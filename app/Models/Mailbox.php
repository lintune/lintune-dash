<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mailbox extends Model
{
    protected $fillable = ['email', 'realm', 'active'];
    protected $casts    = ['active' => 'boolean'];
}
