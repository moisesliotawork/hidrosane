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
use App\Models\AnotacionVisita;
use App\Models\AbsentHistory;
use App\Models\NoteNullReason;
use App\Models\NoteSalaObservation;
use App\Models\NoteConfirmation;
use Filament\Forms\Components\Select;
use App\Models\CreamDailyControl;
use App\Models\NoteSalaEvent;
use App\Models\User;
use App\Filament\Commercial\Resources\NoteResource;

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

                    // 3.1) Crear Anotacion de Visita para que aparezca en el PDF
                    AnotacionVisita::create([
                        'nota_id' => $this->record->id,
                        'author_id' => auth()->id(),
                        'asunto' => 'AUSENTE',
                        'cuerpo' => $data['observacion'] ?? 'Marcado como AUSENTE',
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
                    Forms\Components\Section::make('¿Estás en pareja con otro compañero?')
                        ->schema([
                            Select::make('companion_id')
                                ->label('Compañero')
                                ->native(false)
                                ->searchable()
                                ->required()
                                ->placeholder('Selecciona una opción')
                                ->options(
                                    fn() => ['__NONE__' => 'SIN COMPAÑERO']
                                    + User::role(['commercial', 'team_leader', 'sales_manager'])
                                        ->whereKeyNot(auth()->id())
                                        ->whereNull('baja')
                                        ->select('id', 'empleado_id', 'name', 'last_name')
                                        ->orderBy('name')
                                        ->distinct()
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all()
                                ),
                        ]),

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

                    // '__NONE__' => null
                    $rawCompanionId = $data['companion_id'] ?? null;
                    $companionId = $rawCompanionId === '__NONE__' ? null : $rawCompanionId;

                    DB::transaction(function () use ($data, $companionId) {

                        // 1) Guardar motivo + compañero
                        $nullReason = NoteNullReason::create([
                            'note_id' => $this->record->id,
                            'comercial_id' => Auth::id(),
                            'companion_id' => $companionId,   // ✅ NUEVO
                            'reason' => $data['reason'],
                        ]);

                        // 2) Cambiar estado
                        $this->record->estado_terminal = EstadoTerminal::NUL;
                        $this->record->reten = false;
                        $this->record->save();

                        DB::afterCommit(function () use ($nullReason) {
                            $note = $this->record->fresh(['customer', 'comercial']);

                            event(new \App\Events\NotaNula(
                                $note,
                                $nullReason->fresh()
                            ));
                        });
                    });

                    Notification::make()
                        ->title('Nota marcada como NULA')
                        ->body('Se guardó el motivo, el compañero y se actualizó el estado.')
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

                    Forms\Components\Section::make('¿Estás en pareja con otro compañero?')
                        ->schema([
                            Select::make('companion_id')
                                ->label('Compañero')
                                ->native(false)
                                ->searchable()
                                ->required()
                                ->placeholder('Selecciona una opción')
                                ->options(
                                    fn() => ['__NONE__' => 'SIN COMPAÑERO']
                                    + User::role(['commercial', 'team_leader', 'sales_manager'])
                                        ->whereKeyNot(auth()->id())
                                        ->whereNull('baja')
                                        ->select('id', 'empleado_id', 'name', 'last_name')
                                        ->orderBy('name')
                                        ->distinct()
                                        ->get()
                                        ->mapWithKeys(fn($u) => [
                                            $u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}",
                                        ])
                                        ->all()
                                ),
                        ]),

                    TextInput::make('phone1_commercial')
                        ->label('Teléfono Principal')
                        ->required()
                        ->default(fn() => $this->record->customer?->phone1_commercial),

                    TextInput::make('phone2_commercial')
                        ->label('Teléfono Secundario')
                        ->default(fn() => $this->record->customer?->phone2_commercial),

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

                    $dioCrema = (bool) ($data['dio_crema'] ?? false);

                    // Resolver companion_id: '__NONE__' => null
                    $rawCompanionId = $data['companion_id'] ?? null;
                    $companionId = $rawCompanionId === '__NONE__' ? null : $rawCompanionId;

                    // 1) Si marcó que SÍ entregó crema, primero verificamos stock
                    if ($dioCrema) {
                        $comercialId = $this->record->comercial_id ?? Auth::id();
                        $fechaStr = now()->toDateString();

                        // Obtenemos su control de hoy (sin tocar delivered)
                        $control = CreamDailyControl::firstOrCreate(
                            [
                                'comercial_id' => $comercialId,
                                'date' => $fechaStr,
                            ],
                            [
                                // valores por defecto SOLO si no existe
                                'assigned' => 8,
                                'delivered' => 0,
                                'received' => 0,
                                'donated' => 0,
                            ]
                        );

                        if ($control->remaining <= 0) {
                            Notification::make()
                                ->title('No tienes cremas disponibles')
                                ->body('Debes pedir una crema a otro comercial antes de marcar que la has entregado.')
                                ->warning()
                                ->send();

                            $url = NoteResource::getUrl('pedir-crema', [
                                'record' => $this->record,
                            ], panel: 'comercial');

                            $this->redirect($url);

                            return;
                        }

                    }

                    // 2️⃣ Flujo normal: sí tiene cremas (o marcó que no entregó crema)
                    DB::transaction(function () use ($data, $dioCrema, $companionId) {

                        if ($this->record->customer) {
                            $this->record->customer->update([
                                'phone1_commercial' => $data['phone1_commercial'] ?? $this->record->customer->phone1_commercial,
                                'phone2_commercial' => $data['phone2_commercial'] ?? $this->record->customer->phone2_commercial,
                            ]);
                        }

                        $confirmation = NoteConfirmation::create([
                            'note_id' => $this->record->id,
                            'author_id' => Auth::id(),
                            'companion_id' => $companionId,   // 👈 aquí guardamos null o el id real
                            'dio_crema' => $dioCrema,
                            'observation' => $data['observation'] ?? null,
                        ]);

                        $this->record->estado_terminal = EstadoTerminal::CONFIRMADO;
                        $this->record->save();

                        // Si SÍ entregó crema, restamos una de su control diario
                        if ($dioCrema) {
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
                                    'received' => 0,
                                    'donated' => 0,
                                ]
                            );

                            // Entregó una crema
                            $control->delivered++;
                            $control->save(); // booted recalcula remaining y next_day_to_assign
                        }

                        DB::afterCommit(function () use ($confirmation) {
                            $note = $this->record->fresh(['customer', 'comercial']);
                            event(new \App\Events\NotaConfirmada($note, $confirmation->fresh()));
                        });
                    });

                    Notification::make()
                        ->title('Nota marcada como CONFIRMADA')
                        ->success()
                        ->send();

                    // 3️⃣ Flujo normal: volvemos al listado
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
                        $salaObservation = NoteSalaObservation::create([
                            'note_id' => $this->record->id,
                            'author_id' => Auth::id(),
                            'observation' => $data['observation'],
                        ]);

                        // 2) Cambiar estado a SALA, registrar fecha/hora y RESET de printed
                        $this->record->forceFill([
                            'estado_terminal' => EstadoTerminal::SALA,
                            'sent_to_sala_at' => now(),
                            'printed' => false,
                            'reten' => false,
                        ])->save();

                        // 3) Crear registro en el historial de envíos a sala
                        //    vía 'declaracion' porque es el comercial pulsando el botón
                        NoteSalaEvent::create([
                            'note_id' => $this->record->id,
                            'sent_by_user_id' => Auth::id(),
                            'via' => 'declaracion',
                            'sent_at' => now(),
                        ]);

                        // 4) Evento para notificaciones u otros listeners
                        DB::afterCommit(function () use ($salaObservation) {
                            $note = $this->record->fresh(['customer', 'comercial']);

                            event(new \App\Events\NotaEnviadaAOficina(
                                $note,
                                $salaObservation->fresh()
                            ));
                        });
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
