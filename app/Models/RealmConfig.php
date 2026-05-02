<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealmConfig extends Model
{
    protected $table    = 'realm_config';
    protected $fillable = ['realm', 'key', 'value', 'encrypted'];

    public static function get(string $realm, string $key, mixed $default = null): mixed
    {
        $record = static::where('realm', $realm)->where('key', $key)->first();
        if (!$record) return $default;

        if ($record->encrypted) {
            return $record->value !== null ? decrypt($record->value) : null;
        }
        return $record->value;
    }
}
