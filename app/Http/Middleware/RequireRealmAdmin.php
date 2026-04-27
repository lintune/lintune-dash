<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRealmAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('is_realm_admin')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
