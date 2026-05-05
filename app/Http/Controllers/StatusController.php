<?php

namespace App\Http\Controllers;

use App\Services\KumaService;
use Illuminate\Support\Facades\Cache;

class StatusController extends Controller
{
    public function index()
    {
        $statuses = Cache::remember('kuma.status.public', 30, function () {
            $all = (new KumaService())->getStatus();
            // Tenant dash only shows public-facing services, not admin-only (AIO)
            return array_values(array_filter($all, fn($m) => !$m['admin_only']));
        });

        return view('status', ['statuses' => $statuses]);
    }
}
