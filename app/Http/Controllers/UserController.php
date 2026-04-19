<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\DomainRealmMap;
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

        $mailcowEnabled = DomainRealmMap::where('realm', $realm)->value('mailcow_enabled') ?? false;
        $mailboxEmails  = $mailcowEnabled
            ? Mailbox::where('realm', $realm)->pluck('active', 'email')
            : collect();

        return view('users.index', compact('users', 'adminUserIds', 'mailcowEnabled', 'mailboxEmails'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'firstName' => 'required',
            'lastName'  => 'required',
            'email'     => 'required|email',
            'password'  => 'required|min:8',
        ]);

        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $res = \Http::withToken($token)->post("{$base}/admin/realms/{$realm}/users", [
            'username'      => $request->email,
            'email'         => $request->email,
            'firstName'     => $request->firstName,
            'lastName'      => $request->lastName,
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

        return redirect()->route('users')->with('success', 'User ' . ($enabled ? 'enabled' : 'disabled') . '.');
    }

    public function toggleMailbox(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        $user  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}")->json();
        $email = $user['email'] ?? null;

        if (!$email) {
            return back()->withErrors(['user' => 'User has no email address.']);
        }

        $mailcowUrl = rtrim(config('mailcow.url'), '/');
        $headers    = ['X-API-Key' => config('mailcow.api_key'), 'Accept' => 'application/json'];
        $mailbox    = Mailbox::where('email', $email)->first();

        if ($mailbox) {
            // Delete mailbox from Mailcow
            $res = \Http::withHeaders($headers)->post("{$mailcowUrl}/api/v1/delete/mailbox", [$email]);
            if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
                $detail = $res->json()[0]['msg'] ?? $res->body();
                return back()->withErrors(['user' => "Failed to delete mailbox: {$detail}"]);
            }
            $mailbox->delete();
            return redirect()->route('users')->with('success', "Mailbox {$email} deleted.");
        }

        // Create mailbox in Mailcow
        $password = bin2hex(random_bytes(12));
        $res = \Http::withHeaders($headers)->post("{$mailcowUrl}/api/v1/add/mailbox", [
            'local_part'  => Str::before($email, '@'),
            'domain'      => Str::after($email, '@'),
            'name'        => trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')),
            'password'    => $password,
            'password2'   => $password,
            'active'      => '1',
        ]);

        if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
            $detail = $res->json()[0]['msg'] ?? $res->body();
            return back()->withErrors(['user' => "Failed to create mailbox: {$detail}"]);
        }

        Mailbox::create(['email' => $email, 'realm' => $realm, 'active' => true]);
        return redirect()->route('users')->with('success', "Mailbox {$email} created.");
    }

    public function destroy(string $userId)
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->keycloak();

        if ($this->isLastAdmin($userId, $base, $realm, $token)) {
            return redirect()->route('users')->withErrors(['user' => 'Cannot delete the last realm admin.']);
        }

        $res = \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}");

        if ($res->failed()) {
            return back()->withErrors(['user' => 'Failed to delete user.']);
        }

        return redirect()->route('users')->with('success', 'User deleted.');
    }
}
