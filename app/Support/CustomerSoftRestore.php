<?php

namespace App\Support;

use App\Models\Customer;

final class CustomerSoftRestore
{
    public static function restore(Customer $customer): void
    {
        $customer->restore();

        $customer->forceFill([
            'deleted_by_user_id' => null,
        ])->saveQuietly();
    }
}
