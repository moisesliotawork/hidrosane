<?php

namespace App\Casts;

use App\Support\Filament\FechaNacimientoField;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SafeDateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return FechaNacimientoField::parseStored($attributes[$key] ?? null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => FechaNacimientoField::normalizeForStorage($value)];
    }
}
