<?php

namespace App\Http\Controllers;

use App\Models\DomainRealmMap;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mailbox;
use App\Services\AuditLogger;
use App\Services\MailcowService;
use App\Services\NextcloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    private function keycloak(): array
    {
        return [
            'base'  => config('keycloak.base_url'),
            'realm' => session('realm'),
            'token' => session('access_token'),
        ];
    }

    public function index()
    {
        $realm = session('realm');
        $map   = DomainRealmMap::where('realm', $realm)->first();

        $groups = Group::where('realm', $realm)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('groups.index', [
            'groups'           => $groups,
            'mailcowEnabled'   => $map?->mailcow_enabled ?? false,
            'nextcloudEnabled' => $map?->nextcloud_enabled ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $realm = session('realm');
        $map   = DomainRealmMap::where('realm', $realm)->first();

        $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:mailing_list,security',
            'local_part' => 'required_if:type,mailing_list|nullable|regex:/^[a-zA-Z0-9._-]+$/',
        ]);

        if ($request->type === 'mailing_list' && !($map?->mailcow_enabled)) {
            return back()->withErrors(['name' => 'Mailcow is not enabled for this realm.']);
        }

        $slug = Str::slug($realm . '-' . $request->name);

        if (Group::where('realm', $realm)->where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A group with this name already exists.']);
        }

        $email       = null;
        $keycloakId  = null;
        $nextcloudId = null;

        if ($request->type === 'mailing_list') {
            $email = strtolower(trim($request->local_part)) . '@' . $realm;

            if (Group::where('email', $email)->exists()) {
                return back()->withErrors(['local_part' => 'This email address is already in use by another group.']);
            }
        }

        if ($request->type === 'security') {
            ['base' => $base, 'token' => $token] = $this->keycloak();

            $res = \Http::withToken($token)->post("{$base}/admin/realms/{$realm}/groups", [
                'name' => $slug,
            ]);

            if ($res->successful()) {
                $keycloakId = basename($res->header('Location'));
            }

            if ($map?->nextcloud_enabled) {
                $nc = new NextcloudService($realm);
                if ($nc->isConfigured()) {
                    $nc->createGroup($slug);
                    $nextcloudId = $slug;
                }
            }
        }

        Group::create([
            'realm'       => $realm,
            'name'        => $request->name,
            'slug'        => $slug,
            'type'        => $request->type,
            'email'       => $email,
            'keycloak_id' => $keycloakId,
            'nextcloud_id' => $nextcloudId,
        ]);

        AuditLogger::log('group.created', $request->name);
        return redirect()->route('groups')->with('success', "Group \"{$request->name}\" created.");
    }

    public function show(string $id)
    {
        $realm = session('realm');
        $group = Group::where('id', $id)->where('realm', $realm)->firstOrFail();
        $map   = DomainRealmMap::where('realm', $realm)->first();

        ['base' => $base, 'token' => $token] = $this->keycloak();

        try {
            $kcUsers = \Http::withToken($token)
                ->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])
                ->json() ?? [];
        } catch (\Throwable) {
            $kcUsers = [];
        }

        $userMap = collect($kcUsers)->keyBy('id')->map(fn($u) => [
            'id'    => $u['id'],
            'name'  => trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')),
            'email' => $u['email'] ?? '',
        ]);

        // Mailing lists: restrict to users with an active mailbox
        if ($group->type === 'mailing_list') {
            $mailboxEmails = Mailbox::where('realm', $realm)->pluck('email')->flip();
            $userMap = $userMap->filter(fn($u) => isset($mailboxEmails[$u['email']]));
        }

        $currentMembers  = $group->members()->get();
        $memberUserIds   = $currentMembers->pluck('user_id')->flip();

        $availableUsers = $userMap->filter(fn($u) => !isset($memberUserIds[$u['id']]))->values();

        $membersWithNames = $currentMembers->map(function ($m) use ($userMap) {
            $kc = $userMap->get($m->user_id);
            return [
                'user_id' => $m->user_id,
                'email'   => $m->email,
                'name'    => $kc ? $kc['name'] : $m->email,
            ];
        });

        return view('groups.show', [
            'group'            => $group,
            'availableUsers'   => $availableUsers,
            'membersWithNames' => $membersWithNames,
            'mailcowEnabled'   => $map?->mailcow_enabled ?? false,
            'nextcloudEnabled' => $map?->nextcloud_enabled ?? false,
        ]);
    }

    public function syncMembers(Request $request, string $id)
    {
        $realm = session('realm');
        $group = Group::where('id', $id)->where('realm', $realm)->firstOrFail();

        // Each item in members[] is "userId|email"
        $incoming = collect($request->input('members', []))->mapWithKeys(function ($item) {
            [$userId, $email] = explode('|', $item, 2);
            return [$userId => $email];
        });

        $current = $group->members()->get()->keyBy('user_id');

        $toAddIds    = array_diff($incoming->keys()->toArray(), $current->keys()->toArray());
        $toRemoveIds = array_diff($current->keys()->toArray(), $incoming->keys()->toArray());

        if ($group->type === 'security') {
            ['base' => $base, 'token' => $token] = $this->keycloak();
            $nc = new NextcloudService($realm);

            foreach ($toAddIds as $userId) {
                $email = $incoming[$userId];

                if ($group->keycloak_id) {
                    \Http::withToken($token)->put(
                        "{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$group->keycloak_id}"
                    );
                }

                if ($nc->isConfigured() && $group->nextcloud_id) {
                    $nc->addGroupMember($email, $group->nextcloud_id);
                }

                GroupMember::firstOrCreate(
                    ['group_id' => $group->id, 'user_id' => $userId],
                    ['email' => $email]
                );
            }

            foreach ($toRemoveIds as $userId) {
                $member = $current[$userId];

                if ($group->keycloak_id) {
                    \Http::withToken($token)->delete(
                        "{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$group->keycloak_id}"
                    );
                }

                if ($nc->isConfigured() && $group->nextcloud_id) {
                    $nc->removeGroupMember($member->email, $group->nextcloud_id);
                }

                $member->delete();
            }
        } else {
            foreach ($toAddIds as $userId) {
                GroupMember::firstOrCreate(
                    ['group_id' => $group->id, 'user_id' => $userId],
                    ['email' => $incoming[$userId]]
                );
            }

            foreach ($toRemoveIds as $userId) {
                $current[$userId]->delete();
            }

            $this->syncMailcowAlias($group->fresh());
        }

        AuditLogger::log('group.members_updated', $group->name);
        return redirect()->route('groups.show', $id)->with('success', 'Members saved.');
    }

    public function destroy(string $id)
    {
        $realm = session('realm');
        $group = Group::where('id', $id)->where('realm', $realm)->firstOrFail();

        if ($group->type === 'mailing_list') {
            if ($group->mailcow_alias_id) {
                $mailcow = new MailcowService($realm);
                if ($mailcow->isConfigured()) {
                    $mailcow->deleteAlias($group->mailcow_alias_id);
                }
            }
        } else {
            ['base' => $base, 'token' => $token] = $this->keycloak();

            if ($group->keycloak_id) {
                \Http::withToken($token)->delete(
                    "{$base}/admin/realms/{$realm}/groups/{$group->keycloak_id}"
                );
            }

            if ($group->nextcloud_id) {
                $nc = new NextcloudService($realm);
                if ($nc->isConfigured()) {
                    $nc->deleteGroup($group->nextcloud_id);
                }
            }
        }

        $name = $group->name;
        $group->delete(); // cascades to group_members

        AuditLogger::log('group.deleted', $name);
        return redirect()->route('groups')->with('success', "Group \"{$name}\" deleted.");
    }

    private function syncMailcowAlias(Group $group): void
    {
        $mailcow = new MailcowService($group->realm);
        if (!$mailcow->isConfigured()) {
            return;
        }

        $gotos = $group->members()->pluck('email')->toArray();

        if (empty($gotos)) {
            if ($group->mailcow_alias_id) {
                $mailcow->deleteAlias($group->mailcow_alias_id);
                $group->update(['mailcow_alias_id' => null]);
            }
            return;
        }

        if ($group->mailcow_alias_id) {
            $mailcow->updateAlias($group->mailcow_alias_id, $gotos);
        } else {
            $aliasId = $mailcow->createAlias($group->email, $gotos);
            if ($aliasId) {
                $group->update(['mailcow_alias_id' => $aliasId]);
            }
        }
    }
}
