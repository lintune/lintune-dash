<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\KumaService;
use Illuminate\Support\Facades\Cache;

class StatusController extends Controller
{
    public function index()
    {
        $statuses = Cache::remember('kuma.status.public', 30, function () {
            $rawKey = Setting::get('kuma.api_key');
            if (!$rawKey) {
                return [];
            }
            $all = (new KumaService())->getStatus(decrypt($rawKey));
            // Tenant dash only shows public-facing services, not admin-only (AIO)
            return array_values(array_filter($all, fn($m) => !$m['admin_only']));
        });

        return view('status', ['statuses' => $statuses]);
    }
}
