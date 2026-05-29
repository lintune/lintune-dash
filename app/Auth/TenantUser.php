<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class TenantUser implements Authenticatable
{
    public function __construct(
        public readonly string $realm,
        public readonly string $name,
        public readonly bool   $isRealmAdmin,
    ) {}

    public function getAuthIdentifierName(): string  { return 'realm'; }
    public function getAuthIdentifier(): mixed        { return $this->realm; }
    public function getAuthPasswordName(): string     { return 'password'; }
    public function getAuthPassword(): string         { return ''; }
    public function getRememberToken(): ?string       { return null; }
    public function setRememberToken($value): void    {}
    public function getRememberTokenName(): string    { return ''; }
}
