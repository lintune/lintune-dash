<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class KumaService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('KUMA_INTERNAL_URL', 'http://uptime-kuma:3001'), '/');
        $this->apiKey  = Setting::get('kuma.api_key', '');
    }

    public function getStatus(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey),
            ])->timeout(10)->get($this->baseUrl . '/api/lintune/monitors');

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
