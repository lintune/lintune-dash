<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public $incrementing  = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);
        if (!$setting) return $default;

        if ($setting->encrypted) {
            return $setting->value !== null ? decrypt($setting->value) : null;
        }
        return $setting->value;
    }
}
