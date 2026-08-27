<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Illuminate\Validation\ValidationException;

class CustomerPhoneForm
{
    public const MASK = '999 999 999';

    public const PLACEHOLDER = '999 999 999';

    public const CLIENT_FIELDS = [
        'phone',
        'secondary_phone',
        'third_phone',
    ];

    public static function normalizeDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    public static function formatMask(?string $value): ?string
    {
        $digits = self::normalizeDigits($value);

        if ($digits === null || strlen($digits) !== 9) {
            return $value ?: null;
        }

        return implode(' ', str_split($digits, 3));
    }

    public static function nineDigitValidationRule(bool $allowEmpty = false): \Closure
    {
        return function () use ($allowEmpty) {
            return function (string $attribute, $value, \Closure $fail) use ($allowEmpty) {
                if ($value === null || trim((string) $value) === '') {
                    if (! $allowEmpty) {
                        $fail('Debe tener exactamente 9 cifras.');
                    }

                    return;
                }

                $digits = preg_replace('/\D+/', '', (string) $value);

                if (strlen($digits) !== 9) {
                    $fail('Debe tener exactamente 9 cifras.');
                }
            };
        };
    }

    public static function make(string $name, ?string $label = null, bool $required = false, bool $strictDigits = true): TextInput
    {
        $defaultLabels = [
            'phone' => 'Teléfono',
            'secondary_phone' => 'Teléfono 2',
            'third_phone' => 'Teléfono 3',
        ];

        $allowEmpty = ! $required;

        $field = TextInput::make($name)
            ->tel()
            ->placeholder(self::PLACEHOLDER)
            ->label($label ?? $defaultLabels[$name] ?? 'Teléfono')
            ->formatStateUsing(fn ($state) => self::formatMask($state) ?? $state)
            ->dehydrateStateUsing(function ($state) {
                $digits = self::normalizeDigits($state);

                return $digits;
            })
            ->dehydrated(true);

        if ($strictDigits) {
            $field
                ->mask(self::MASK)
                ->maxLength(11)
                ->rule(self::nineDigitValidationRule($allowEmpty))
                ->validationMessages([
                    'required' => 'Debe tener exactamente 9 cifras.',
                ]);
        } else {
            $field->maxLength(20);
        }

        if ($required) {
            $field->required();
        }

        return $field;
    }

    /**
     * @param  array<string, bool>  $fields
     * @return array<string, string|null>
     */
    public static function validateAndNormalizeFields(array $data, array $fields): array
    {
        $normalized = [];
        $errors = [];

        foreach ($fields as $field => $required) {
            $digits = self::normalizeDigits($data[$field] ?? null);

            if ($required && $digits === null) {
                $errors[$field] = 'Debe tener exactamente 9 cifras.';
                continue;
            }

            if ($digits !== null && strlen($digits) !== 9) {
                $errors[$field] = 'Debe tener exactamente 9 cifras.';
                continue;
            }

            $normalized[$field] = $digits;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public static function applyTo(TextInput $field, bool $required = false): TextInput
    {
        $allowEmpty = ! $required;

        $field
            ->tel()
            ->mask(self::MASK)
            ->placeholder(self::PLACEHOLDER)
            ->maxLength(11)
            ->formatStateUsing(fn ($state) => self::formatMask($state) ?? $state)
            ->rule(self::nineDigitValidationRule($allowEmpty))
            ->dehydrateStateUsing(function ($state) {
                return self::normalizeDigits($state);
            })
            ->dehydrated(true)
            ->validationMessages([
                'required' => 'Debe tener exactamente 9 cifras.',
            ]);

        if ($required) {
            $field->required();
        }

        return $field;
    }
}
