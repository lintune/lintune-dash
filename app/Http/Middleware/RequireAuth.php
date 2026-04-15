<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('access_token')) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
