<?php

namespace App\Support\Filament;

use App\Models\Venta;
use App\Support\VentaReserva;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;

final class BorradosReservaColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('enviar_reserva')
            ->label('RESERVA')
            ->state('Enviar a RESERVA')
            ->badge()
            ->color('warning')
            ->alignCenter()
            ->action(
                Action::make('enviarReserva')
                    ->label('Enviar a RESERVA')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar a RESERVA')
                    ->modalDescription('El contrato saldrá de Contratos borrados y pasará a RESERVA. Seguirá siendo recuperable; no se borra de forma definitiva.')
                    ->modalSubmitActionLabel('Sí, enviar a RESERVA')
                    ->successNotificationTitle('Contrato enviado a RESERVA')
                    ->action(fn (Venta $record) => VentaReserva::move($record)),
            );
    }
}
