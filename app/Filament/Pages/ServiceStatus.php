<?php

namespace App\Filament\Pages;

use BackedEnum;

use App\Services\KumaService;
use Filament\Pages\Page;

class ServiceStatus extends Page
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Service Status';
    protected static ?string $title           = 'Service Status';
    protected static ?int    $navigationSort  = 4;
    protected string $view            = 'filament.pages.service-status';

    public array $statuses = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        try {
            $kuma          = new KumaService();
            $this->statuses = $kuma->getStatus();
        } catch (\Throwable) {
            $this->statuses = [];
        }
    }
}
