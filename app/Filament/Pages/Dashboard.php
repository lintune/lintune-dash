<?php

namespace App\Filament\Pages;

use BackedEnum;

use App\Filament\Widgets\UserStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?int    $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            UserStatsWidget::class,
        ];
    }
}
