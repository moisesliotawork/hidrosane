<?php

namespace App\Filament\HeadOfRoom\Resources\CustomerResource\Pages;

use App\Filament\HeadOfRoom\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\HeadOfRoom\Resources\CustomerResource\Widgets\CustomerNotesTable;
use App\Filament\HeadOfRoom\Resources\CustomerResource\Widgets\CustomerSalesTable;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function getTitle(): string
    {
        return 'Posición Global del Cliente';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_inhabilitado')
                ->label(fn() => '☠️ Cl_Inhabilitado')
                ->color(fn() => $this->record->inhabilitado ? 'danger' : 'gray')
                ->size(\Filament\Support\Enums\ActionSize::Large)
                ->requiresConfirmation()
                ->modalHeading(fn() => $this->record->inhabilitado ? '✅ Rehabilitar Cliente' : '☠️ Inhabilitar Cliente')
                ->modalDescription(fn() => $this->record->inhabilitado
                    ? '¿Estás seguro de que quieres volver a habilitar a este cliente? Podrá volver a ser contactado.'
                    : '¿Estás seguro? Este cliente no podrá ser contactado por teleoperadoras ni jefe de sala.')
                ->modalSubmitActionLabel(fn() => $this->record->inhabilitado ? 'Sí, rehabilitar' : 'Sí, inhabilitar')
                ->action(function () {
                    $this->record->update(['inhabilitado' => !$this->record->inhabilitado]);
                    $this->refreshFormData(['inhabilitado']);
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CustomerNotesTable::class,
            CustomerSalesTable::class,
        ];
    }
}
