<?php

namespace App\Livewire\SuperAdmin;

use App\Filament\SuperAdmin\Resources\ContratosBorradosResource;
use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Venta;
use App\Support\BorradosAlerta;
use App\Support\CustomerIdentityAlertaTracker;
use App\Support\VentaFechaCambioTracker;
use Filament\Notifications\Notification;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerDatosContratoSearch extends Component
{
    public const SESSION_NRO = 'superadmin.ver_datos_contrato.nro';

    public const SESSION_OPEN = 'superadmin.ver_datos_contrato.open';

    public string $nro = '';

    public bool $open = true;

    /** Contratos en «Contratos borrados» (soft-delete, aún no en reserva). */
    public int $borradosCount = 0;

    /** Cambios de fecha_venta pendientes de revisar. */
    public int $fechaCambiosCount = 0;

    /** Cambios de nombre o DNI que coinciden con otro cliente. */
    public int $identidadCount = 0;

    public function mount(): void
    {
        $this->nro = (string) session(self::SESSION_NRO, '');
        $this->open = (bool) session(self::SESSION_OPEN, true);
        $this->refreshAlerts();
    }

    public function refreshBorradosCount(): void
    {
        $this->refreshAlerts();
    }

    public function refreshAlerts(): void
    {
        $this->borradosCount = BorradosAlerta::pendingCount();
        $this->fechaCambiosCount = VentaFechaCambioTracker::pendingCount();
        $this->identidadCount = CustomerIdentityAlertaTracker::pendingCount();
    }

    public function updatedNro(mixed $value): void
    {
        $this->nro = trim((string) $value);
        session([self::SESSION_NRO => $this->nro]);
    }

    public function updatedOpen(mixed $value): void
    {
        $this->open = (bool) $value;
        session([self::SESSION_OPEN => $this->open]);
    }

    public function toggleOpen(): void
    {
        $this->open = ! $this->open;
        session([self::SESSION_OPEN => $this->open]);
    }

    public function descartarAlertaBorrados(): void
    {
        $n = BorradosAlerta::acknowledgePending();
        $this->refreshAlerts();

        Notification::make()
            ->title('Alerta descartada')
            ->body($n === 1
                ? '1 contrato sigue en Contratos borrados, sin aviso.'
                : "{$n} contratos siguen en Contratos borrados, sin aviso.")
            ->success()
            ->send();
    }

    public function descartarCambiosFecha(): void
    {
        $n = VentaFechaCambioTracker::acknowledgePending();
        $this->refreshAlerts();

        Notification::make()
            ->title('Cambios de fecha descartados')
            ->body($n === 1 ? '1 cambio marcado como revisado.' : "{$n} cambios marcados como revisados.")
            ->success()
            ->send();
    }

    public function descartarAlertaIdentidad(): void
    {
        $n = CustomerIdentityAlertaTracker::acknowledgePending();
        $this->refreshAlerts();

        Notification::make()
            ->title('Alerta de coincidencia descartada')
            ->body($n === 1
                ? '1 coincidencia marcada como revisada. Los contratos no se han fusionado.'
                : "{$n} coincidencias marcadas como revisadas. Los contratos no se han fusionado.")
            ->success()
            ->send();
    }

    public function descargarCambiosFecha(): StreamedResponse
    {
        return VentaFechaCambioTracker::downloadPendingCsv();
    }

    public function buscar(): void
    {
        $nro = trim($this->nro);
        session([self::SESSION_NRO => $nro]);

        if ($nro === '') {
            Notification::make()
                ->title('Indica un nº de contrato admin')
                ->warning()
                ->send();

            return;
        }

        $venta = Venta::query()
            ->withTrashed()
            ->where('nro_contr_adm', $nro)
            ->orderByDesc('id')
            ->first();

        if (! $venta) {
            Notification::make()
                ->title('Contrato no encontrado')
                ->body("No hay un contrato con nº admin «{$nro}».")
                ->danger()
                ->send();

            return;
        }

        $this->redirect(VentaResource::getUrl('edit', ['record' => $venta]));
    }

    public function render()
    {
        $fechaCambios = VentaFechaCambioTracker::pending();

        return view('livewire.superadmin.ver-datos-contrato-search', [
            'hasBorradosAlert' => $this->borradosCount > 0,
            'hasFechaCambiosAlert' => $this->fechaCambiosCount > 0,
            'hasIdentidadAlert' => $this->identidadCount > 0,
            'borradosUrl' => ContratosBorradosResource::getUrl('index'),
            'fechaCambios' => $fechaCambios,
            'identidadAlertas' => CustomerIdentityAlertaTracker::pending(),
        ]);
    }
}
