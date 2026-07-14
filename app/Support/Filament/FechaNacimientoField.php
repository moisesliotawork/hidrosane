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

    /** Zona del DatePicker en formularios; solo se usa al guardar lo que el usuario eligió. */
    private const PICKER_TIMEZONE = 'Europe/Madrid';

    public static function minDate(): Carbon
    {
        return Carbon::createFromDate(self::MIN_YEAR, 1, 1)->startOfDay();
    }

    /**
     * Formatea el valor crudo de BD (Y-m-d) sin conversiones de zona horaria.
     * Lo que está en BD es lo que se muestra.
     */
    public static function formatDisplay(?string $stored, string $format = 'd/m/Y'): ?string
    {
        if (blank($stored)) {
            return null;
        }

        $stored = trim($stored);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $stored, $parts)) {
            return match ($format) {
                'd/m/Y' => "{$parts[3]}/{$parts[2]}/{$parts[1]}",
                'd-m-Y' => "{$parts[3]}-{$parts[2]}-{$parts[1]}",
                'Y-m-d' => $stored,
                default => self::parseStored($stored)?->format($format),
            };
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $stored, $parts)) {
            return match ($format) {
                'd/m/Y' => $stored,
                'd-m-Y' => "{$parts[1]}-{$parts[2]}-{$parts[3]}",
                'Y-m-d' => "{$parts[3]}-{$parts[2]}-{$parts[1]}",
                default => self::parseStored($stored)?->format($format),
            };
        }

        return null;
    }

    /** Parsea el valor almacenado en BD para cálculos (edad, validaciones). */
    public static function parseStored(?string $stored): ?Carbon
    {
        if (blank($stored)) {
            return null;
        }

        $stored = trim($stored);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $stored, $parts)) {
            $date = Carbon::createFromDate((int) $parts[1], (int) $parts[2], (int) $parts[3])->startOfDay();

            return $date->format('Y-m-d') === $stored ? $date : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $stored, $parts)) {
            $date = Carbon::createFromDate((int) $parts[3], (int) $parts[2], (int) $parts[1])->startOfDay();

            return $date->format('d/m/Y') === $stored ? $date : null;
        }

        return null;
    }

    public static function parse(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return self::parseStored(self::calendarDateFromPicker($value));
        }

        if (blank($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return self::parseStored($value);
        }

        return null;
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            $ymd = self::calendarDateFromPicker($value);
        } else {
            $stringValue = trim((string) $value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
                $ymd = self::parseStored($stringValue) ? $stringValue : null;
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $stringValue)) {
                $ymd = self::parseStored($stringValue)?->format('Y-m-d');
            } else {
                $ymd = null;
            }

            if ($ymd === null) {
                return null;
            }

            if ($ymd < self::MIN_YEAR.'-01-01' && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $stringValue)) {
                return null;
            }

            $date = self::parseStored($ymd);

            return ($date !== null && ! $date->isFuture()) ? $ymd : null;
        }

        if ($ymd === null) {
            return null;
        }

        $date = self::parseStored($ymd);

        if ($date === null || $date->isFuture()) {
            return null;
        }

        return $ymd;
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

        $date = self::parse($value);

        if ($date === null) {
            $fail('La fecha de nacimiento no es válida.');

            return null;
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
            ->rules(self::validationRules($required))
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeForStorage($state));

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
            ->formatStateUsing(fn ($state) => self::formatDisplay(
                is_string($state) ? $state : self::normalizeForStorage($state),
                'd/m/Y',
            ))
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

    /**
     * Extrae Y-m-d de un DatePicker respetando la fecha que eligió el usuario.
     * Solo aplica en el flujo de guardado, no al leer de BD.
     */
    private static function calendarDateFromPicker(Carbon|\DateTimeInterface $value): ?string
    {
        $date = $value instanceof Carbon
            ? $value->copy()
            : Carbon::instance(\DateTime::createFromInterface($value));

        return $date->timezone(self::PICKER_TIMEZONE)->format('Y-m-d');
    }
}
