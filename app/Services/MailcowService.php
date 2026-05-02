<?php

namespace App\Services;

use App\Models\RealmConfig;
use App\Models\Setting;

class MailcowService
{
    private ?string $baseUrl;
    private ?string $apiKey;

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

    public function createAlias(string $address, array $gotos): ?int
    {
        $res = \Http::withHeaders($this->headers())->post($this->url('add/alias'), [
            'address' => $address,
            'goto'    => implode(',', $gotos),
            'active'  => 1,
        ]);

        if ($res->failed() || ($res->json()[0]['type'] ?? '') !== 'success') {
            return null;
        }

        $all = \Http::withHeaders($this->headers())->get($this->url('get/alias/all'))->json();
        $alias = collect($all)->firstWhere('address', $address);

        return $alias ? (int) $alias['id'] : null;
    }

    public function updateAlias(int $aliasId, array $gotos): bool
    {
        $res = \Http::withHeaders($this->headers())->post($this->url('edit/alias'), [[
            'items' => [$aliasId],
            'attr'  => ['goto' => implode(',', $gotos), 'active' => 1],
        ]]);

        return ($res->json()[0]['type'] ?? '') === 'success';
    }

    public function deleteAlias(int $aliasId): bool
    {
        $res = \Http::withHeaders($this->headers())->post($this->url('delete/alias'), [$aliasId]);

        return ($res->json()[0]['type'] ?? '') === 'success';
    }
}
