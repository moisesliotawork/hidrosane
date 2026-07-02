<?php

namespace App\Support\Filament;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;

class FechaNacimientoField
{
    public static function parse(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value);
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function make(string $name = 'fecha_nac', ?Closure $configure = null): TextInput
    {
        $field = TextInput::make($name)
            ->label('Fec. nac.')
            ->placeholder('dd/mm/aaaa')
            ->mask('99/99/9999')
            ->required()
            ->maxLength(10)
            ->live(onBlur: true)
            ->rules([
                'required',
                'date_format:d/m/Y',
                function () {
                    return function (string $attribute, $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        try {
                            $date = Carbon::createFromFormat('d/m/Y', trim((string) $value));
                        } catch (\Throwable) {
                            return;
                        }

                        if ($date->isFuture()) {
                            $fail('La fecha de nacimiento no puede ser futura.');
                        }
                    };
                },
            ])
            ->validationMessages([
                'date_format' => 'Usa el formato dd/mm/aaaa (ej. 04/05/1949).',
            ])
            ->dehydrateStateUsing(function (?string $state): ?string {
                $date = self::parse($state);

                return $date?->format('Y-m-d');
            })
            ->formatStateUsing(fn ($state) => ($date = self::parse($state)) !== null
                ? $date->format('d/m/Y')
                : null)
            ->afterStateHydrated(function ($state, Set $set): void {
                $date = self::parse($state);
                $set('age', $date?->age);
            })
            ->afterStateUpdated(function ($state, Set $set): void {
                $date = self::parse($state);
                $set('age', $date?->age);
            });

        if ($configure) {
            $configure($field);
        }

        return $field;
    }
}
