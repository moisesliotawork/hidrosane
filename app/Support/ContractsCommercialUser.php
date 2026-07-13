<?php

namespace App\Support;

use App\Models\User;

/**
 * Comercial único (911 / contratos@gmail.com) con reglas especiales.
 */
class ContractsCommercialUser
{
    public static function matches(?User $user = null): bool
    {
        return ActionGps::isGpsExempt($user);
    }
}
