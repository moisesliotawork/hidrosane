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
            ->modalDescription('El contrato se archivará y dejará de aparecer en Contratos. Podrá consultarse en Contratos borrados.')
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
