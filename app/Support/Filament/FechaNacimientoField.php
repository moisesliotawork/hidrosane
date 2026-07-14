<?php

namespace App\Support\Filament;

use App\Models\Customer;
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
        return Carbon::createFromDate(self::MIN_YEAR, 1, 1)->startOfDay();
    }

    /** Normaliza cualquier valor de BD a Y-m-d sin zona horaria. */
    public static function normalizeStoredString(mixed $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        $stored = trim((string) $stored);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $stored, $parts)) {
            $year = (int) $parts[1];
            $month = (int) $parts[2];
            $day = (int) $parts[3];

            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $stored, $parts)) {
            $day = (int) $parts[1];
            $month = (int) $parts[2];
            $year = (int) $parts[3];

            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        return null;
    }

    /**
     * Formatea el valor crudo de BD (Y-m-d) sin conversiones de zona horaria.
     * Lo que está en BD es lo que se muestra.
     */
    public static function formatDisplay(?string $stored, string $format = 'd/m/Y'): ?string
    {
        $ymd = self::normalizeStoredString($stored);

        if ($ymd === null) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $ymd));

        return match ($format) {
            'd/m/Y' => sprintf('%02d/%02d/%04d', $day, $month, $year),
            'd-m-Y' => sprintf('%02d-%02d-%04d', $day, $month, $year),
            'Y-m-d' => $ymd,
            default => self::parseStored($ymd)?->format($format),
        };
    }

    /** Parsea el valor almacenado en BD para cálculos (edad, validaciones). */
    public static function parseStored(?string $stored): ?Carbon
    {
        $ymd = self::normalizeStoredString($stored);

        if ($ymd === null) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $ymd));

        return Carbon::createFromDate($year, $month, $day)->startOfDay();
    }

    /** Convierte cualquier estado de formulario a Y-m-d sin desplazamientos UTC. */
    public static function toStorageDateString(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_string($value)) {
            return self::normalizeStoredString($value);
        }

        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            $date = $value instanceof Carbon
                ? $value
                : Carbon::instance(\DateTime::createFromInterface($value));

            $ymd = sprintf(
                '%04d-%02d-%02d',
                (int) $date->year,
                (int) $date->month,
                (int) $date->day,
            );

            return self::normalizeStoredString($ymd);
        }

        return null;
    }

    public static function parse(mixed $value): ?Carbon
    {
        return self::parseStored(self::toStorageDateString($value));
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_string($value)) {
            $stringValue = trim($value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $stringValue)) {
                $ymd = self::normalizeStoredString($stringValue);

                if ($ymd === null) {
                    return null;
                }

                $date = self::parseStored($ymd);

                return ($date !== null && ! $date->isFuture()) ? $ymd : null;
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $stringValue)) {
                $ymd = self::normalizeStoredString($stringValue);

                if ($ymd === null) {
                    return null;
                }

                if ($ymd < self::MIN_YEAR.'-01-01') {
                    return null;
                }

                $date = self::parseStored($ymd);

                return ($date !== null && ! $date->isFuture()) ? $ymd : null;
            }
        }

        $ymd = self::toStorageDateString($value);

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
            ->native(false)
            ->format('Y-m-d')
            ->displayFormat('d/m/Y')
            ->minDate(self::minDate())
            ->maxDate(now())
            ->rules(self::validationRules($required))
            ->formatStateUsing(fn (mixed $state): ?string => self::toStorageDateString($state))
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

    /**
     * Al fusionar duplicados con el mismo DNI, unifica fecha_nac.
     * Si difieren, conserva la del registro actualizado más recientemente.
     */
    public static function resolveOnCustomerMerge(Customer $keeper, Customer $toDelete): ?string
    {
        $keeperDate = self::normalizeStoredString(self::rawFechaNacFromCustomer($keeper));
        $toDeleteDate = self::normalizeStoredString(self::rawFechaNacFromCustomer($toDelete));

        if ($keeperDate === null) {
            return $toDeleteDate;
        }

        if ($toDeleteDate === null) {
            return $keeperDate;
        }

        if ($keeperDate === $toDeleteDate) {
            return $keeperDate;
        }

        $keeperDni = mb_strtoupper(trim((string) ($keeper->dni ?? '')));
        $toDeleteDni = mb_strtoupper(trim((string) ($toDelete->dni ?? '')));

        if ($keeperDni === '' || $keeperDni !== $toDeleteDni) {
            return $keeperDate;
        }

        $keeperUpdatedAt = $keeper->updated_at?->getTimestamp() ?? 0;
        $toDeleteUpdatedAt = $toDelete->updated_at?->getTimestamp() ?? 0;

        return $toDeleteUpdatedAt >= $keeperUpdatedAt ? $toDeleteDate : $keeperDate;
    }

    protected static function rawFechaNacFromCustomer(Customer $customer): mixed
    {
        return $customer->getRawOriginal('fecha_nac')
            ?? ($customer->getAttributes()['fecha_nac'] ?? null);
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
                is_string($state) ? $state : self::toStorageDateString($state),
                'd/m/Y',
            ))
            ->afterStateHydrated(function (TextInput $component, $state, Set $set) use ($name): void {
                $formatted = self::formatDisplay(
                    is_string($state) ? $state : self::toStorageDateString($state),
                    'd/m/Y',
                );

                if ($formatted !== null && $formatted !== $state) {
                    $component->state($formatted);
                    $state = $formatted;
                }

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
