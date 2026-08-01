<?php

namespace App\Livewire\SuperAdmin;

use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Venta;
use Filament\Notifications\Notification;
use Livewire\Component;

class VerDatosContratoSearch extends Component
{
    public const SESSION_NRO = 'superadmin.ver_datos_contrato.nro';

    public const SESSION_OPEN = 'superadmin.ver_datos_contrato.open';

    public string $nro = '';

    public bool $open = true;

    public function mount(): void
    {
        $this->nro = (string) session(self::SESSION_NRO, '');
        $this->open = (bool) session(self::SESSION_OPEN, true);
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
        return view('livewire.superadmin.ver-datos-contrato-search');
    }
}
