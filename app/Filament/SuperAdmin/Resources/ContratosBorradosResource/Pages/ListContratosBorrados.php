<?php

namespace App\Filament\SuperAdmin\Resources\ContratosBorradosResource\Pages;

use App\Filament\SuperAdmin\Resources\ContratosBorradosResource;
use App\Models\Venta;
use App\Support\VentaReserva;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListContratosBorrados extends ListRecords
{
    protected static string $resource = ContratosBorradosResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviarTodosReserva')
                ->label('Enviar todos a RESERVA')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->visible(fn (): bool => Venta::onlyTrashed()->enContratosBorrados()->exists())
                ->requiresConfirmation()
                ->modalHeading('Enviar todos a RESERVA')
                ->modalDescription('Todos los contratos de Contratos borrados pasarán a RESERVA. Seguirán siendo recuperables; no se borra ninguno de forma definitiva.')
                ->modalSubmitActionLabel('Sí, enviar a RESERVA')
                ->action(function (): void {
                    $n = VentaReserva::moveAllFromBorrados();

                    Notification::make()
                        ->title($n === 1
                            ? '1 contrato enviado a RESERVA'
                            : "{$n} contratos enviados a RESERVA")
                        ->success()
                        ->send();
                }),
        ];
    }
}
