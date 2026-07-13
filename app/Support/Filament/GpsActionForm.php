<?php

namespace App\Support\Filament;

use App\Support\ActionGps;
use Filament\Actions\Action;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Livewire\Livewire;

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
            // No usar ->required(): bloquea el submit con error oculto si el GPS
            // ya está en la nota (venta normal) o aún no llegó del navegador.
            Forms\Components\Hidden::make('gps_lat')->dehydrated(),
            Forms\Components\Hidden::make('gps_lng')->dehydrated(),
            Forms\Components\View::make('filament.commercial.components.gps-capture-venta-wizard'),
        ];
    }

    /**
     * @param  callable(): bool|null  $isReady
     */
    public static function applyToVentaWizardCreateAction(Action $action, ?callable $isReady = null): Action
    {
        if (! ActionGps::shouldRegisterGps()) {
            return $action;
        }

        return $action
            ->disabled(function () use ($isReady): bool {
                if ($isReady !== null && $isReady()) {
                    return false;
                }

                return ! self::gpsReadyOnCurrentComponent();
            })
            ->tooltip(function () use ($isReady): ?string {
                $ready = ($isReady !== null && $isReady()) || self::gpsReadyOnCurrentComponent();

                return $ready ? null : 'Esperando ubicación GPS…';
            });
    }

    public static function requireGpsBeforeSubmit(StaticAction $action): StaticAction
    {
        if (! ActionGps::shouldRegisterGps()) {
            return $action;
        }

        return $action
            ->disabled(fn (): bool => ! self::gpsReadyOnCurrentComponent())
            ->tooltip(fn (): ?string => self::gpsReadyOnCurrentComponent()
                ? null
                : 'Esperando ubicación GPS…');
    }

    public static function applyToCreateAction(Action $action): Action
    {
        if (! ActionGps::shouldRegisterGps()) {
            return $action;
        }

        return $action
            ->disabled(fn (): bool => ! self::gpsReadyOnCurrentComponent())
            ->tooltip(fn (): ?string => self::gpsReadyOnCurrentComponent()
                ? null
                : 'Esperando ubicación GPS…');
    }

    /**
     * StaticAction (botón del modal) no puede inyectar $livewire en closures;
     * resolvemos el componente activo con Livewire::current().
     */
    public static function gpsReadyOnCurrentComponent(): bool
    {
        if (! ActionGps::shouldRegisterGps()) {
            return true;
        }

        $livewire = Livewire::current();

        if ($livewire === null) {
            return false;
        }

        if (is_array($livewire->data ?? null) && self::gpsReadyOnForm($livewire->data)) {
            return true;
        }

        return self::gpsReadyOnLivewire($livewire);
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
