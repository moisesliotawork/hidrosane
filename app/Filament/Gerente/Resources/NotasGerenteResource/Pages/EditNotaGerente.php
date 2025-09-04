<?php

namespace App\Filament\Gerente\Resources\NotasGerenteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Gerente\Resources\NotasGerenteResource;
use App\Filament\Gerente\Pages\NotasDeComercial;
use App\Filament\Commercial\Resources\VentaResource;
use App\Enums\EstadoTerminal;
use App\Models\AbsentHistory;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;

class EditNotaGerente extends EditRecord
{
    protected static string $resource = NotasGerenteResource::class;

    public function getTitle(): string
    {
        return 'Nro de Nota: ' . $this->record->nro_nota;
    }

    protected function backToGerentePage(): string
    {
        return NotasDeComercial::getUrl(
            ['comercial_id' => $this->record->comercial_id],
            panel: 'gerente'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ausente')
                ->label('Ausente')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como AUSENTE?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {
                    $this->record->estado_terminal = EstadoTerminal::AUSENTE;
                    $this->record->save();

                    // Ubicación (igual que en comercial)
                    if (App::environment('local')) {
                        $lat = '42.2405';
                        $lng = '-8.7200';
                    } else {
                        $lat = request()->input('latitud') ?? ($this->record->dentro_latitude ?? null);
                        $lng = request()->input('longitud') ?? ($this->record->dentro_longitude ?? null);
                    }

                    AbsentHistory::create([
                        'note_id' => $this->record->id,
                        'fecha' => Carbon::now()->toDateString(),
                        'hora' => Carbon::now()->format('H:i:s'),
                        'latitud' => $lat,
                        'longitud' => $lng,
                    ]);

                    Notification::make()->title('Nota marcada como AUSENTE')->success()->send();
                    $this->redirect($this->backToGerentePage());
                }),

            Actions\Action::make('nulo')
                ->label('Nulo')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como NULO?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {
                    $this->record->estado_terminal = EstadoTerminal::NUL;
                    $this->record->save();

                    Notification::make()->title('Nota marcada como NULO')->success()->send();
                    $this->redirect($this->backToGerentePage());
                }),

            Actions\Action::make('confirmada')
                ->label('Confirmada')
                ->color('orange')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como CONFIRMADA?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {
                    $this->record->estado_terminal = EstadoTerminal::CONFIRMADO;
                    $this->record->save();

                    Notification::make()->title('Nota marcada como CONFIRMADA')->success()->send();
                    $this->redirect($this->backToGerentePage());
                }),

            Actions\Action::make('venta')
                ->label('Venta')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como VENTA?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {
                    Notification::make()->title('Nota marcada como VENTA')->success()->send();

                    // Si el flujo de ventas vive en el panel comercial, lo dejamos así:
                    $url = VentaResource::getUrl(
                        'create',
                        ['note' => $this->record->id],
                        panel: 'comercial'
                    );
                    $this->redirect($url);
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->backToGerentePage();
    }
}
