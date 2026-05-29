<?php

namespace App\Filament\Resources;

use BackedEnum;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\DomainRealmMap;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mailbox;
use App\Services\AuditLogger;
use App\Services\MailcowService;
use App\Services\NextcloudService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupResource extends Resource
{
    protected static ?string $model           = Group::class;
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Groups';
    protected static ?int    $navigationSort  = 3;

    public static function canAccess(): bool
    {
        return (bool) session('is_realm_admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('realm', session('realm'))
            ->withCount('members');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required()->maxLength(100),
            Select::make('type')
                ->options(['mailing_list' => 'Mailing list', 'security' => 'Security group'])
                ->required()
                ->disabledOn('edit'),
            TextInput::make('local_part')
                ->label('Email local part (mailing lists only)')
                ->helperText(fn () => 'Will become local_part@' . session('realm'))
                ->requiredIf('type', 'mailing_list')
                ->regex('/^[a-zA-Z0-9._-]+$/')
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'mailing_list' => 'Mailing list',
                        'security'     => 'Security',
                        default        => $state,
                    })
                    ->color(fn ($state) => $state === 'mailing_list' ? 'info' : 'success'),
                TextColumn::make('email')->label('Address')->placeholder('—'),
                TextColumn::make('members_count')->label('Members')->sortable(),
            ])
            ->actions([
                Action::make('manage_members')
                    ->label('Members')
                    ->icon('heroicon-o-user-group')
                    ->url(fn (Group $record) => static::getUrl('members', ['record' => $record])),
                DeleteAction::make()
                    ->before(fn (Group $record) => static::handleDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListGroups::route('/'),
            'create'  => Pages\CreateGroup::route('/create'),
            'members' => Pages\ManageGroupMembers::route('/{record}/members'),
        ];
    }

    public static function handleDelete(Group $record): void
    {
        if ($record->type === 'mailing_list') {
            if ($record->mailcow_alias_id) {
                $mc = new MailcowService($record->realm);
                if ($mc->isConfigured()) $mc->deleteAlias($record->mailcow_alias_id);
            }
        } else {
            $base  = config('keycloak.base_url');
            $realm = $record->realm;
            $token = session('access_token');

            if ($record->keycloak_id) {
                \Http::withToken($token)->delete("{$base}/admin/realms/{$realm}/groups/{$record->keycloak_id}");
            }
            if ($record->nextcloud_id) {
                $nc = new NextcloudService($realm);
                if ($nc->isConfigured()) $nc->deleteGroup($record->nextcloud_id);
            }
        }

        AuditLogger::log('group.deleted', $record->name);
    }
}
