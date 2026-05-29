<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $realm = session('realm');
        $token = session('access_token');
        $base  = config('keycloak.base_url');

        try {
            $users = \Http::withToken($token)
                ->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])
                ->json() ?? [];

            $total    = count($users);
            $active   = count(array_filter($users, fn($u) => $u['enabled'] ?? false));
            $disabled = $total - $active;
        } catch (\Throwable) {
            return [
                Stat::make('Users', 'Unavailable')->color('danger'),
            ];
        }

        return [
            Stat::make('Total Users', $total)
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Active', $active)
                ->icon('heroicon-o-user-circle')
                ->color('success'),
            Stat::make('Disabled', $disabled)
                ->icon('heroicon-o-user-minus')
                ->color('warning'),
        ];
    }
}
