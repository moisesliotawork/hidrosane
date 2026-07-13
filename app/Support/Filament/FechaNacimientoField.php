<?php

namespace App\Support\Filament;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;

class FechaNacimientoField
{
    public const MIN_YEAR = 1936;

    public static function minDate(): Carbon
    {
        return Carbon::create(self::MIN_YEAR, 1, 1)->startOfDay();
    }

    public static function parse(mixed $value): ?Carbon
    {
        return self::parseRaw($value);
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $stringValue = trim((string) $value);
        $date = self::parseRaw($value);

        if ($date === null || $date->isFuture()) {
            return null;
        }

        // Restricción desde 1936 solo al introducir fecha nueva en formulario (dd/mm/aaaa).
        // Los valores ya guardados en BD (Y-m-d) se conservan sin revisión retroactiva.
        if (
            preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $stringValue)
            && $date->year < self::MIN_YEAR
        ) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /** @return array<int, mixed> */
    public static function validationRules(bool $required = true): array
    {
        $rules = [];

        if ($required) {
            $rules[] = 'required';
        }

        $rules[] = function () {
            return function (string $attribute, $value, Closure $fail): void {
                self::validateFormValue($value, $fail);
            };
        };

        return $rules;
    }

    public static function validateFormValue(mixed $value, Closure $fail): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            $date = $value;
        } elseif ($value instanceof \DateTimeInterface) {
            $date = Carbon::instance(\DateTime::createFromInterface($value));
        } else {
            $value = trim((string) $value);

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $value);
                    if (! $date || $date->format('d/m/Y') !== $value) {
                        $fail('La fecha de nacimiento no es válida.');

                        return null;
                    }
                } catch (\Throwable) {
                    $fail('La fecha de nacimiento no es válida.');

                    return null;
                }
            } else {
                try {
                    $date = Carbon::parse($value);
                } catch (\Throwable) {
                    $fail('La fecha de nacimiento no es válida.');

                    return null;
                }
            }
        }

        if ($date->year < self::MIN_YEAR) {
            $fail('La fecha de nacimiento no puede ser anterior a '.self::MIN_YEAR.'.');

            return null;
        }

        if ($date->isFuture()) {
            $fail('La fecha de nacimiento no puede ser futura.');

            return null;
        }

        return $date;
    }

    public static function configureDatePicker(DatePicker $field, bool $required = true): DatePicker
    {
        $field = $field
            ->minDate(self::minDate())
            ->maxDate(now())
            ->rules(self::validationRules($required));

        if ($required) {
            $field->required();
        }

        return $field
            ->afterStateHydrated(function ($state, Set $set): void {
                $date = self::parse($state);
                $set('age', $date?->age);
            })
            ->afterStateUpdated(function ($state, Set $set): void {
                $date = self::parse($state);
                $set('age', $date?->age);
            });
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
            ->rules(self::validationRules())
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

    private static function parseRaw(mixed $value): ?Carbon
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
}
