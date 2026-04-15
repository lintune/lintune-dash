<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    public function index()
    {
        $realm = session('realm');
        $token = session('access_token');
        $base  = config('keycloak.base_url');

        try {
            $users = \Http::withToken($token)
                ->get("{$base}/admin/realms/{$realm}/users", ['max' => 1000])
                ->json();
        } catch (\Throwable) {
            $users = null;
        }

        return view('users.index', compact('users'));
    }
}
