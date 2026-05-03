<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KumaService
{
    private string $url;

    public function __construct()
    {
        $this->url = rtrim(env('KUMA_INTERNAL_URL', 'http://uptime-kuma:3001'), '/');
    }

    // Returns [{id, name, status, url, admin_only}]
    // status: 0=down, 1=up, 2=pending/unknown
    // admin_only: true for AIO monitors — excluded from tenant dash
    public function getStatus(string $apiKey): array
    {
        try {
            $res = Http::withHeaders(['Authorization' => "apikey {$apiKey}"])
                ->timeout(5)
                ->get("{$this->url}/api/monitors");

            if ($res->failed()) {
                return [];
            }

            $monitors = [];
            foreach ((array) $res->json('monitors') as $m) {
                if (empty($m['active'])) {
                    continue;
                }
                $monitors[] = [
                    'id'         => $m['id'],
                    'name'       => $m['name'],
                    'status'     => $m['status'] ?? 2,
                    'url'        => $m['url'] ?? '',
                    'admin_only' => str_contains(strtolower($m['name'] ?? ''), 'aio'),
                ];
            }
            return $monitors;
        } catch (\Throwable) {
            return [];
        }
    }
}
