<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

class TenantSessionGuard implements Guard
{
    private ?TenantUser $resolvedUser = null;

    public function check(): bool
    {
        return (bool) session('access_token');
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if (! $this->check()) {
            return null;
        }

        if ($this->resolvedUser === null) {
            $this->resolvedUser = new TenantUser(
                realm:        session('realm', ''),
                name:         session('user_name', ''),
                isRealmAdmin: (bool) session('is_realm_admin'),
            );
        }

        return $this->resolvedUser;
    }

    public function id(): mixed
    {
        return session('realm');
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->resolvedUser !== null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->resolvedUser = $user instanceof TenantUser ? $user : null;
    }
}
