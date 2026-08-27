<?php

namespace App\Support\Filament;

use App\Models\Customer;
use App\Support\CustomerSoftDelete;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Collection;

final class CustomerSoftDeleteTableAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Eliminar cliente')
            ->modalDescription('El cliente se archivará y dejará de aparecer en listados. Podrá recuperarse en Clientes borrados. Sus notas, contratos y datos relacionados se conservan.')
            ->successNotificationTitle('Cliente archivado')
            ->action(fn (Customer $record) => CustomerSoftDelete::delete($record));
    }

    public static function bulk(): \Filament\Tables\Actions\DeleteBulkAction
    {
        return \Filament\Tables\Actions\DeleteBulkAction::make()
            ->label('Eliminar seleccionados')
            ->modalHeading('Eliminar clientes seleccionados')
            ->modalDescription('Los clientes se archivarán. Podrán recuperarse en Clientes borrados. Sus notas, contratos y datos relacionados se conservan.')
            ->modalSubmitActionLabel('Sí, archivar')
            ->successNotificationTitle('Clientes archivados')
            ->action(function (Collection $records): void {
                foreach ($records as $record) {
                    if ($record instanceof Customer) {
                        CustomerSoftDelete::delete($record);
                    }
                }
            });
    }
}
