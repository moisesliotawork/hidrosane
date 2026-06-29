<?php

namespace App\Support\Filament;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;

class FechaNacimientoField
{
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
                if (blank($state)) {
                    return null;
                }

                return Carbon::createFromFormat('d/m/Y', trim($state))->format('Y-m-d');
            })
            ->formatStateUsing(fn ($state) => filled($state)
                ? Carbon::parse($state)->format('d/m/Y')
                : null)
            ->afterStateHydrated(function ($state, Set $set): void {
                $set('age', filled($state) ? Carbon::parse($state)->age : null);
            })
            ->afterStateUpdated(function ($state, Set $set): void {
                if (blank($state)) {
                    $set('age', null);

                    return;
                }

                try {
                    $set('age', Carbon::createFromFormat('d/m/Y', trim((string) $state))->age);
                } catch (\Throwable) {
                    $set('age', null);
                }
            });

        if ($configure) {
            $configure($field);
        }

        return $field;
    }
}
