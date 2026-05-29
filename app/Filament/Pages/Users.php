<?php

namespace App\Filament\Pages;

use BackedEnum;

use App\Models\DomainRealmMap;
use App\Models\Mailbox;
use App\Services\AuditLogger;
use App\Services\MailcowService;
use App\Services\NextcloudService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class Users extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $title           = 'Users';
    protected static ?int    $navigationSort  = 2;
    protected string $view            = 'filament.pages.users';

    // ── helpers ──────────────────────────────────────────────────────────────

    private function kc(): array
    {
        return [
            'base'  => config('keycloak.base_url'),
            'realm' => session('realm'),
            'token' => session('access_token'),
        ];
    }

    private function realmAdminRole(string $base, string $realm, string $token): ?array
    {
        $clients  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/clients")->json() ?? [];
        $mgmt     = collect($clients)->firstWhere('clientId', 'realm-management');
        if (! $mgmt) return null;

        $roles = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/clients/{$mgmt['id']}/roles")->json() ?? [];
        $role  = collect($roles)->firstWhere('name', 'realm-admin');

        return $role ? ['mgmtId' => $mgmt['id'], 'role' => $role] : null;
    }

    private function adminUserIds(string $base, string $realm, string $token): array
    {
        $ra = $this->realmAdminRole($base, $realm, $token);
        if (! $ra) return [];

        $users = \Http::withToken($token)
            ->get("{$base}/admin/realms/{$realm}/clients/{$ra['mgmtId']}/roles/realm-admin/users")
            ->json() ?? [];

        return collect($users)->pluck('id')->toArray();
    }

    private function isLastAdmin(string $userId, string $base, string $realm, string $token): bool
    {
        $ids = $this->adminUserIds($base, $realm, $token);
        return count($ids) === 1 && in_array($userId, $ids);
    }

    private function fetchUsers(): Collection
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->kc();

        try {
            $users        = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])->json() ?? [];
            $adminUserIds = $this->adminUserIds($base, $realm, $token);
        } catch (\Throwable) {
            return collect();
        }

        $mailcowEnabled   = DomainRealmMap::where('realm', $realm)->value('mailcow_enabled') ?? false;
        $nextcloudEnabled = DomainRealmMap::where('realm', $realm)->value('nextcloud_enabled') ?? false;
        $mailboxEmails    = $mailcowEnabled ? Mailbox::where('realm', $realm)->pluck('active', 'email') : collect();

        $ncUserEmails = [];
        if ($nextcloudEnabled) {
            try {
                $ncGroup = collect(\Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups", ['search' => 'nextcloud'])->json() ?? [])->firstWhere('name', 'nextcloud');
                if ($ncGroup) {
                    $members    = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups/{$ncGroup['id']}/members", ['max' => 1000])->json() ?? [];
                    $ncUserEmails = collect($members)->pluck('email')->flip()->toArray();
                }
            } catch (\Throwable) {}
        }

        return collect($users)->map(fn ($u) => array_merge($u, [
            'is_admin'          => in_array($u['id'], $adminUserIds),
            'has_mailbox'       => $mailcowEnabled && isset($mailboxEmails[$u['email'] ?? '']),
            'has_nextcloud'     => $nextcloudEnabled && isset($ncUserEmails[$u['email'] ?? '']),
            'mailcow_enabled'   => $mailcowEnabled,
            'nextcloud_enabled' => $nextcloudEnabled,
        ]));
    }

    // ── table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->records($this->fetchUsers())
            ->recordKey('id')
            ->columns([
                TextColumn::make('firstName')
                    ->label('First name')
                    ->searchable(isIndividual: false),
                TextColumn::make('lastName')
                    ->label('Last name')
                    ->searchable(isIndividual: false),
                TextColumn::make('email')
                    ->searchable(isIndividual: false),
                IconColumn::make('enabled')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->trueIcon('heroicon-s-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                IconColumn::make('has_mailbox')
                    ->label('Mailbox')
                    ->boolean()
                    ->visible(fn () => DomainRealmMap::where('realm', session('realm'))->value('mailcow_enabled')),
                IconColumn::make('has_nextcloud')
                    ->label('Nextcloud')
                    ->boolean()
                    ->visible(fn () => DomainRealmMap::where('realm', session('realm'))->value('nextcloud_enabled')),
            ])
            ->actions([
                TableAction::make('toggle_enabled')
                    ->label(fn (array $record) => ($record['enabled'] ?? false) ? 'Disable' : 'Enable')
                    ->icon(fn (array $record) => ($record['enabled'] ?? false) ? 'heroicon-o-user-minus' : 'heroicon-o-user-plus')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => $this->toggleEnabled($record)),

                TableAction::make('toggle_mailbox')
                    ->label(fn (array $record) => $record['has_mailbox'] ? 'Remove Mailbox' : 'Add Mailbox')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn (array $record) => $record['mailcow_enabled'] ?? false)
                    ->action(fn (array $record) => $this->toggleMailbox($record)),

                TableAction::make('toggle_nextcloud')
                    ->label(fn (array $record) => $record['has_nextcloud'] ? 'Remove Nextcloud' : 'Add Nextcloud')
                    ->icon('heroicon-o-cloud')
                    ->requiresConfirmation()
                    ->visible(fn (array $record) => $record['nextcloud_enabled'] ?? false)
                    ->action(fn (array $record) => $this->toggleNextcloud($record)),

                TableAction::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => $this->deleteUser($record)),
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('create_user')
                    ->label('New User')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        TextInput::make('firstName')->label('First name')->required(),
                        TextInput::make('lastName')->label('Last name')->required(),
                        TextInput::make('email')
                            ->label('Email (local part)')
                            ->required()
                            ->suffix(fn () => '@' . session('realm'))
                            ->regex('/^[a-zA-Z0-9_.\-]+$/'),
                        TextInput::make('password')->password()->revealable()->required()->minLength(8),
                        Checkbox::make('is_admin')->label('Make realm admin'),
                    ])
                    ->action(fn (array $data) => $this->createUser($data)),
            ]);
    }

    // ── actions ───────────────────────────────────────────────────────────────

    private function toggleEnabled(array $record): void
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->kc();
        $userId  = $record['id'];
        $enabled = ! ($record['enabled'] ?? false);

        if (! $enabled && $this->isLastAdmin($userId, $base, $realm, $token)) {
            Notification::make()->title('Cannot disable the last realm admin.')->danger()->send();
            return;
        }

        \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}", ['enabled' => $enabled]);
        AuditLogger::log($enabled ? 'user.enabled' : 'user.disabled', $record['email'] ?? $userId);
        Notification::make()->title('User ' . ($enabled ? 'enabled' : 'disabled') . '.')->success()->send();
    }

    private function toggleMailbox(array $record): void
    {
        $realm   = session('realm');
        $email   = $record['email'] ?? null;

        if (! $email) {
            Notification::make()->title('User has no email address.')->danger()->send();
            return;
        }

        $mailcow = new MailcowService($realm);
        if (! $mailcow->isConfigured()) {
            Notification::make()->title('Mailcow is not configured for this realm.')->danger()->send();
            return;
        }

        $mailbox = Mailbox::where('email', $email)->first();

        if ($mailbox) {
            $res = \Http::withHeaders($mailcow->headers())->post($mailcow->url('delete/mailbox'), [$email]);
            if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
                Notification::make()->title('Failed to delete mailbox: ' . ($res->json()[0]['msg'] ?? $res->body()))->danger()->send();
                return;
            }
            $mailbox->delete();
            AuditLogger::log('mailbox.deleted', $email);
            Notification::make()->title("Mailbox {$email} deleted.")->success()->send();
            return;
        }

        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_mailbox_users && Mailbox::where('realm', $realm)->count() >= $map->max_mailbox_users) {
            Notification::make()->title("Mailbox limit reached ({$map->max_mailbox_users} maximum).")->danger()->send();
            return;
        }

        $password = bin2hex(random_bytes(12));
        $res = \Http::withHeaders($mailcow->headers())->post($mailcow->url('add/mailbox'), [
            'local_part' => \Str::before($email, '@'),
            'domain'     => \Str::after($email, '@'),
            'name'       => trim(($record['firstName'] ?? '') . ' ' . ($record['lastName'] ?? '')),
            'password'   => $password,
            'password2'  => $password,
            'active'     => '1',
        ]);

        if ($res->failed() || ($res->json()[0]['type'] ?? '') === 'error') {
            Notification::make()->title('Failed to create mailbox: ' . ($res->json()[0]['msg'] ?? $res->body()))->danger()->send();
            return;
        }

        Mailbox::create(['email' => $email, 'realm' => $realm, 'active' => true]);
        AuditLogger::log('mailbox.created', $email);
        Notification::make()->title("Mailbox {$email} created.")->success()->send();
    }

    private function toggleNextcloud(array $record): void
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->kc();
        $userId = $record['id'];
        $email  = $record['email'] ?? null;

        if (! $email) {
            Notification::make()->title('User has no email address.')->danger()->send();
            return;
        }

        $groups  = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups", ['search' => 'nextcloud'])->json() ?? [];
        $ncGroup = collect($groups)->firstWhere('name', 'nextcloud');

        if (! $ncGroup) {
            Notification::make()->title('Nextcloud access group not found.')->danger()->send();
            return;
        }

        $groupId    = $ncGroup['id'];
        $userGroups = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users/{$userId}/groups")->json() ?? [];
        $inGroup    = collect($userGroups)->contains('id', $groupId);

        if ($inGroup) {
            \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$groupId}");
            $nc = new NextcloudService($realm);
            if ($nc->isConfigured()) $nc->deleteUser($email);
            AuditLogger::log('nextcloud_user.deleted', $email);
            Notification::make()->title("Nextcloud access removed for {$email}.")->success()->send();
            return;
        }

        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_nextcloud_users) {
            $members = \Http::withToken($token)->get("{$base}/admin/realms/{$realm}/groups/{$groupId}/members", ['max' => 1000])->json() ?? [];
            if (count($members) >= $map->max_nextcloud_users) {
                Notification::make()->title("Nextcloud user limit reached ({$map->max_nextcloud_users} maximum).")->danger()->send();
                return;
            }
        }

        \Http::withToken($token)->put("{$base}/admin/realms/{$realm}/users/{$userId}/groups/{$groupId}");
        $nc          = new NextcloudService($realm);
        $displayName = trim(($record['firstName'] ?? '') . ' ' . ($record['lastName'] ?? '')) ?: $email;
        if ($nc->isConfigured()) $nc->createUser($email, $displayName);
        AuditLogger::log('nextcloud_user.created', $email);
        Notification::make()->title("Nextcloud access enabled for {$email}.")->success()->send();
    }

    private function createUser(array $data): void
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->kc();

        $email = strtolower(trim($data['email'])) . '@' . $realm;

        $map = DomainRealmMap::where('realm', $realm)->first();
        if ($map?->max_users) {
            $count = count(\Http::withToken($token)->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])->json() ?? []);
            if ($count >= $map->max_users) {
                Notification::make()->title("User limit reached ({$map->max_users} maximum).")->danger()->send();
                return;
            }
        }

        $res = \Http::withToken($token)->post("{$base}/admin/realms/{$realm}/users", [
            'username'      => $email,
            'email'         => $email,
            'firstName'     => trim($data['firstName']),
            'lastName'      => trim($data['lastName']),
            'enabled'       => true,
            'emailVerified' => true,
            'credentials'   => [['type' => 'password', 'value' => $data['password'], 'temporary' => false]],
        ]);

        if ($res->failed()) {
            Notification::make()->title('Failed to create user: ' . $res->body())->danger()->send();
            return;
        }

        if ($data['is_admin'] ?? false) {
            $userId = basename($res->header('Location'));
            $ra     = $this->realmAdminRole($base, $realm, $token);
            if ($ra) {
                \Http::withToken($token)->post(
                    "{$base}/admin/realms/{$realm}/users/{$userId}/role-mappings/clients/{$ra['mgmtId']}",
                    [$ra['role']]
                );
            }
        }

        AuditLogger::log('user.created', $data['email']);
        Notification::make()->title('User created.')->success()->send();
    }

    private function deleteUser(array $record): void
    {
        ['base' => $base, 'realm' => $realm, 'token' => $token] = $this->kc();
        $userId = $record['id'];
        $email  = $record['email'] ?? null;

        if ($this->isLastAdmin($userId, $base, $realm, $token)) {
            Notification::make()->title('Cannot delete the last realm admin.')->danger()->send();
            return;
        }

        $res = \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/users/{$userId}");
        if ($res->failed()) {
            Notification::make()->title('Failed to delete user.')->danger()->send();
            return;
        }

        if ($email) {
            $mailbox = Mailbox::where('email', $email)->first();
            if ($mailbox) {
                $mc = new MailcowService($realm);
                if ($mc->isConfigured()) \Http::withHeaders($mc->headers())->post($mc->url('delete/mailbox'), [$email]);
                $mailbox->delete();
            }
            $nc = new NextcloudService($realm);
            if ($nc->isConfigured()) $nc->deleteUser($email);
        }

        AuditLogger::log('user.deleted', $email ?? $userId);
        Notification::make()->title('User deleted.')->success()->send();
    }
}
