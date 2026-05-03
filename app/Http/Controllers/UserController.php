<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\DomainRealmMap;
use App\Services\AuditLogger;
use App\Services\MailcowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private function keycloak(): array
    {
        return [
            'base'  => config('keycloak.base_url'),
            'realm' => session('realm'),
            'token' => session('access_token'),
        ];
    }

    private function realmAdminRoleId(string $base, string $realm, string $token): ?array
    {
        $clients    = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/clients")->json();
        $mgmtClient = collect($clients)->firstWhere('clientId', 'realm-management');

        if (!$mgmtClient) return null;

        $mgmtId = $mgmtClient['id'];
        $roles  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/clients/{$mgmtId}/roles")->json();
        $role   = collect($roles)->firstWhere('name', 'realm-admin');

        return $role ? ['mgmtId' => $mgmtId, 'role' => $role] : null;
    }

    private function adminUserIds(string $base, string $realm, string $token): array
    {
        $realmAdmin = $this->realmAdminRoleId($base, $realm, $token);
        if (!$realmAdmin) return [];

        $users = \Http::withToken($token)
            ->get("{$base}/admin/realms/{$realm}/clients/{$realmAdmin['mgmtId']}/roles/realm-admin/users")
            ->json();

        return collect($users)->pluck('id')->toArray();
    }

    private function isLastAdmin(string $userId, string $base, string $realm, string $token): bool
    {
        $ids = $this->adminUserIds($base, $realm, $token);
        return count($ids) === 1 && in_array($userId, $ids);
    }

    public function index()
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        try {
            $users = \Http::withToken($token)
                ->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])
                ->json();

            $realmAdmin = $this->realmAdminRoleId($base, $realm, $token);
            $adminUserIds = [];

            if ($realmAdmin) {
                $mgmtId     = $realmAdmin['mgmtId'];
                $adminUsers = \Http::withToken($token)
                    ->get("{$base}/admin/realms/{$realm}/clients/{$mgmtId}/roles/realm-admin/users")
                    ->json();
                $adminUserIds = collect($adminUsers)->pluck('id')->toArray();
            }
        } catch (\Throwable) {
            $users        = null;
            $adminUserIds = [];
        }

        $mailcowEnabled   = DomainRealmMap::where('realm', $realm)->value('mailcow_enabled') ?? false;
        $nextcloudEnabled = DomainRealmMap::where('realm', $realm)->value('nextcloud_enabled') ?? false;
        $map              = DomainRealmMap::where('realm', $realm)->first();
        $mailboxEmails    = $mailcowEnabled
            ? Mailbox::where('realm', $realm)->pluck('active', 'email')
            : collect();

        $ncUserIds = [];
        if ($nextcloudEnabled) {
            try {
                $ncGroups = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups", ['search' => 'nextcloud'])->json();
                $ncGroup  = collect((array) $ncGroups)->firstWhere('name', 'nextcloud');
                if ($ncGroup) {
                    $ncMembers = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups/{$ncGroup['id']}/members", ['max' => 1000])->json();
                    $ncUserIds = collect((array) $ncMembers)->mapWithKeys(fn($u) => [$u['email'] => true])->toArray();
                }
            } catch (\Throwable) {}
        }

        $counts = [
            'users'     => count($users ?? []),
            'max_users' => $map?->max_users,
            'mailboxes'     => $mailcowEnabled ? Mailbox::where('realm', $realm)->count() : null,
            'max_mailboxes' => $map?->max_mailbox_users,
            'nextcloud'     => $nextcloudEnabled ? count($ncUserIds) : null,
            'max_nextcloud' => $map?->max_nextcloud_users,
        ];

        return view('users.index', compact('users', 'adminUserIds', 'mailcowEnabled', 'nextcloudEnabled', 'mailboxEmails', 'ncUserIds', 'realm', 'counts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'firstName' => 'required|string|max:255',
            'lastName'  => 'required|string|max:255',
            'email'     => ['required', 'regex:/^[a-zA-Z0-9_.\-]+$/'],
            'password'  => 'required|min:8',
        ]);

        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $email = strtolower(trim($request->email)) . '@' . $realm;

        // Enforce max_users limit
        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_users) {
            $count = count(\Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])->json() ?? []);
            if ($count >= $map->max_users) {
                return back()->withErrors(['user' => "User limit reached ({$map->max_users} users maximum)."]);
            }
        }

        $res = \Http::withToken($token)->post("{$base}/admin/realms/{$realm}/users", [
            'username'      => $email,
            'email'         => $email,
            'firstName'     => trim($request->firstName),
            'lastName'      => trim($request->lastName),
            'enabled'       => true,
            'emailVerified' => true,
            'credentials'   => [[
                'type'      => 'password',
                'value'     => $request->password,
                'temporary' => false,
            ]],
        ]);

        if ($res->failed()) {
            return back()->withErrors(['user' => 'Failed to create user: ' . $res->body()]);
        }

        if ($request->boolean('is_admin')) {
            $userId     = basename($res->header('Location'));
            $realmAdmin = $this->realmAdminRoleId($base, $realm, $token);
            if ($realmAdmin) {
                \Http::withToken($token)->post(
                    "{$base}/admin/realms/{$realm}/users/{$userId}/role-mappings/clients/{$realmAdmin['mgmtId']}",
                    [$realmAdmin['role']]
                );
            }
        }

        AuditLogger::log('user.created', $request->email);
        return redirect()->route('users')->with('success', 'User created.');
    }

    public function update(Request $request, string $userId)
    {
        $request->validate([
            'firstName' => 'required',
            'lastName'  => 'required',
            'email'     => 'required|email',
        ]);

        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $res = \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}", [
            'firstName' => $request->firstName,
            'lastName'  => $request->lastName,
            'email'     => $request->email,
        ]);

        if ($res->failed()) {
            return back()->withErrors(['user' => 'Failed to update user.']);
        }

        if ($request->filled('password')) {
            \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}/reset-password", [
                'type'      => 'password',
                'value'     => $request->password,
                'temporary' => false,
            ]);
        }

        // Sync realm-admin role
        $realmAdmin = $this->realmAdminRoleId($base, $realm, $token);
        if ($realmAdmin) {
            $mgmtId  = $realmAdmin['mgmtId'];
            $role    = $realmAdmin['role'];
            $url     = "{$base}/admin/realms/{$realm}/users/{$userId}/role-mappings/clients/{$mgmtId}";
            $makeAdmin = $request->boolean('is_admin');

            // Prevent removing the last realm admin
            if (!$makeAdmin && $this->isLastAdmin($userId, $base, $realm, $token)) {
                return redirect()->route('users')->withErrors(['user' => 'Cannot remove the realm-admin role from the last admin.']);
            }

            if ($makeAdmin) {
                \Http::withToken($token)->post($url, [$role]);
            } else {
                \Http::withToken($token)->delete($url, [$role]);
            }
        }

        AuditLogger::log('user.updated', $request->email);
        return redirect()->route('users')->with('success', 'User updated.');
    }

    public function toggle(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $user    = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}")->json();
        $enabled = !($user['enabled'] ?? false);

        // Prevent disabling the last realm admin
        if (!$enabled && $this->isLastAdmin($userId, $base, $realm, $token)) {
            return redirect()->route('users')->withErrors(['user' => 'Cannot disable the last realm admin.']);
        }

        \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}", ['enabled' => $enabled]);

        $status = $enabled ? 'enabled' : 'disabled';
        AuditLogger::log("user.{$status}", $user['email'] ?? $userId);
        return redirect()->route('users')->with('success', 'User ' . $status . '.');
    }

    public function toggleMailbox(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $user  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}")->json();
        $email = $user['email'] ?? null;

        if (!$email) {
            return back()->withErrors(['user' => 'User has no email address.']);
        }

        $mailcow = new MailcowService($realm);

        if (!$mailcow->isConfigured()) {
            return back()->withErrors(['user' => 'Mailcow is not configured for this realm.']);
        }

        $mailbox = Mailbox::where('email', $email)->first();

        if ($mailbox) {
            $res = \Http::withHeaders($mailcow->headers())->post($mailcow->url('delete/mailbox'), [$email]);
            if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
                $detail = $res->json()[0]['msg'] ?? $res->body();
                return back()->withErrors(['user' => "Failed to delete mailbox: {$detail}"]);
            }
            $mailbox->delete();
            AuditLogger::log('mailbox.deleted', $email);
            return redirect()->route('users')->with('success', "Mailbox {$email} deleted.");
        }

        // Enforce max_mailbox_users limit
        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_mailbox_users) {
            $mailboxCount = Mailbox::where('realm', $realm)->count();
            if ($mailboxCount >= $map->max_mailbox_users) {
                return back()->withErrors(['user' => "Mailbox limit reached ({$map->max_mailbox_users} mailboxes maximum)."]);
            }
        }

        $password = bin2hex(random_bytes(12));
        $res = \Http::withHeaders($mailcow->headers())->post($mailcow->url('add/mailbox'), [
            'local_part' => \Str::before($email, '@'),
            'domain'     => \Str::after($email, '@'),
            'name'       => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')),
            'password'   => $password,
            'password2'  => $password,
            'active'     => '1',
        ]);

        if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
            $detail = $res->json()[0]['msg'] ?? $res->body();
            return back()->withErrors(['user' => "Failed to create mailbox: {$detail}"]);
        }

        Mailbox::create(['email' => $email, 'realm' => $realm, 'active' => true]);
        AuditLogger::log('mailbox.created', $email);
        return redirect()->route('users')->with('success', "Mailbox {$email} created.");
    }

    public function toggleNextcloud(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $user  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}")->json();
        $email = $user['email'] ?? null;

        if (!$email) {
            return back()->withErrors(['user' => 'User has no email address.']);
        }

        // Find the nextcloud KC group in the tenant realm
        $groups  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups", ['search' => 'nextcloud'])->json();
        $ncGroup = collect((array) $groups)->firstWhere('name', 'nextcloud');

        if (!$ncGroup) {
            return back()->withErrors(['user' => 'Nextcloud access group not found in this realm.']);
        }

        $groupId = $ncGroup['id'];

        // Check current membership in the nextcloud group
        $userGroups = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}/groups")->json();
        $inGroup    = collect((array) $userGroups)->contains('id', $groupId);

        if ($inGroup) {
            // Remove from KC group and delete NC account for cleanup
            $res = \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$groupId}");
            if ($res->failed()) {
                return back()->withErrors(['user' => 'Failed to remove Nextcloud access.']);
            }

            $nc = new \App\Services\NextcloudService($realm);
            if ($nc->isConfigured()) {
                $nc->delete("cloud/users/{$email}");
            }

            AuditLogger::log('nextcloud_user.deleted', $email);
            return redirect()->route('users')->with('success', "Nextcloud access removed for {$email}.");
        }

        // Enforce max_nextcloud_users against current group member count
        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_nextcloud_users) {
            $members = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups/{$groupId}/members", ['max' => 1000])->json();
            if (count((array) $members) >= $map->max_nextcloud_users) {
                return back()->withErrors(['user' => "Nextcloud user limit reached ({$map->max_nextcloud_users} maximum)."]);
            }
        }

        // Add to KC group — NC account is auto-provisioned by user_oidc on first login
        $res = \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$groupId}");
        if ($res->failed()) {
            return back()->withErrors(['user' => 'Failed to grant Nextcloud access.']);
        }

        AuditLogger::log('nextcloud_user.created', $email);
        return redirect()->route('users')->with('success', "Nextcloud access enabled for {$email}.");
    }

    public function destroy(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        if ($this->isLastAdmin($userId, $base, $realm, $token)) {
            return redirect()->route('users')->withErrors(['user' => 'Cannot delete the last realm admin.']);
        }

        $user  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}")->json();
        $email = $user['email'] ?? null;

        $res = \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}");

        if ($res->failed()) {
            return back()->withErrors(['user' => 'Failed to delete user.']);
        }

        // Clean up mailbox
        if ($email) {
            $mailbox = Mailbox::where('email', $email)->first();
            if ($mailbox) {
                $mailcow = new \App\Services\MailcowService($realm);
                if ($mailcow->isConfigured()) {
                    \Http::withHeaders($mailcow->headers())->post($mailcow->url('delete/mailbox'), [$email]);
                }
                $mailbox->delete();
            }
        }

        // Clean up Nextcloud account (best-effort)
        if ($email) {
            $nc = new \App\Services\NextcloudService($realm);
            if ($nc->isConfigured()) {
                $nc->delete("cloud/users/{$email}");
            }
        }

        AuditLogger::log('user.deleted', $email ?? $userId);
        return redirect()->route('users')->with('success', 'User deleted.');
    }
}
