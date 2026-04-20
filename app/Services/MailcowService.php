<?php

namespace App\Services;

use App\Models\RealmConfig;
use App\Models\Setting;

class MailcowService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $realm)
    {
        $this->baseUrl = rtrim(
            RealmConfig::get($realm, 'mailcow.url') ?? Setting::get('mailcow.url', config('mailcow.url')),
            '/'
        );
        $this->apiKey = RealmConfig::get($realm, 'mailcow.api_key')
            ?? Setting::get('mailcow.api_key', config('mailcow.api_key'));
    }

    public function headers(): array
    {
        return ['X-API-Key' => $this->apiKey, 'Accept' => 'application/json'];
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/api/v1/' . ltrim($path, '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }
}
