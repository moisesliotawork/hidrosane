<?php

namespace App\Support\Filament;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;

class FechaNacimientoField
{
    public static function parse(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                $date = Carbon::createFromFormat('d/m/Y', $value);

                return ($date && $date->format('d/m/Y') === $value) ? $date : null;
            }

            $date = Carbon::parse($value);

            return $date instanceof Carbon ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        return self::parse($value)?->format('Y-m-d');
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
                function () {
                    return function (string $attribute, $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        $value = trim((string) $value);

                        if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                            $fail('Usa el formato dd/mm/aaaa (ej. 04/05/1949).');

                            return;
                        }

                        $date = self::parse($value);

                        if ($date === null) {
                            $fail('La fecha de nacimiento no es válida.');

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
            ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeForStorage($state))
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
