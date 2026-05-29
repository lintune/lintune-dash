<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use App\Models\DomainRealmMap;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mailbox;
use App\Services\AuditLogger;
use App\Services\MailcowService;
use App\Services\NextcloudService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManageGroupMembers extends Page
{
    protected static string  $resource = GroupResource::class;
    protected string $view     = 'filament.resources.group-resource.pages.manage-group-members';

    public Group  $record;
    public array  $selectedMembers = [];
    public array  $availableUsers  = [];
    public array  $currentMembers  = [];

    public function mount(int|string $record): void
    {
        $realm       = session('realm');
        $this->record = Group::where('id', $record)->where('realm', $realm)->firstOrFail();

        $base  = config('keycloak.base_url');
        $token = session('access_token');

        try {
            $kcUsers = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])->json() ?? [];
        } catch (\Throwable) {
            $kcUsers = [];
        }

        $userMap = collect($kcUsers)->keyBy('id')->map(fn ($u) => [
            'id'    => $u['id'],
            'name'  => trim(($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')),
            'email' => $u['email'] ?? '',
        ]);

        if ($this->record->type === 'mailing_list') {
            $mailboxEmails = Mailbox::where('realm', $realm)->pluck('email')->flip();
            $userMap = $userMap->filter(fn ($u) => isset($mailboxEmails[$u['email']]));
        }

        $currentMembers       = $this->record->members()->get();
        $memberUserIds        = $currentMembers->pluck('user_id')->flip();
        $this->currentMembers = $currentMembers->map(fn ($m) => [
            'user_id' => $m->user_id,
            'email'   => $m->email,
            'name'    => $userMap->get($m->user_id)['name'] ?? $m->email,
        ])->values()->toArray();

        $this->availableUsers  = $userMap->filter(fn ($u) => ! isset($memberUserIds[$u['id']]))->values()->toArray();
        $this->selectedMembers = [];
    }

    public function save(): void
    {
        $realm = session('realm');
        $group = $this->record;

        $incoming = collect($this->selectedMembers)->mapWithKeys(function ($item) {
            [$userId, $email] = explode('|', $item, 2);
            return [$userId => $email];
        });

        $current     = $group->members()->get()->keyBy('user_id');
        $toAddIds    = array_diff($incoming->keys()->toArray(), $current->keys()->toArray());
        $toRemoveIds = array_diff($current->keys()->toArray(), $incoming->keys()->toArray());

        if ($group->type === 'security') {
            $base  = config('keycloak.base_url');
            $token = session('access_token');
            $nc    = new NextcloudService($realm);

            foreach ($toAddIds as $userId) {
                $email = $incoming[$userId];
                if ($group->keycloak_id) {
                    \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$group->keycloak_id}");
                }
                if ($nc->isConfigured() && $group->nextcloud_id) {
                    $nc->addGroupMember($email, $group->nextcloud_id);
                }
                GroupMember::firstOrCreate(['group_id' => $group->id, 'user_id' => $userId], ['email' => $email]);
            }

            foreach ($toRemoveIds as $userId) {
                $member = $current[$userId];
                if ($group->keycloak_id) {
                    \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$group->keycloak_id}");
                }
                if ($nc->isConfigured() && $group->nextcloud_id) {
                    $nc->removeGroupMember($member->email, $group->nextcloud_id);
                }
                $member->delete();
            }
        } else {
            foreach ($toAddIds as $userId) {
                GroupMember::firstOrCreate(['group_id' => $group->id, 'user_id' => $userId], ['email' => $incoming[$userId]]);
            }
            foreach ($toRemoveIds as $userId) {
                $current[$userId]->delete();
            }
            $this->syncMailcowAlias($group->fresh());
        }

        AuditLogger::log('group.members_updated', $group->name);
        Notification::make()->title('Members saved.')->success()->send();
        $this->mount($group->id);
    }

    private function syncMailcowAlias(Group $group): void
    {
        $mc = new MailcowService($group->realm);
        if (! $mc->isConfigured()) return;

        $gotos = $group->members()->pluck('email')->toArray();

        if (empty($gotos)) {
            if ($group->mailcow_alias_id) {
                $mc->deleteAlias($group->mailcow_alias_id);
                $group->update(['mailcow_alias_id' => null]);
            }
            return;
        }

        if ($group->mailcow_alias_id) {
            $mc->updateAlias($group->mailcow_alias_id, $gotos);
        } else {
            $aliasId = $mc->createAlias($group->email, $gotos);
            if ($aliasId) $group->update(['mailcow_alias_id' => $aliasId]);
        }
    }
}
