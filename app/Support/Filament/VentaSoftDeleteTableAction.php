<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Models\Venta;
use App\Support\VentaSoftDelete;
use Filament\Tables\Actions\DeleteAction;

final class VentaSoftDeleteTableAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Eliminar contrato')
            ->modalDescription(function (Venta $record): string {
                $record->loadMissing('customer');

                $cliente = trim((string) ($record->customer?->name ?? ''));
                if ($cliente === '') {
                    $cliente = '—';
                }

                $fecha = $record->fecha_venta
                    ? $record->fecha_venta->timezone('Europe/Madrid')->format('d/m/Y')
                    : '—';

                $nro = trim((string) ($record->nro_contr_adm ?? ''));
                $nroLinea = $nro !== '' ? "Nº contrato: {$nro}. " : '';

                return "Cliente: {$cliente}. Fecha contrato: {$fecha}. {$nroLinea}"
                    .'El contrato se archivará y dejará de aparecer en Contratos. '
                    .'Podrá consultarse en Contratos borrados.';
            })
            ->successNotificationTitle('Contrato archivado')
            ->action(fn (Venta $record) => VentaSoftDelete::delete($record));
    }

    /** Papelera visible solo para Abby dentro del panel Admin. */
    public static function makeForAbbyOnly(): DeleteAction
    {
        return static::make()
            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAbby());
    }
}
