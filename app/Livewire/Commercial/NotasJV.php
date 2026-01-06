<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Filament\Notifications\Notification;
use App\Filament\Commercial\Resources\NoteResource;
use Illuminate\Support\Str;
use App\Enums\EstadoTerminal;
use App\Models\NoteSalaEvent;
use App\Enums\NoteStatus;
use Illuminate\Support\Facades\DB;
use App\Filament\Commercial\Resources\NoteJVResource;
use Illuminate\Validation\Rule;

class NotasJV extends Component
{
    public array $selectedNotes = [];
    public string $search = '';
    public ?string $statusFilter = null;
    public bool $confirmDeleteOpen = false;
    public ?int $noteIdToDelete = null;
    public ?int $noteIdToReassign = null;
    public ?int $reassignComercialId = null;
    public ?string $reassignAssignmentDate = null; // YYYY-MM-DD
    public ?string $assignmentStart = null;     // Desde (YYYY-MM-DD)
    public ?string $assignmentEnd = null;       // Hasta (YYYY-MM-DD)
    public ?string $assignmentExact = null;     // Fecha exacta de asignación (YYYY-MM-DD)
    public ?int $comercialFilterId = null;      // Comercial (id)
    public ?string $sentToSalaAt = null;        // Fecha exacta Sala (YYYY-MM-DD)

    public string $tab = 'todas'; // 'oficina' | 'se' | 'todas'

    protected $queryString = [
        'tab' => ['except' => 'todas'],
    ];

    protected $listeners = [
        'notaActualizada' => '$refresh',
    ];

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = null;

        $this->assignmentStart = null;
        $this->assignmentEnd = null;
        $this->assignmentExact = null;
        $this->comercialFilterId = null;
        $this->sentToSalaAt = null;
    }

    public function setTab(string $tab): void
    {
        $allowed = ['oficina', 'se', 'todas'];
        $this->tab = in_array($tab, $allowed, true) ? $tab : 'todas';

        // opcional: limpiar seleccionadas al cambiar tab
        $this->selectedNotes = [];
    }


    public function getStatusOptionsProperty(): array
    {
        // Si tu enum ya tiene options() como en Filament, úsalo:
        return NoteStatus::options();
    }

    public function getActiveFiltersCountProperty(): int
    {
        $count = 0;

        if (!empty(trim($this->search)))
            $count++;
        if (!empty($this->statusFilter))
            $count++;

        if (!empty($this->assignmentStart))
            $count++;
        if (!empty($this->assignmentEnd))
            $count++;
        if (!empty($this->assignmentExact))
            $count++;
        if (!empty($this->comercialFilterId))
            $count++;
        if (!empty($this->sentToSalaAt))
            $count++;

        return $count;
    }

    /**
     * IDs de comerciales permitidos (según rol).
     */
    protected function allowedComercialIds(): array
    {
        $user = auth()->user();

        // sales_manager: sin filtro por comercial
        if ($user->hasRole('sales_manager')) {
            return [];
        }

        $ids = collect([$user->id]);

        if ($user->hasRole('team_leader')) {
            $team = Team::where('team_leader_id', $user->id)->first();

            if ($team) {
                $ids = $ids->merge(
                    $team->members()->pluck('users.id')
                )->unique();
            }
        }

        return $ids->values()->all();
    }

    /**
     * Chequeo de acceso a una nota para acciones.
     */
    protected function canAccessNote(Note $note): bool
    {
        $user = auth()->user();

        if ($user->hasRole('sales_manager')) {
            return true;
        }

        $allowed = $this->allowedComercialIds();

        return in_array((int) $note->comercial_id, array_map('intval', $allowed), true);
    }

    public function editarNota(int $noteId)
    {
        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()->title('Acceso denegado')->danger()->send();
            return;
        }

        $url = NoteJVResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'comercial'
        );

        return redirect()->to($url);
    }

    public function confirmarBorrado(int $noteId): void
    {
        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->send();
            return;
        }

        $this->noteIdToDelete = $noteId;

        // Abre modal nativo Filament
        $this->dispatch('open-modal', id: 'confirm-delete-note');
    }

    public function cancelarBorrado(): void
    {
        $this->noteIdToDelete = null;
        $this->dispatch('close-modal', id: 'confirm-delete-note');
    }

    public function borrarNotaConfirmada(): void
    {
        $noteId = (int) ($this->noteIdToDelete ?? 0);

        if ($noteId <= 0) {
            $this->cancelarBorrado();
            return;
        }

        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->send();

            $this->cancelarBorrado();
            return;
        }

        $note->delete();

        // quitar de seleccionadas si estaba marcada
        $this->selectedNotes = array_values(array_filter(
            $this->selectedNotes,
            fn($id) => (int) $id !== $noteId
        ));

        Notification::make()
            ->title('Nota eliminada')
            ->success()
            ->send();

        $this->cancelarBorrado();
        $this->dispatch('notaActualizada');
    }

    public function getReassignComercialOptionsProperty(): array
    {
        return User::role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja') // SOLO activos
            ->orderBy('name')
            ->select('id', 'name', 'last_name', 'empleado_id')
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
            ])
            ->toArray();
    }


    public function confirmarReasignarComercial(int $noteId): void
    {
        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->send();
            return;
        }

        $this->noteIdToReassign = $noteId;
        $this->reassignComercialId = $note->comercial_id ? (int) $note->comercial_id : null;

        // Si tiene assignment_date, lo ponemos en YYYY-MM-DD para el date input
        $this->reassignAssignmentDate = $note->assignment_date
            ? optional(\Carbon\Carbon::parse($note->assignment_date))->format('Y-m-d')
            : null;

        $this->dispatch('open-modal', id: 'reassign-commercial-note');
    }

    public function cancelarReasignarComercial(): void
    {
        $this->noteIdToReassign = null;
        $this->reassignComercialId = null;
        $this->reassignAssignmentDate = null;

        $this->dispatch('close-modal', id: 'reassign-commercial-note');
    }

    public function reasignarComercialConfirmado(): void
    {
        $noteId = (int) ($this->noteIdToReassign ?? 0);

        if ($noteId <= 0) {
            $this->cancelarReasignarComercial();
            return;
        }

        $note = Note::find($noteId);

        if (!$note || !$this->canAccessNote($note)) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->send();

            $this->cancelarReasignarComercial();
            return;
        }

        // Validación igual a tu Action del Resource
        $this->validate([
            'reassignComercialId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereNull('baja')
                        ->whereExists(function ($sq) {
                            $sq->selectRaw(1)
                                ->from('model_has_roles as mhr')
                                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                                ->whereColumn('mhr.model_id', 'users.id')
                                ->where('mhr.model_type', User::class)
                                ->whereIn('r.name', ['commercial', 'team_leader', 'sales_manager']);
                        });
                }),
            ],
            'reassignAssignmentDate' => ['nullable', 'date'],
        ], [
            'reassignComercialId.exists' => 'El comercial seleccionado no es válido o no está activo.',
            'reassignAssignmentDate.date' => 'La fecha de asignación no es válida.',
        ]);

        try {
            $comercialId = $this->reassignComercialId ?: null;

            // Doble verificación runtime (igual idea)
            if (!empty($comercialId)) {
                $isValid = User::query()
                    ->where('id', $comercialId)
                    ->whereNull('baja')
                    ->whereHas('roles', fn($r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
                    ->exists();

                if (!$isValid) {
                    throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
                }
            }

            $assignmentDate = !empty($comercialId)
                ? ($this->reassignAssignmentDate ? \Carbon\Carbon::parse($this->reassignAssignmentDate) : now())
                : null;

            $updates = [
                'comercial_id' => $comercialId,
                'assignment_date' => $assignmentDate,
                'reten' => false,
            ];

            if ($note->estado_terminal === EstadoTerminal::SALA) {
                $updates['estado_terminal'] = EstadoTerminal::SIN_ESTADO->value;
                $updates['sent_to_sala_at'] = null;
            }

            $note->update($updates);

            Notification::make()
                ->title(empty($comercialId)
                    ? 'Comercial removido correctamente'
                    : 'Comercial asignado correctamente: ' . (User::find($comercialId)?->name ?? ''))
                ->success()
                ->send();

            $this->cancelarReasignarComercial();
            $this->dispatch('notaActualizada');

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al actualizar comercial')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }


    protected function getSelectedNoteIds(): array
    {
        return collect($this->selectedNotes)
            ->flatten()
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function getComercialFilterOptionsProperty(): array
    {
        return User::role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja') // recomendado: solo activos
            ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
            ->orderBy('users.name')
            ->distinct()
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
            ])
            ->toArray();
    }


    /**
     * ✅ Masivo: Enviar a Oficina (SALA) (igual que bulkAction del Resource)
     */
    public function bulkEnviarASala(): void
    {
        $allIds = $this->getSelectedNoteIds();

        if (empty($allIds)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        $ids = $this->getSelectedNoteIds();

        $allowed = Note::query()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn(Note $note) => $this->canAccessNote($note))
            ->pluck('id')
            ->values()
            ->all();


        if (empty($allIds)) {
            Notification::make()
                ->title('No hay notas válidas')
                ->warning()
                ->send();
            return;
        }

        // Elegibles: sin venta y TN ∈ { null, '', 'ausente' }
        $eligible = Note::query()
            ->whereIn('id', $allIds)
            ->whereDoesntHave('venta')
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
            })
            ->pluck('id')
            ->all();

        $skipped = count($allIds) - count($eligible);

        if (empty($eligible)) {
            Notification::make()
                ->title('No hay notas válidas para enviar a Oficina')
                ->body('Todas las seleccionadas tienen venta o su TN es NULO/CONFIRMADO/VENTA.')
                ->warning()
                ->send();
            return;
        }

        \DB::transaction(function () use ($eligible) {
            $now = now();
            $userId = auth()->id();

            // 1) Actualizar todas las notas elegibles
            Note::whereIn('id', $eligible)->update([
                'estado_terminal' => EstadoTerminal::SALA->value,
                'printed' => false,
                'reten' => false,
                'sent_to_sala_at' => $now,
                'fecha_declaracion' => $now,
            ]);

            // 2) Historial masivo
            $rows = [];
            foreach ($eligible as $noteId) {
                $rows[] = [
                    'note_id' => $noteId,
                    'sent_by_user_id' => $userId,
                    'via' => 'masivo',
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                NoteSalaEvent::insert($rows);
            }

            // 3) Evento afterCommit (igual)
            \DB::afterCommit(function () use ($eligible) {
                $comercial = auth()->user();

                event(new \App\Events\NotasEnviadasAOficinaBulk(
                    $eligible,
                    $comercial
                ));
            });
        });

        Notification::make()
            ->title('Notas enviadas a Oficina')
            ->body('Actualizadas: ' . count($eligible) . ($skipped ? ' • Omitidas: ' . $skipped : ''))
            ->success()
            ->send();

        $this->selectedNotes = [];
        $this->dispatch('notaActualizada');
    }

    protected function baseNotesQuery()
    {
        $user = auth()->user();

        $query = Note::query()->with(['customer', 'comercial', 'user']);

        // 1) Filtro por comercial (commercial / team_leader)
        if (!$user->hasRole('sales_manager')) {
            $ids = $this->allowedComercialIds();
            $query->whereIn('comercial_id', $ids);

            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        } else {
            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        }

        // 2) Sin ventas (como ya haces)
        $query->whereDoesntHave('venta');

        // 3) Filtros de asignación (como Resource)
        $hasAssignmentFilter = !empty($this->assignmentStart) || !empty($this->assignmentEnd) || !empty($this->assignmentExact);

        if ($hasAssignmentFilter) {
            if (!empty($this->assignmentExact)) {
                $query->whereDate('assignment_date', $this->assignmentExact);
            } else {
                if (!empty($this->assignmentStart)) {
                    $query->whereDate('assignment_date', '>=', $this->assignmentStart);
                }
                if (!empty($this->assignmentEnd)) {
                    $query->whereDate('assignment_date', '<=', $this->assignmentEnd);
                }
            }
        } else {
            $desde = now()->subDays(5)->toDateString();
            $hasta = now()->toDateString();
            $query->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta]);
        }

        // 4) Sin reten
        $query->where('reten', false);

        // 4.1) SelectFilter comercial_id
        if (!empty($this->comercialFilterId)) {
            $selected = (int) $this->comercialFilterId;

            if (!auth()->user()->hasRole('sales_manager')) {
                $allowedIds = array_map('intval', $this->allowedComercialIds());
                if (in_array($selected, $allowedIds, true)) {
                    $query->where('comercial_id', $selected);
                }
            } else {
                $query->where('comercial_id', $selected);
            }
        }

        // 5) Búsqueda
        $term = trim((string) $this->search);
        if ($term !== '') {
            $term = preg_replace('/\s+/', ' ', $term);

            $query->where(function ($q) use ($term) {
                $q->where('nro_nota', 'like', "%{$term}%");

                $q->orWhereHas('customer', function ($qc) use ($term) {
                    $qc->where(function ($w) use ($term) {
                        $w->where('first_names', 'like', "%{$term}%")
                            ->orWhere('last_names', 'like', "%{$term}%")
                            ->orWhereRaw(
                                "CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?",
                                ["%{$term}%"]
                            )
                            ->orWhere('dni', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('secondary_phone', 'like', "%{$term}%")
                            ->orWhere('primary_address', 'like', "%{$term}%")
                            ->orWhere('postal_code', 'like', "%{$term}%")
                            ->orWhere('ciudad', 'like', "%{$term}%");
                    });
                });

                $q->orWhereHas('user', function ($qu) use ($term) {
                    $qu->where('empleado_id', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(COALESCE(name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$term}%"]);
                });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        return $query;
    }

    public function getTabCountsProperty(): array
    {
        $base = $this->baseNotesQuery();

        // Si usas el filtro de Fecha Sala, forzamos "Oficina" por lógica
        if (!empty($this->sentToSalaAt)) {
            $oficina = (clone $base)
                ->where('estado_terminal', EstadoTerminal::SALA->value)
                ->whereDate('sent_to_sala_at', $this->sentToSalaAt)
                ->count();

            return [
                'oficina' => $oficina,
                'se' => 0,
                'todas' => $oficina,
            ];
        }

        $oficina = (clone $base)->where('estado_terminal', EstadoTerminal::SALA->value)->count();

        $se = (clone $base)->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '');
        })->count();

        $todas = (clone $base)->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '')
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'")
                ->orWhere('estado_terminal', EstadoTerminal::SALA->value);
        })->count();

        return compact('oficina', 'se', 'todas');
    }



    /**
     * ✅ ESTE es el query “de arriba” pero en Livewire.
     */
    public function getNotesProperty()
    {
        $user = auth()->user();

        $query = Note::query()->with(['customer', 'comercial', 'user']);

        // 1) Filtro por comercial (commercial / team_leader)
        if (!$user->hasRole('sales_manager')) {
            $ids = $this->allowedComercialIds();
            $query->whereIn('comercial_id', $ids);

            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        } else {
            // sales_manager también puede filtrar por activeTab
            $active = request()->query('activeTab', '');
            if (Str::startsWith($active, 'com_')) {
                $comId = (int) Str::after($active, 'com_');
                if ($comId > 0) {
                    $query->where('comercial_id', $comId);
                }
            }
        }

        $query = $this->baseNotesQuery();

        // 2) Tabs + filtro Fecha Sala
        if (!empty($this->sentToSalaAt)) {
            // Fecha Sala manda: SOLO SALA en esa fecha
            $query->where('estado_terminal', EstadoTerminal::SALA->value)
                ->whereDate('sent_to_sala_at', $this->sentToSalaAt);
        } else {
            if ($this->tab === 'oficina') {
                $query->where('estado_terminal', EstadoTerminal::SALA->value);
            } elseif ($this->tab === 'se') {
                $query->where(function ($q) {
                    $q->whereNull('estado_terminal')
                        ->orWhere('estado_terminal', '');
                });
            } else {
                // todas
                $query->where(function ($q) {
                    $q->whereNull('estado_terminal')
                        ->orWhere('estado_terminal', '')
                        ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'")
                        ->orWhere('estado_terminal', EstadoTerminal::SALA->value);
                });
            }
        }


        // 3) ✅ Filtros de asignación (como Resource)
        // Si hay filtros de assignment, NO aplicamos el default "últimos 5 días"
        $hasAssignmentFilter = !empty($this->assignmentStart) || !empty($this->assignmentEnd) || !empty($this->assignmentExact);

        if ($hasAssignmentFilter) {
            if (!empty($this->assignmentExact)) {
                $query->whereDate('assignment_date', $this->assignmentExact);
            } else {
                if (!empty($this->assignmentStart)) {
                    $query->whereDate('assignment_date', '>=', $this->assignmentStart);
                }
                if (!empty($this->assignmentEnd)) {
                    $query->whereDate('assignment_date', '<=', $this->assignmentEnd);
                }
            }
        } else {
            // Default actual: hoy-5 hasta hoy (INCLUSIVO)
            $desde = now()->subDays(5)->toDateString();
            $hasta = now()->toDateString();
            $query->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta]);
        }

        // 4) Sin reten
        $query->where('reten', false);

        // 4.1) ✅ SelectFilter comercial_id (como Resource)
        if (!empty($this->comercialFilterId)) {
            $selected = (int) $this->comercialFilterId;

            // Si NO es sales_manager, solo permitir filtrar dentro de sus IDs permitidos
            if (!auth()->user()->hasRole('sales_manager')) {
                $allowedIds = array_map('intval', $this->allowedComercialIds());
                if (in_array($selected, $allowedIds, true)) {
                    $query->where('comercial_id', $selected);
                }
            } else {
                $query->where('comercial_id', $selected);
            }
        }


        // 5) ✅ BÚSQUEDA (reactiva)
        $term = trim((string) $this->search);

        if ($term !== '') {
            $term = preg_replace('/\s+/', ' ', $term);

            $query->where(function ($q) use ($term) {

                // Nota: nro_nota (búsqueda parcial)
                $q->where('nro_nota', 'like', "%{$term}%");

                // Customer (tu BD usa first_names / last_names)
                $q->orWhereHas('customer', function ($qc) use ($term) {
                    $qc->where(function ($w) use ($term) {
                        $w->where('first_names', 'like', "%{$term}%")
                            ->orWhere('last_names', 'like', "%{$term}%")
                            ->orWhereRaw(
                                "CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?",
                                ["%{$term}%"]
                            )
                            ->orWhere('dni', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('secondary_phone', 'like', "%{$term}%")
                            ->orWhere('primary_address', 'like', "%{$term}%")
                            ->orWhere('postal_code', 'like', "%{$term}%")
                            ->orWhere('ciudad', 'like', "%{$term}%");
                    });
                });

                // Teleoperadora (user): empleado_id
                $q->orWhereHas('user', function ($qu) use ($term) {
                    $qu->where('empleado_id', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(COALESCE(name,''),' ',COALESCE(last_name,'')) LIKE ?", ["%{$term}%"]);
                });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }


        return $query
            ->latest('assignment_date')
            ->get()
            ->map(function ($note) {
                $customer = $note->customer;

                // ==== misma lógica de dirección formateada ====
                $primary = trim((string) ($customer->primary_address ?? ''));
                $nroPiso = trim((string) ($customer->nro_piso ?? ''));
                $postalCode = trim((string) ($customer->postal_code ?? ''));
                $city = trim((string) ($customer->ciudad ?? ''));
                $province = trim((string) ($customer->provincia ?? ''));
                $ayto = trim((string) ($customer->ayuntamiento ?? ''));

                $cpCity = trim(implode(' ', array_filter([$postalCode, $city])));
                $cpCity = preg_replace('/^(\d{4,5})\s+[A-ZÁÉÍÓÚÑ]\b\s+/u', '$1 ', $cpCity);

                $provinceFormatted = $province ? "($province)" : null;

                $dirL1 = $primary;

                $dirL2Parts = [];
                if ($nroPiso !== '')
                    $dirL2Parts[] = $nroPiso;
                if ($cpCity !== '')
                    $dirL2Parts[] = $cpCity;
                if ($ayto !== '')
                    $dirL2Parts[] = $ayto;

                $dirL2 = implode(' - ', $dirL2Parts);
                if ($provinceFormatted)
                    $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);

                $toTitleCase = function (?string $text): string {
                    $t = trim((string) $text);
                    if ($t === '')
                        return '';
                    $t = mb_strtolower($t, 'UTF-8');
                    return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
                };

                $dirL1 = $toTitleCase($dirL1);
                $dirL2 = $toTitleCase($dirL2);

                $dirOneLine = trim(
                    preg_replace('/\s+/', ' ', trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))),
                    ' -'
                );

                $fullAddress = $dirOneLine !== '' ? $dirOneLine : 'Sin dirección';

                $postalCodeSimple = $customer->postal_code ?? null;
                $citySimple = $customer->ciudad ?? null;
                $addressInfo = $postalCodeSimple && $citySimple
                    ? "$postalCodeSimple, $citySimple"
                    : ($postalCodeSimple ?? $citySimple ?? 'Sin ubicación');
                // ============================================
    
                return [
                    'id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'customer' => $customer->name ?? 'Sin cliente',
                    'full_address' => $fullAddress,
                    'primary_address' => $customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,
                    'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
                    'visit_date' => $note->visit_date ? \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y') : '--/--/----',
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $note->de_camino,
                    'show_phone' => $note->show_phone,
                    'phone' => $customer->phone ?? null,
                    'secondary_phone' => $customer->secondary_phone ?? null,
                    'lat_dentro' => $note->lat_dentro,
                    'lng' => $note->lng,
                    'lat' => $note->lat,
                    'lng_dentro' => $note->lng_dentro,
                ];
            });
    }

    public function render()
    {
        return view('livewire.commercial.notas-j-v');
    }
}
