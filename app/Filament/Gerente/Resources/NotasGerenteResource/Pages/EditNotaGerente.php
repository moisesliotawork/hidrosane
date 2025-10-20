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

    /** ====== HYDRATE: rellenar campos que viven en customer ====== */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $note = $this->record;
        $customer = $note->customer;

        // Observaciones existentes para el repeater
        $observations = $note->observations()->get()->map(fn($o) => [
            'id' => $o->id,
            'author_id' => $o->author_id,
            'observation' => $o->observation,
        ])->toArray();

        // Edad (opcional)
        $fechaNac = $customer->fecha_nac;
        $fechaNacStr = $fechaNac
            ? ($fechaNac instanceof \Carbon\Carbon ? $fechaNac->toDateString() : (string) $fechaNac)
            : null;

        $computedAge = null;
        if ($fechaNacStr) {
            try {
                $computedAge = Carbon::parse($fechaNacStr)->age;
            } catch (\Throwable $e) {
                $computedAge = $customer->age;
            }
        } else {
            $computedAge = $customer->age;
        }

        return array_merge($data, [
            'first_names' => $customer->first_names,
            'last_names' => $customer->last_names,
            'phone' => $customer->phone,
            'secondary_phone' => $customer->secondary_phone,
            'email' => $customer->email,
            'postal_code' => $customer->postal_code,
            'ciudad' => $customer->ciudad,
            'provincia' => $customer->provincia,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'parish' => $customer->parish,
            'ayuntamiento' => $customer->ayuntamiento,
            'edadTelOp' => $customer->edadTelOp,
            'observations' => $observations,
        ]);
    }

    /** ====== CLEAN: no guardes campos del cliente dentro de notes ====== */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['fecha_nac'], $data['age'], $data['ayuntamiento'], $data['edadTelOp']);
        return $data;
    }

    /** ====== POST-SAVE: sincronizar observaciones del repeater ====== */
    protected function afterSave(): void
    {
        $currentObservationIds = [];
        $observations = $this->data['observations'] ?? [];

        foreach ($observations as $row) {
            if (empty($row['observation'])) {
                continue;
            }

            if (!empty($row['id'])) {
                $obs = $this->record->observations()->find($row['id']);
                if ($obs) {
                    $obs->update(['observation' => $row['observation']]);
                    $currentObservationIds[] = $obs->id;
                }
            } else {
                $new = $this->record->observations()->create([
                    'author_id' => auth()->id(),
                    'observation' => $row['observation'],
                ]);
                $currentObservationIds[] = $new->id;
            }
        }

        $this->record->observations()
            ->whereNotIn('id', $currentObservationIds)
            ->delete();
    }

    protected function getHeaderActions(): array
    {
        return [
            //Actions\Action::make('ausente')
            //    ->label('Ausente')
            //    ->color('gray')
            //    ->requiresConfirmation()
            //    ->modalHeading('Confirmar acción')
            //    ->modalDescription('¿Estás seguro de marcar esta nota como AUSENTE?')
            //    ->modalSubmitActionLabel('Sí, confirmar')
            //    ->action(function () {
            //        $this->record->estado_terminal = EstadoTerminal::AUSENTE;
            //        $this->record->save();
//
            //        // Ubicación (igual que en comercial)
            //        if (App::environment('local')) {
            //            $lat = '42.2405';
            //            $lng = '-8.7200';
            //        } else {
            //            $lat = request()->input('latitud') ?? ($this->record->dentro_latitude ?? null);
            //            $lng = request()->input('longitud') ?? ($this->record->dentro_longitude ?? null);
            //        }
//
            //        AbsentHistory::create([
            //            'note_id' => $this->record->id,
            //            'fecha' => Carbon::now()->toDateString(),
            //            'hora' => Carbon::now()->format('H:i:s'),
            //            'latitud' => $lat,
            //            'longitud' => $lng,
            //        ]);
//
            //        Notification::make()->title('Nota marcada como AUSENTE')->success()->send();
            //        $this->redirect($this->backToGerentePage());
            //    }),
//
            //Actions\Action::make('nulo')
            //    ->label('Nulo')
            //    ->color('danger')
            //    ->requiresConfirmation()
            //    ->modalHeading('Confirmar acción')
            //    ->modalDescription('¿Estás seguro de marcar esta nota como NULO?')
            //    ->modalSubmitActionLabel('Sí, confirmar')
            //    ->action(function () {
            //        $this->record->estado_terminal = EstadoTerminal::NUL;
            //        $this->record->save();
//
            //        Notification::make()->title('Nota marcada como NULO')->success()->send();
            //        $this->redirect($this->backToGerentePage());
            //    }),
//
            //Actions\Action::make('confirmada')
            //    ->label('Confirmada')
            //    ->color('orange')
            //    ->requiresConfirmation()
            //    ->modalHeading('Confirmar acción')
            //    ->modalDescription('¿Estás seguro de marcar esta nota como CONFIRMADA?')
            //    ->modalSubmitActionLabel('Sí, confirmar')
            //    ->action(function () {
            //        $this->record->estado_terminal = EstadoTerminal::CONFIRMADO;
            //        $this->record->save();
//
            //        Notification::make()->title('Nota marcada como CONFIRMADA')->success()->send();
            //        $this->redirect($this->backToGerentePage());
            //    }),
//
            //Actions\Action::make('venta')
            //    ->label('Venta')
            //    ->color('success')
            //    ->requiresConfirmation()
            //    ->modalHeading('Confirmar acción')
            //    ->modalDescription('¿Estás seguro de marcar esta nota como VENTA?')
            //    ->modalSubmitActionLabel('Sí, confirmar')
            //    ->action(function () {
            //        Notification::make()->title('Nota marcada como VENTA')->success()->send();
//
            //        // Si el flujo de ventas vive en el panel comercial, lo dejamos así:
            //        $url = VentaResource::getUrl(
            //            'create',
            //            ['note' => $this->record->id],
            //            panel: 'comercial'
            //        );
            //        $this->redirect($url);
            //    }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->backToGerentePage();
    }
}
