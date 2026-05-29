<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use App\Models\DomainRealmMap;
use App\Models\Group;
use App\Services\AuditLogger;
use App\Services\NextcloudService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function handleRecordCreation(array $data): Group
    {
        $realm = session('realm');
        $map   = DomainRealmMap::where('realm', $realm)->first();

        if ($data['type'] === 'mailing_list' && ! ($map?->mailcow_enabled)) {
            Notification::make()->title('Mailcow is not enabled for this realm.')->danger()->send();
            $this->halt();
        }

        $slug = Str::slug($realm . '-' . $data['name']);

        if (Group::where('realm', $realm)->where('slug', $slug)->exists()) {
            Notification::make()->title('A group with this name already exists.')->danger()->send();
            $this->halt();
        }

        $email = null;
        if ($data['type'] === 'mailing_list') {
            $email = strtolower(trim($data['local_part'] ?? '')) . '@' . $realm;
            if (Group::where('email', $email)->exists()) {
                Notification::make()->title('This email address is already in use.')->danger()->send();
                $this->halt();
            }
        }

        $keycloakId  = null;
        $nextcloudId = null;

        if ($data['type'] === 'security') {
            $base  = config('keycloak.base_url');
            $token = session('access_token');

            $res = \Http::withToken($token)->post("{$base}/admin/realms/{$realm}/groups", ['name' => $slug]);
            if ($res->successful()) $keycloakId = basename($res->header('Location'));

            if ($map?->nextcloud_enabled) {
                $nc = new NextcloudService($realm);
                if ($nc->isConfigured()) {
                    $nc->createGroup($slug);
                    $nextcloudId = $slug;
                }
            }
        }

        $group = Group::create([
            'realm'        => $realm,
            'name'         => $data['name'],
            'slug'         => $slug,
            'type'         => $data['type'],
            'email'        => $email,
            'keycloak_id'  => $keycloakId,
            'nextcloud_id' => $nextcloudId,
        ]);

        AuditLogger::log('group.created', $data['name']);
        return $group;
    }
}
