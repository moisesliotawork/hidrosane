<?php

namespace App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;

use App\Filament\Commercial\Resources\HistoricoContratosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditVenta extends EditRecord
{
    protected static string $resource = HistoricoContratosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function mount($record): void
    {
        parent::mount($record);

        $venta = $this->getRecord();

        $permitido = $venta->fecha_venta
            && now()->lessThanOrEqualTo($venta->fecha_venta->copy()->setTime(23, 0, 0));

        if (!$permitido) {
            Notification::make()
                ->danger()
                ->title('Edición no permitida')
                ->body('Solo puedes editar hasta las 23:00 del mismo día de la declaración.')
                ->send();

            $this->redirect(HistoricoContratosResource::getUrl('index'));
        }
    }
}
