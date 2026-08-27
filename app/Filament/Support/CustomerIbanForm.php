<?php

namespace App\Filament\Support;

use App\Models\Customer;

class CustomerIbanForm
{
    public static function normalize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', $value));
    }

    public static function persist(?Customer $customer, ?string $iban): void
    {
        if (!$customer) {
            return;
        }

        $customer->update(['iban' => self::normalize($iban)]);
    }
}
