<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(string $action, ?string $detail = null): void
    {
        AuditLog::create([
            'app'      => 'dash',
            'username' => session('user_email', 'unknown'),
            'action'   => $action,
            'realm'    => session('realm'),
            'detail'   => $detail,
        ]);
    }
}
