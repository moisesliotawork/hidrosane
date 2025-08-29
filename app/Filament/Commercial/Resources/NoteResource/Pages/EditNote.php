<?php

namespace App\Filament\Commercial\Resources\NoteResource\Pages;

use App\Filament\Commercial\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Observation;
use Filament\Notifications\Notification;
use App\Enums\EstadoTerminal;
use App\Filament\Commercial\Resources\VentaResource;
use Carbon\Carbon;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;

    public function getTitle(): string
    {
        return 'Nro de Nota: ' . $this->record->nro_nota;
    }

    protected function getHeaderActions(): array
    {
        return [
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

                    Notification::make()
                        ->title('Nota marcada como NULO')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
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

                    Notification::make()
                        ->title('Nota marcada como CONFIRMADA')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('venta')
                ->label('Venta')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como VENTA?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {

                    Notification::make()
                        ->title('Nota marcada como VENTA')
                        ->success()
                        ->send();

                    $url = VentaResource::getUrl(
                        'create',
                        ['note' => $this->record->id],
                        panel: 'comercial'
                    );

                    $this->redirect($url);
                }),

            Actions\Action::make('sala')
                ->label('Sala')
                ->color('pink')
                ->requiresConfirmation()
                ->modalHeading('Confirmar acción')
                ->modalDescription('¿Estás seguro de marcar esta nota como SALA?')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function () {
                    $this->record->estado_terminal = EstadoTerminal::SALA;
                    $this->record->save();

                    Notification::make()
                        ->title('Nota marcada como SALA')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $note = $this->record;
        $customer = $note->customer;

        // Observaciones existentes
        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'id' => $observation->id,
                'author_id' => $observation->author_id,
                'observation' => $observation->observation,
            ];
        })->toArray();

        // Fecha de nacimiento y edad calculada para mostrar en el form
        $fechaNac = $customer->fecha_nac; // si tienes $casts, será Carbon|null
        $fechaNacStr = $fechaNac
            ? ($fechaNac instanceof \Carbon\Carbon ? $fechaNac->toDateString() : (string) $fechaNac)
            : null;

        $computedAge = null;
        if ($fechaNacStr) {
            try {
                $computedAge = Carbon::parse($fechaNacStr)->age;
            } catch (\Throwable $e) {
                $computedAge = $customer->age; // fallback
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
            'postal_code_id' => $customer->postal_code_id,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'parish' => $customer->parish,

            'ayuntamiento' => $customer->ayuntamiento,

            'fecha_nac' => $fechaNacStr,
            'age' => $computedAge,

            'observations' => $observations,
        ]);

    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // En el panel Commercial NO persistimos fecha_nac ni age
         unset($data['fecha_nac'], $data['age'], $data['ayuntamiento']);
        return $data;
    }

    protected function afterSave(): void
    {
        $currentObservationIds = [];
        $observations = $this->data['observations'] ?? [];

        foreach ($observations as $observationData) {
            if (empty($observationData['observation'])) {
                continue;
            }

            if (isset($observationData['id'])) {
                $observation = $this->record->observations()->find($observationData['id']);
                if ($observation) {
                    $observation->update([
                        'observation' => $observationData['observation'],
                    ]);
                    $currentObservationIds[] = $observation->id;
                }
            } else {
                $newObservation = $this->record->observations()->create([
                    'author_id' => auth()->id(),
                    'observation' => $observationData['observation'],
                ]);
                $currentObservationIds[] = $newObservation->id;
            }
        }

        $this->record->observations()
            ->whereNotIn('id', $currentObservationIds)
            ->delete();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
