<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        $realm = session('realm');
        $token = session('access_token');
        $base  = config('keycloak.base_url');

        $stats = [];

        try {
            $users = \Http::withToken($token)
                ->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])
                ->json();

            $stats['total_users']    = count($users);
            $stats['active_users']   = count(array_filter($users, fn($u) => $u['enabled'] ?? false));
            $stats['disabled_users'] = $stats['total_users'] - $stats['active_users'];
        } catch (\Throwable) {
            $stats = null;
        }

        return view('dashboard', compact('stats'));
    }
}
