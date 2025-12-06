<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\CreamTransfer;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class CreamTransferRequested extends Notification
{
    use Queueable;

    public function __construct(protected CreamTransfer $transfer)
    {
    }

    public function via(object $notifiable): array
    {
        // Solo base de datos
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Solicitud de transferencia de crema')
            ->body("{$this->transfer->fromComercial->name} te solicita {$this->transfer->amount} crema(s).")
            ->actions([
                Action::make('ver')
                    ->label('Ver solicitud')
                    ->url(route('cream-transfers.show', $this->transfer))
                    ->openUrlInNewTab(false)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage(); 
    }
}
