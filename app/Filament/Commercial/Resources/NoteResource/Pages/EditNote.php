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
use App\Models\AbsentHistory;
use Illuminate\Support\Facades\App;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\DB;
use App\Models\NoteNullReason;
use Illuminate\Support\Facades\Auth;
use App\Models\NoteSalaObservation;
use App\Models\NoteConfirmation;
use Filament\Forms\Components\Select;
use App\Models\CreamDailyControl;


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
            Actions\Action::make('ausente')
                ->label('Ausente')
                ->color('gray')
                ->icon('heroicon-o-user-minus')
                ->form([
                    Textarea::make('observacion')
                        ->label('Observación (opcional)')
                        ->placeholder('Escribe una observación si lo consideras necesario…')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Marcar como AUSENTE')
                ->modalDescription('Confirma que deseas marcar la nota como AUSENTE.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data) {
                    // 1) Cambiar estado
                    $this->record->estado_terminal = EstadoTerminal::AUSENTE;
                    $this->record->reten = false;
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

                    // 3) Crear historial (incluyendo la observación opcional)
                    AbsentHistory::create([
                        'note_id' => $this->record->id,
                        'fecha' => Carbon::now()->toDateString(),
                        'hora' => Carbon::now()->format('H:i:s'),
                        'latitud' => $lat,
                        'longitud' => $lng,
                        'observacion' => $data['observacion'] ?? null,
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
                // Tras completar el formulario, Filament mostrará el modal de confirmación:
                ->requiresConfirmation()
                ->modalHeading('Motivo de nulidad')
                ->modalDescription('Confirma que deseas marcar la nota como NULA con el motivo indicado.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data) {
                        // 1) Guardar el motivo en la nueva tabla
                        NoteNullReason::create([
                            'note_id' => $this->record->id,
                            'comercial_id' => Auth::id(),
                            'reason' => $data['reason'],
                        ]);

                        // 2) Cambiar el estado de la nota a NULO
                        $this->record->estado_terminal = EstadoTerminal::NUL;
                        $this->record->reten = false;
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
                ->icon('heroicon-o-check-circle')
                ->form([
                    Select::make('dio_crema')
                        ->label('¿Haz entregado crema?')
                        ->options([
                            1 => 'Sí',
                            0 => 'No',
                        ])
                        ->required()
                        ->native(false),
                    Textarea::make('observation')
                        ->label('Observación (opcional)')
                        ->placeholder('Escribe una observación (opcional)…')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Confirmar nota')
                ->modalDescription('Confirma que deseas marcar la nota como CONFIRMADA.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data) {


                    // 1️⃣ Guardado normal
                    DB::transaction(function () use ($data) {

                        $confirmation = NoteConfirmation::create([
                            'note_id' => $this->record->id,
                            'author_id' => Auth::id(),
                            'dio_crema' => (bool) ($data['dio_crema'] ?? false),
                            'observation' => $data['observation'] ?? null,
                        ]);

                        $this->record->estado_terminal = EstadoTerminal::CONFIRMADO;
                        $this->record->save();

                        if ($confirmation->dio_crema) {

                            $comercialId = $this->record->comercial_id ?? Auth::id();
                            $fechaStr = now()->toDateString();

                            $control = CreamDailyControl::firstOrCreate(
                                [
                                    'comercial_id' => $comercialId,
                                    'date' => $fechaStr,
                                ],
                                [
                                    'assigned' => 5,
                                    'delivered' => 0,
                                ]
                            );

                            $control->delivered++;
                            $control->save();
                        }
                    });

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
                        ->label('Observación de Oficina')
                        ->placeholder('Escribe la observación para enviar a oficina...')
                        ->rows(4)
                        ->required()
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalHeading('Marcar como OFICINA')
                ->modalDescription('Confirma que deseas marcar la nota como OFICINA y guardar la observación.')
                ->modalSubmitActionLabel('Sí, confirmar')
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        // 1) Guardar observación de sala
                        NoteSalaObservation::create([
                            'note_id' => $this->record->id,
                            'author_id' => Auth::id(),
                            'observation' => $data['observation'],
                        ]);

                        // 2) Cambiar estado a SALA, registrar fecha/hora y RESET de printed
                        $this->record->forceFill([
                            'estado_terminal' => EstadoTerminal::SALA, // o ->value si tu columna es string sin cast
                            'sent_to_sala_at' => now(),
                            'printed' => false,
                            'reten' => false,
                        ])->save();
                    });

                    Notification::make()
                        ->title('Nota marcada como OFICINA')
                        ->body('Se guardó la observación, se actualizó el estado y se reinició el indicador de impresión.')
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
            'postal_code' => $customer->postal_code,
            'ciudad' => $customer->ciudad,
            'nro_piso' => $customer->nro_piso,
            'provincia' => $customer->provincia,
            'primary_address' => $customer->primary_address,
            'secondary_address' => $customer->secondary_address,
            'parish' => $customer->parish,

            'ayuntamiento' => $customer->ayuntamiento,
            'edadTelOp' => $customer->edadTelOp,

            'observations' => $observations,
        ]);

    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // En el panel Commercial NO persistimos fecha_nac ni age
        unset($data['fecha_nac'], $data['age'], $data['ayuntamiento'], $data["edadTelOp"]);
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
