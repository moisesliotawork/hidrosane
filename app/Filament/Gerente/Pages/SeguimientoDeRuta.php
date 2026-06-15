<?php

namespace App\Filament\Gerente\Pages;

use App\Enums\EstadoTerminal;
use App\Models\Note;
use App\Models\User;
use App\Support\SeguimientoRutaDisplay;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeguimientoDeRuta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Seguimiento de ruta';
    protected static ?string $slug = 'seguimiento-de-ruta';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.gerente.pages.seguimiento-de-ruta';

    public string $selectedDay = 'hoy';

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
        $today = today();
        $yesterday = today()->subDay();

        return User::query()
            ->role(['commercial', 'team_leader', 'sales_manager'])
            ->whereNull('baja')
            ->with([
                'notasDeclaradas' => fn($query) => $this->activeNotesQuery($query, $yesterday, $today),
                'notasDeclaradas.customer',
                'notasDeclaradas.venta',
                'notasDeclaradas.anotacionesVisitas.autor',
                'notasDeclaradas.observations.author',
                'notasDeclaradas.confirmations.companion',
                'notasDeclaradas.ausencias.autor',
                'notasDeclaradas.nullReasons.companion',
                'notasDeclaradas.nullReasons.comercial',
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
                    })
                    // Caso 3: nota con venta registrada en el rango
                    ->orWhereHas('venta', function ($q) use ($from, $to) {
                        $q->whereDate('fecha_venta', '>=', $from->toDateString())
                            ->whereDate('fecha_venta', '<=', $to->toDateString());
                    })
                    // Caso 4: ausencia registrada en el rango
                    ->orWhereHas('ausencias', function ($q) use ($from, $to) {
                        $q->whereDate('fecha', '>=', $from->toDateString())
                            ->whereDate('fecha', '<=', $to->toDateString());
                    });
            })
            ->orderBy('assignment_date')
            ->orderByRaw('CAST(nro_nota AS UNSIGNED) ASC');
    }

    public function getDailyActivitiesForNote(Note $note, Carbon $date): Collection
    {
        $anotaciones = ($note->anotacionesVisitas ?? collect())
            ->filter(fn($anotacion) => $anotacion->created_at?->isSameDay($date)
                && strtoupper(trim((string) ($anotacion->asunto ?? ''))) !== 'AUSENTE')
            ->map(function ($anotacion) use ($note) {
                $isGpsOnly = SeguimientoRutaDisplay::isGpsLocationText($anotacion->cuerpo);
                $gps = $isGpsOnly
                    ? SeguimientoRutaDisplay::gpsCoordsForGpsText($anotacion->cuerpo, (string) ($anotacion->asunto ?? ''), $note)
                    : ['gps_lat' => null, 'gps_lng' => null];

                return [
                    'type' => 'anotacion',
                    'created_at' => $anotacion->created_at,
                    'topic' => $anotacion->asunto ?: 'SIN ASUNTO',
                    'body' => $isGpsOnly
                        ? ''
                        : SeguimientoRutaDisplay::displayBody($anotacion->cuerpo, 'Sin contenido'),
                    'author' => $anotacion->autor?->full_name ?? $anotacion->autor?->display_name ?? 'SIN AUTOR',
                    'meta_label' => 'Anotado',
                    'gps_lat' => $gps['gps_lat'],
                    'gps_lng' => $gps['gps_lng'],
                ];
            });

        $observaciones = ($note->relationLoaded('observations')
            ? ($note->getRelation('observations') ?? collect())
            : $note->observations()->with('author')->get())
            ->filter(fn($observacion) => $observacion->created_at?->isSameDay($date))
            ->map(fn($observacion) => [
                'type' => 'observacion',
                'created_at' => $observacion->created_at,
                'topic' => 'OBSERVACION',
                'body' => SeguimientoRutaDisplay::displayBody($observacion->observation, 'Sin contenido'),
                'author' => $observacion->author?->full_name ?? $observacion->author?->display_name ?? 'SIN AUTOR',
                'meta_label' => 'Observado',
                'gps_lat' => null,
                'gps_lng' => null,
            ]);

        $confirmaciones = ($note->relationLoaded('confirmations')
            ? ($note->getRelation('confirmations') ?? collect())
            : $note->confirmations()->with('companion')->get())
            ->filter(fn($conf) => $conf->created_at?->isSameDay($date))
            ->map(fn($conf) => [
                'type' => 'confirmada',
                'created_at' => $conf->created_at,
                'topic' => 'CONFIRMADA',
                'body' => SeguimientoRutaDisplay::displayBody(trim(
                    ($conf->companion ? 'Compañero: ' . $conf->companion->display_name : '')
                    . ($conf->dio_crema ? ' | Crema: SÍ' : ' | Crema: NO')
                    . (!empty($conf->observation) ? ' | ' . $conf->observation : '')
                )),
                'author' => $conf->companion?->display_name ?? '—',
                'meta_label' => 'Confirmada',
                'gps_lat' => filled($note->lat_dentro) ? $note->lat_dentro : null,
                'gps_lng' => filled($note->lng_dentro) ? $note->lng_dentro : null,
            ]);

        $ausencias = ($note->relationLoaded('ausencias')
            ? ($note->getRelation('ausencias') ?? collect())
            : $note->ausencias()->with('autor')->get())
            ->filter(fn($ausencia) => $ausencia->fecha?->isSameDay($date)
                || $ausencia->created_at?->isSameDay($date))
            ->map(function ($ausencia) use ($note) {
                $fecha = $ausencia->fecha?->toDateString() ?? $ausencia->created_at?->toDateString() ?? today()->toDateString();
                $hora = $ausencia->hora ?: $ausencia->created_at?->format('H:i:s') ?: '00:00:00';

                return [
                    'type' => 'ausente',
                    'created_at' => Carbon::parse("{$fecha} {$hora}"),
                    'topic' => 'AUSENTE',
                    'body' => SeguimientoRutaDisplay::displayBody($ausencia->observacion, 'Marcado como AUSENTE'),
                    'author' => $ausencia->autor?->display_name ?? $ausencia->autor?->full_name ?? '—',
                    'meta_label' => 'Ausente',
                    'gps_lat' => filled($ausencia->latitud) ? $ausencia->latitud : (filled($note->lat_dentro) ? $note->lat_dentro : null),
                    'gps_lng' => filled($ausencia->longitud) ? $ausencia->longitud : (filled($note->lng_dentro) ? $note->lng_dentro : null),
                ];
            });

        if ($ausencias->isEmpty()) {
            $ausencias = ($note->anotacionesVisitas ?? collect())
                ->filter(fn($anotacion) => $anotacion->created_at?->isSameDay($date)
                    && strtoupper(trim((string) ($anotacion->asunto ?? ''))) === 'AUSENTE')
                ->map(fn($anotacion) => [
                    'type' => 'ausente',
                    'created_at' => $anotacion->created_at,
                    'topic' => 'AUSENTE',
                    'body' => SeguimientoRutaDisplay::displayBody($anotacion->cuerpo, 'Marcado como AUSENTE'),
                    'author' => $anotacion->autor?->display_name ?? $anotacion->autor?->full_name ?? '—',
                    'meta_label' => 'Ausente',
                    'gps_lat' => filled($note->lat_dentro) ? $note->lat_dentro : null,
                    'gps_lng' => filled($note->lng_dentro) ? $note->lng_dentro : null,
                ]);
        }

        $nulos = ($note->relationLoaded('nullReasons')
            ? ($note->getRelation('nullReasons') ?? collect())
            : $note->nullReasons()->with(['companion', 'comercial'])->get())
            ->filter(fn($nullReason) => $nullReason->created_at?->isSameDay($date))
            ->map(function ($nullReason) use ($note) {
                $bodyParts = [];
                if ($nullReason->companion) {
                    $bodyParts[] = 'Compañero: ' . $nullReason->companion->display_name;
                }
                if (filled($nullReason->reason)) {
                    $bodyParts[] = $nullReason->reason;
                }

                return [
                    'type' => 'nulo',
                    'created_at' => $nullReason->created_at ?? $note->fecha_declaracion,
                    'topic' => 'NULO',
                    'body' => SeguimientoRutaDisplay::displayBody($bodyParts ? implode(' | ', $bodyParts) : null, 'Marcado como NULO'),
                    'author' => $nullReason->comercial?->display_name ?? $nullReason->comercial?->full_name ?? '—',
                    'meta_label' => 'Nulo',
                    'gps_lat' => filled($note->lat) ? $note->lat : null,
                    'gps_lng' => filled($note->lng) ? $note->lng : null,
                ];
            });

        if ($nulos->isEmpty()
            && strtolower(trim((string) $note->getRawOriginal('estado_terminal'))) === EstadoTerminal::NUL->value
            && $note->fecha_declaracion?->isSameDay($date)) {
            $nullReason = $note->relationLoaded('nullReason')
                ? $note->nullReason
                : $note->nullReason()->with(['companion', 'comercial'])->first();
            $bodyParts = [];
            if ($nullReason?->companion) {
                $bodyParts[] = 'Compañero: ' . $nullReason->companion->display_name;
            }
            if (filled($nullReason?->reason)) {
                $bodyParts[] = $nullReason->reason;
            }

            $nulos = collect([[
                'type' => 'nulo',
                'created_at' => $note->fecha_declaracion,
                'topic' => 'NULO',
                'body' => SeguimientoRutaDisplay::displayBody($bodyParts ? implode(' | ', $bodyParts) : null, 'Marcado como NULO'),
                'author' => $nullReason?->comercial?->display_name ?? $nullReason?->comercial?->full_name ?? '—',
                'meta_label' => 'Nulo',
                'gps_lat' => filled($note->lat) ? $note->lat : null,
                'gps_lng' => filled($note->lng) ? $note->lng : null,
            ]]);
        }

        $ventas = collect();
        if ($note->venta && $note->venta->fecha_venta?->isSameDay($date)) {
            $venta = $note->venta;
            $ventaBody = 'Contrato: ' . ($venta->nro_contr_adm ?: 'S/N');
            if ($venta->origen_venta) {
                $ventaBody .= ' | Origen: ' . (
                    $venta->origen_venta instanceof \App\Enums\OrigenVenta
                        ? $venta->origen_venta->label()
                        : $venta->origen_venta
                );
            }
            $ventas = collect([[
                'type'       => 'venta',
                'created_at' => $venta->fecha_venta,
                'topic'      => 'VENTA',
                'body'       => $ventaBody,
                'author'     => '—',
                'meta_label' => 'Vendido',
                'gps_lat'    => $venta->lat,
                'gps_lng'    => $venta->lng,
            ]]);
        }

        return $anotaciones
            ->concat($observaciones)
            ->concat($confirmaciones)
            ->concat($ausencias)
            ->concat($nulos)
            ->concat($ventas)
            ->sortBy('created_at')
            ->values();
    }
}
