<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use App\Models\Note;
use App\Models\User;
use App\Models\AnotacionVisita;
use Filament\Notifications\Notification;

class NotasDeComercial extends Component
{
    public string|int $comercialId;
    public bool $esReten = false;

    /** Modal de reasignación */
    public bool $showReassignModal = false;
    public ?int $reassignNoteId = null;
    public ?int $newComercialId = null;

    /** IDs seleccionados (hoy + todas) */
    public array $selectedNotes = [];

    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function mount(string|int $comercialId): void
    {
        $this->comercialId = $comercialId;
        $this->esReten = ($comercialId === 'reten');

        if ($this->esReten) {
            $this->selectedNotes = [];
        }
    }

    /** ====== Botón Reasignar ====== */
    public function openReassignModal(int $noteId): void
    {
        $this->reassignNoteId = $noteId;
        $this->newComercialId = null;
        $this->showReassignModal = true;
    }

    /** Reasignar SIN cambiar assignment_date */
    public function reassignVisit(): void
    {
        if (!$this->reassignNoteId || !$this->newComercialId) {
            Notification::make()->title('Selecciona un comercial')->warning()->send();
            return;
        }

        $note = Note::find($this->reassignNoteId);
        if (!$note) {
            Notification::make()->title('Nota no encontrada')->danger()->send();
            return;
        }

        $nuevo = User::find($this->newComercialId);
        $nombre = $nuevo ? trim(($nuevo->name ?? '') . ' ' . ($nuevo->last_name ?? '')) : 'Desconocido';
        $empleado = $nuevo->empleado_id ?? 'SIN-ID';

        $note->update(['comercial_id' => $this->newComercialId]);

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => auth()->id(),
            'asunto' => 'REASIGNACIÓN',
            'cuerpo' => "Nota #{$note->nro_nota} reasignada al comercial {$nombre} - {$empleado} (sin cambiar fecha de asignación).",
        ]);

        $this->showReassignModal = false;

        Notification::make()
            ->title("Nota #{$note->nro_nota} reasignada al comercial {$nombre} - {$empleado}")
            ->success()
            ->body('Se ha reasignado coreectamente la nota.')
            ->send();

        $this->dispatch('notaActualizada');
    }

    /** Opciones para el select del modal */
    public function getComercialesProperty(): array
    {
        return User::role(['commercial', 'team_leader', 'sales_manager'])
            ->orderBy('empleado_id')
            ->get()
            ->mapWithKeys(fn($u) => [
                $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
            ])
            ->toArray();
    }

    public function avisarSinDentro($notaId): void
    {
        Notification::make()
            ->title('Sin ubicación en GPS')
            ->body("La nota #{$notaId} no tiene coordenadas de GPS guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacionDentro($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note)
            return;

        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => auth()->id(),
            'asunto' => 'DENTRO',
            'cuerpo' => "Ubicación DENTRO: Latitud $lat, Longitud $lng",
        ]);

        Notification::make()
            ->title('Ubicación DENTRO capturada')
            ->success()
            ->body("Guardada para nota #$notaId: [$lat, $lng]")
            ->send();

        $this->dispatch('notaActualizada');
    }

    public function guardarUbicacion($notaId, $lat, $lng): void
    {
        $note = Note::find($notaId);
        if (!$note)
            return;

        $note->lat = $lat;
        $note->lng = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación capturada: Latitud $lat, Longitud $lng",
        ]);

        Notification::make()
            ->title('Ubicación capturada')
            ->success()
            ->body("Ubicación guardada para la nota #$notaId: [$lat, $lng]")
            ->send();
    }

    public function toggleDeCamino($noteId): void
    {
        $note = Note::find($noteId);
        if (!$note) {
            Notification::make()->title('Nota no encontrada')->danger()->send();
            return;
        }

        $puede = auth()->user()?->hasRole('gerente') || $note->comercial_id === auth()->id();
        if (!$puede) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->body('No tienes permiso para modificar esta nota.')
                ->send();
            return;
        }

        $note->de_camino = !$note->de_camino;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $noteId,
            'author_id' => auth()->id(),
            'asunto' => 'DE CAMINO',
            'cuerpo' => $note->de_camino ? 'Va de camino' : 'No va de camino',
        ]);

        Notification::make()
                    ->title('Estado actualizado')
            ->{$note->de_camino ? 'success' : 'warning'}()
                ->body($note->de_camino ? 'La nota ha sido marcada como EN CAMINO' : 'La nota ha sido marcada como NO EN CAMINO')
                ->send();

        $this->dispatch('notaActualizada');
    }

    public function sendSelectedToReten(): void
    {
        $ids = array_values(array_filter($this->selectedNotes));

        if (empty($ids)) {
            Notification::make()
                ->title('No hay notas seleccionadas')
                ->warning()
                ->send();
            return;
        }

        // Solo mover a retén las que tengan comercial asignado
        $updated = Note::query()
            ->whereIn('id', $ids)
            ->whereNotNull('comercial_id')
            ->update([
                'reten' => true,
                // Si al enviar a retén quieres refrescar la fecha de asignación:
                'assignment_date' => now()->startOfDay(),
            ]);

        Notification::make()
            ->title('Enviadas a Retén')
            ->body("Se enviaron {$updated} notas a retén.")
            ->success()
            ->send();

        // Limpiar selección
        $this->selectedNotes = [];

        // refrescar
        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {
        $url = \App\Filament\Gerente\Resources\NotasGerenteResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'gerente'
        );

        return redirect()->to($url);
    }

    /** Notas de HOY */
    public function getNotesTodayProperty()
    {
        $query = Note::with(['customer', 'comercial'])
            ->whereDate('assignment_date', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta');

        if ($this->esReten) {
            // 🔴 Modo RETEN: no filtramos por comercial, solo reten = true
            $query->where('reten', true);
        } else {
            // 🧑‍💼 Modo comercial normal: mismas querys + reten = false
            $query->where('comercial_id', $this->comercialId)
                ->where('reten', false);
            // si quieres considerar null como "no reten":
            // ->where(function ($q) {
            //     $q->whereNull('reten')->orWhere('reten', false);
            // });
        }

        return $query
            ->orderByDesc('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) DESC')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    /** TODAS (excepto hoy) */
    public function getNotesAllProperty()
    {
        $query = Note::with(['customer', 'comercial'])
            ->whereDate('assignment_date', '<', today())
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta')
            ->where('assignment_date', '>=', now()->subDays(5)->startOfDay());

        if ($this->esReten) {
            // 🔴 Modo RETEN: mismas querys + reten = true
            $query->where('reten', true);
        } else {
            // 🧑‍💼 Modo comercial normal: mismas querys + reten = false
            $query->where('comercial_id', $this->comercialId)
                ->where('reten', false);
            // si quieres aceptar null:
            // ->where(function ($q) {
            //     $q->whereNull('reten')->orWhere('reten', false);
            // });
        }

        return $query
            ->orderByDesc('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) DESC')
            ->get()
            ->map(fn($note) => $this->mapNote($note));
    }

    private function mapNote(Note $note): array
    {
        $customer = $note->customer;

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
        if ($nroPiso !== '') {
            $dirL2Parts[] = $nroPiso;
        }
        if ($cpCity !== '') {
            $dirL2Parts[] = $cpCity;
        }
        if ($ayto !== '') {
            $dirL2Parts[] = $ayto;
        }

        $dirL2 = implode(' - ', $dirL2Parts);

        if ($provinceFormatted) {
            $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);
        }

        $toTitleCase = function (?string $text): string {
            $t = trim((string) $text);
            if ($t === '') {
                return '';
            }
            $t = mb_strtolower($t, 'UTF-8');
            return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
        };

        $dirL1 = $toTitleCase($dirL1);
        $dirL2 = $toTitleCase($dirL2);

        $dirOneLine = trim(
            preg_replace(
                '/\s+/',
                ' ',
                trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))
            ),
            ' -'
        );

        $fullAddress = $dirOneLine !== '' ? $dirOneLine : 'Sin dirección';

        $postalCodeSimple = $customer->postal_code ?? null;
        $citySimple = $customer->ciudad ?? null;
        $addressInfo = $postalCodeSimple && $citySimple
            ? "$postalCodeSimple, $citySimple"
            : ($postalCodeSimple ?? $citySimple ?? 'Sin ubicación');

        return [
            'id' => $note->id,
            'nro_nota' => $note->nro_nota,
            'customer' => $customer->name ?? 'Sin cliente',
            'full_address' => $fullAddress,
            'primary_address' => $customer->primary_address ?? 'Sin dirección',
            'address_info' => $addressInfo,
            'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
            'visit_date' => \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y'),
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
    }

    public function render()
    {
        return view('livewire.gerente.notas-de-comercial');
    }
}
