<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'realm', 'name', 'slug', 'type', 'email',
        'mailcow_alias_id', 'keycloak_id', 'nextcloud_id',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
