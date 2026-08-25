<?php

namespace App\Support\Filament;

use App\Models\Note;
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
            // No usar ->required(): el hidden falla en silencio si el GPS del
            // navegador tarda o si la nota ya tiene coordenadas (Confirmada / DENTRO).
            Forms\Components\Hidden::make('gps_lat')
                ->dehydrated()
                ->default(fn ($livewire = null) => self::fillFromNote(
                    is_object($livewire) ? ($livewire->record ?? null) : null
                )['gps_lat'] ?? null),
            Forms\Components\Hidden::make('gps_lng')
                ->dehydrated()
                ->default(fn ($livewire = null) => self::fillFromNote(
                    is_object($livewire) ? ($livewire->record ?? null) : null
                )['gps_lng'] ?? null),
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

        if (isset($livewire->record) && self::fillFromNote($livewire->record) !== []) {
            return true;
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

    /**
     * GPS ya guardado en la nota (venta, confirmada, DENTRO) para no bloquear el modal.
     *
     * @return array{gps_lat: string, gps_lng: string}|array{}
     */
    public static function fillFromNote(mixed $note): array
    {
        if (! $note instanceof Note) {
            return [];
        }

        $validated = ActionGps::validateOperatingCoords($note->lat, $note->lng)
            ?? ActionGps::validateOperatingCoords($note->lat_dentro, $note->lng_dentro);

        if ($validated === null) {
            return [];
        }

        return [
            'gps_lat' => $validated['lat'],
            'gps_lng' => $validated['lng'],
        ];
    }
}
