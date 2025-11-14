<?php

namespace App\Filament\Commercial\Resources\MisSupervisionesResource\Pages;

use App\Filament\Commercial\Resources\MisSupervisionesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Enums\EstadoTerminal;
use App\Filament\Commercial\Resources\VentaResource;
use App\Models\AbsentHistory;
use App\Models\NoteNullReason;
use App\Models\NoteSalaObservation;

class EditMisSupervisiones extends EditRecord
{
    protected static string $resource = MisSupervisionesResource::class;

    public function getTitle(): string
    {
        return 'Nro de Nota: ' . $this->record->nro_nota;
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
                    // 1) Cambiar estado
                    $this->record->estado_terminal = EstadoTerminal::AUSENTE;
                    $this->record->save();

                    // 2) Resolver ubicación
                    if (App::environment('local')) {
                        $lat = '42.2405';
                        $lng = '-8.7200';
                    } else {
                        $lat = request()->input('latitud');
                        $lng = request()->input('longitud');

                        if (empty($lat) && property_exists($this->record, 'dentro_latitude')) {
                            $lat = $this->record->dentro_latitude;
                        }
                        if (empty($lng) && property_exists($this->record, 'dentro_longitude')) {
                            $lng = $this->record->dentro_longitude;
                        }
                    }

                    // 3) Crear historial
                    AbsentHistory::create([
                        'note_id' => $this->record->id,
                        'fecha' => Carbon::now()->toDateString(),
                        'hora' => Carbon::now()->format('H:i:s'),
                        'latitud' => $lat,
                        'longitud' => $lng,
                        'autor_id' => Auth::id(),
                    ]);

                    // 4) Notificación + redirect
                    Notification::make()
                        ->title('Nota marcada como AUSENTE')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('nulo')
                ->label('Nulo')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de nulidad')
                        ->placeholder('Describe por qué esta nota se declara NULA...')
                        ->rows(4)
                        ->required()
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Motivo de nulidad')
                ->modalDescription('Confirma que deseas marcar la nota como NULA con el motivo indicado.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data) {
                        NoteNullReason::create([
                            'note_id' => $this->record->id,
                            'comercial_id' => Auth::id(),
                            'reason' => $data['reason'],
                        ]);

                        $this->record->estado_terminal = EstadoTerminal::NUL; // ajusta si tu enum usa NULO/NUL
                        $this->record->save();
                    });

                    Notification::make()
                        ->title('Nota marcada como NULA')
                        ->body('Se guardó el motivo y se actualizó el estado.')
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
                ->label('Oficina')
                ->color('pink')
                ->icon('heroicon-o-building-office-2')
                ->form([
                    Textarea::make('observation')
                        ->label('Observación de sala')
                        ->placeholder('Escribe la observación para sala…')
                        ->rows(4)
                        ->required()
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Marcar como SALA')
                ->modalDescription('Confirma que deseas marcar la nota como SALA y guardar la observación.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        NoteSalaObservation::create([
                            'note_id' => $this->record->id,
                            'author_id' => Auth::id(),
                            'observation' => $data['observation'],
                        ]);

                        $this->record->estado_terminal = EstadoTerminal::SALA;
                        $this->record->save();
                    });

                    Notification::make()
                        ->title('Nota marcada como SALA')
                        ->body('Se guardó la observación y se actualizó el estado.')
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

        $observations = $note->observations()->get()->map(function ($observation) {
            return [
                'id' => $observation->id,
                'author_id' => $observation->author_id,
                'observation' => $observation->observation,
            ];
        })->toArray();

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
            'nro_piso' => $customer->nro_piso,
            'ayuntamiento' => $customer->ayuntamiento,
            'edadTelOp' => $customer->edadTelOp,

            'observations' => $observations,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['fecha_nac'], $data['age'], $data['ayuntamiento'], $data['edadTelOp']);
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
        return static::getResource()::getUrl('index');
    }
}
