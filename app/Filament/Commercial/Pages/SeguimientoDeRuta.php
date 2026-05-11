<?php

namespace App\Filament\Commercial\Pages;

use App\Enums\EstadoTerminal;
use App\Models\Note;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeguimientoDeRuta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Seguimiento de ruta';
    protected static ?string $slug = 'seguimiento-de-ruta';
    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.commercial.pages.seguimiento-de-ruta';

    public string $selectedDay = 'hoy';

    protected ?Collection $comercialesCache = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['team_leader', 'sales_manager']) ?? false;
    }

    public function getTitle(): string
    {
        return 'Seguimiento de ruta';
    }

    public function getReportDaysProperty(): array
    {
        return [
            [
                'key' => 'hoy',
                'label' => 'HOY',
                'date' => today(),
            ],
            [
                'key' => 'ayer',
                'label' => 'AYER',
                'date' => today()->subDay(),
            ],
        ];
    }

    public function getSelectedReportDayProperty(): array
    {
        return collect($this->reportDays)
            ->firstWhere('key', $this->selectedDay)
            ?? $this->reportDays[0];
    }

    public function setSelectedDay(string $day): void
    {
        if (! in_array($day, ['hoy', 'ayer'], true)) {
            return;
        }

        $this->selectedDay = $day;
    }

    public function getComercialesProperty(): Collection
    {
        if ($this->comercialesCache instanceof Collection) {
            return $this->comercialesCache;
        }

        $today = today();
        $yesterday = today()->subDay();

        return $this->comercialesCache = User::query()
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')
            ->with([
                'notasDeclaradas' => fn($query) => $this->activeNotesQuery($query, $yesterday, $today),
                'notasDeclaradas.customer',
                'notasDeclaradas.venta',
                'notasDeclaradas.anotacionesVisitas.autor',
                'notasDeclaradas.observations.author',
                'notasDeclaradas.confirmations.companion',
            ])
            ->orderBy('empleado_id')
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();
    }

    protected function activeNotesQuery($query, Carbon $from, Carbon $to): void
    {
        $query
            ->where(function ($query) use ($from, $to) {
                $query
                    // Caso 1: nota en rango de asignación con estado abierto
                    ->where(function ($q) use ($from, $to) {
                        $q->whereDate('assignment_date', '>=', $from->toDateString())
                            ->whereDate('assignment_date', '<=', $to->toDateString())
                            ->where(function ($q2) {
                                $q2->whereNull('estado_terminal')
                                    ->orWhere('estado_terminal', '')
                                    ->orWhereRaw('LOWER(TRIM(estado_terminal)) = ?', [EstadoTerminal::AUSENTE->value]);
                            })
                            ->whereDoesntHave('venta')
                            ->where(function ($q2) {
                                $q2->whereNull('reten')->orWhere('reten', false);
                            });
                    })
                    // Caso 2: nota declarada hoy o ayer (confirmada, nula, sala...) sin importar assignment_date
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->whereDate('fecha_declaracion', '>=', $from->toDateString())
                            ->whereDate('fecha_declaracion', '<=', $to->toDateString());
                    });
            })
            ->orderBy('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) ASC');
    }

    public function getDailyActivitiesForNote(Note $note, Carbon $date): Collection
    {
        $anotaciones = ($note->anotacionesVisitas ?? collect())
            ->filter(fn($anotacion) => $anotacion->created_at?->isSameDay($date))
            ->map(fn($anotacion) => [
                'type' => 'anotacion',
                'created_at' => $anotacion->created_at,
                'topic' => $anotacion->asunto ?: 'SIN ASUNTO',
                'body' => $anotacion->cuerpo ?: 'Sin contenido',
                'author' => $anotacion->autor?->full_name ?? $anotacion->autor?->display_name ?? 'SIN AUTOR',
                'meta_label' => 'Anotado',
            ]);

        $observaciones = ($note->relationLoaded('observations')
            ? ($note->getRelation('observations') ?? collect())
            : $note->observations()->with('author')->get())
            ->filter(fn($observacion) => $observacion->created_at?->isSameDay($date))
            ->map(fn($observacion) => [
                'type' => 'observacion',
                'created_at' => $observacion->created_at,
                'topic' => 'OBSERVACION',
                'body' => $observacion->observation ?: 'Sin contenido',
                'author' => $observacion->author?->full_name ?? $observacion->author?->display_name ?? 'SIN AUTOR',
                'meta_label' => 'Observado',
            ]);

        $confirmaciones = ($note->relationLoaded('confirmations')
            ? ($note->getRelation('confirmations') ?? collect())
            : $note->confirmations()->with('companion')->get())
            ->filter(fn($conf) => $conf->created_at?->isSameDay($date))
            ->map(fn($conf) => [
                'type' => 'confirmada',
                'created_at' => $conf->created_at,
                'topic' => 'CONFIRMADA',
                'body' => ($conf->companion ? 'Compañero: ' . $conf->companion->display_name : '')
                    . ($conf->dio_crema ? ' | Crema: SÍ' : ' | Crema: NO')
                    . (!empty($conf->observation) ? ' | ' . $conf->observation : ''),
                'author' => $conf->companion?->display_name ?? '—',
                'meta_label' => 'Confirmada',
            ]);

        return $anotaciones
            ->concat($observaciones)
            ->concat($confirmaciones)
            ->sortBy('created_at')
            ->values();
    }
}
