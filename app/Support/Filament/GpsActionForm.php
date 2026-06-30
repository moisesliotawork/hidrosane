<?php

namespace App\Support\Filament;

use App\Support\ActionGps;
use Filament\Actions\Action;
use Filament\Actions\StaticAction;
use Filament\Forms;

class GpsActionForm
{
    /** @return array<int, Forms\Components\Component> */
    public static function fields(): array
    {
        if (! ActionGps::shouldRegisterGps()) {
            return [];
        }

        return [
            Forms\Components\Hidden::make('gps_lat')
                ->required()
                ->dehydrated(),
            Forms\Components\Hidden::make('gps_lng')
                ->required()
                ->dehydrated(),
            Forms\Components\View::make('filament.commercial.components.gps-capture-action'),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    public static function ventaWizardFields(): array
    {
        if (! ActionGps::shouldRegisterGps()) {
            return [];
        }

        return [
            Forms\Components\Hidden::make('gps_lat')
                ->required()
                ->dehydrated(),
            Forms\Components\Hidden::make('gps_lng')
                ->required()
                ->dehydrated(),
            Forms\Components\View::make('filament.commercial.components.gps-capture-venta-wizard'),
        ];
    }

    public static function requireGpsBeforeSubmit(StaticAction $action): StaticAction
    {
        if (! ActionGps::shouldRegisterGps()) {
            return $action;
        }

        return $action
            ->disabled(fn (object $livewire): bool => ! self::gpsReadyOnLivewire($livewire))
            ->tooltip(fn (object $livewire): ?string => self::gpsReadyOnLivewire($livewire)
                ? null
                : 'Esperando ubicación GPS…');
    }

    public static function applyToCreateAction(Action $action): Action
    {
        if (! ActionGps::shouldRegisterGps()) {
            return $action;
        }

        return $action
            ->disabled(fn (object $livewire): bool => ! self::gpsReadyOnForm($livewire->data ?? []))
            ->tooltip(fn (object $livewire): ?string => self::gpsReadyOnForm($livewire->data ?? [])
                ? null
                : 'Esperando ubicación GPS…');
    }

    /** @param  array<string, mixed>  $data */
    public static function gpsReadyOnForm(array $data): bool
    {
        if (! ActionGps::shouldRegisterGps()) {
            return true;
        }

        return filled($data['gps_lat'] ?? null) && filled($data['gps_lng'] ?? null);
    }

    public static function gpsReadyOnLivewire(object $livewire): bool
    {
        if (! ActionGps::shouldRegisterGps()) {
            return true;
        }

        if (property_exists($livewire, 'mountedTableBulkAction')
            && filled($livewire->mountedTableBulkAction ?? null)
            && is_array($livewire->mountedTableBulkActionData ?? null)
        ) {
            if (self::gpsReadyOnForm($livewire->mountedTableBulkActionData)) {
                return true;
            }
        }

        if (! is_array($livewire->mountedActionsData ?? null)) {
            return false;
        }

        $key = array_key_last($livewire->mountedActionsData);
        if ($key === null) {
            return false;
        }

        $row = $livewire->mountedActionsData[$key] ?? null;

        return is_array($row) && self::gpsReadyOnForm($row);
    }
}
