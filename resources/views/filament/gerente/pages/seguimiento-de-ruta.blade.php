<x-filament-panels::page>
    <style>
        html:not(.dark) body,
        html:not(.dark) .fi-layout,
        html:not(.dark) .fi-main,
        html:not(.dark) .fi-page,
        html:not(.dark) .fi-page-content,
        html:not(.dark) .fi-section,
        html:not(.dark) .fi-section-content,
        html:not(.dark) .fi-section-content-ctn {
            background: #f0e6d8 !important;
        }

        .active-notes-page {
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.25;
        }

        html:not(.dark) .active-notes-page {
            background: #f0e6d8;
        }

        .active-notes-day {
            margin-bottom: 22px;
        }

        .active-notes-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 14px;
        }

        .active-notes-tab {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #374151;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
            cursor: pointer;
        }

        html:not(.dark) .active-notes-tab {
            background: #faf5ef;
        }

        html:not(.dark) .active-notes-tab.is-active {
            border-color: #16a34a;
            background: #16a34a;
            color: #ffffff;
        }

        .active-notes-tab.is-active {
            border-color: #16a34a;
            background: #16a34a;
            color: #ffffff;
        }

        .active-notes-day-title {
            margin: 0 0 10px;
            color: #9f1239;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .active-notes-commercial {
            margin-bottom: 16px;
            break-inside: avoid;
        }

        .active-notes-commercial-label {
            display: inline-block;
            max-width: 100%;
            padding: 2px 6px;
            border-radius: 3px;
            background: #374151;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }

        .active-notes-summary {
            margin-top: 3px;
            color: #9f1239;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .active-notes-note {
            margin-top: 7px;
            padding-bottom: 4px;
            border-bottom: 1px solid #f3f4f6;
        }

        .active-notes-note-meta {
            color: #4b5563;
            font-size: 13px;
        }

        .active-notes-note-time {
            color: #2563eb;
            font-weight: 800;
        }

        .active-notes-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 8px;
            margin-top: 2px;
        }

        .active-notes-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            white-space: nowrap;
        }

        a.active-notes-badge {
            text-decoration: none;
        }

        a.active-notes-badge:hover,
        a.active-notes-badge:focus-visible {
            filter: brightness(0.92);
            outline: none;
        }

        .active-notes-badge-customer {
            max-width: 260px;
            background: #c2410c;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .active-notes-badge-topic {
            background: #e5e7eb;
            color: #374151;
        }

        .active-notes-badge-topic.is-annotation {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .active-notes-badge-topic.is-observation {
            background: #dcfce7;
            color: #166534;
        }

        .active-notes-badge-topic.is-venta {
            background: #16a34a;
            color: #ffffff;
        }

        .active-notes-badge-topic.is-ausente {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .active-notes-badge-topic.is-nulo {
            background: #fee2e2;
            color: #b91c1c;
        }

        .active-notes-body {
            min-width: 0;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .active-notes-author {
            color: #111827;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            overflow-wrap: anywhere;
            white-space: nowrap;
        }

        .active-notes-elapsed {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 2px;
            background: #e5e7eb;
            color: #111827;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            white-space: nowrap;
        }

        .active-notes-ir-btn {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 3px;
            background: #16a34a;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            text-decoration: none;
            white-space: nowrap;
        }

        .active-notes-ir-btn:hover,
        .active-notes-ir-btn:focus-visible {
            background: #15803d;
            outline: none;
        }

        .active-notes-declared-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
        }

        .active-notes-empty-note {
            margin-top: 2px;
            color: #6b7280;
            font-size: 13px;
        }

        .active-notes-declared {
            display: inline-block;
            margin-top: 3px;
            padding: 1px 5px;
            border-radius: 2px;
            background: #bbf7d0;
            color: #14532d;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .active-notes-reassigned-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 3px;
        }

        .active-notes-reassigned {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 2px;
            background: #bae6fd;
            color: #0c4a6e;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            text-transform: uppercase;
        }

        @media (max-width: 640px) {
            .active-notes-page {
                font-size: 13px;
            }

            .active-notes-row {
                align-items: flex-start;
            }
        }
    </style>

    <div class="active-notes-page">
        <div class="active-notes-tabs" role="tablist" aria-label="Día del reporte">
            @foreach($this->reportDays as $day)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $selectedDay === $day['key'] ? 'true' : 'false' }}"
                    class="active-notes-tab {{ $selectedDay === $day['key'] ? 'is-active' : '' }}"
                    wire:click="setSelectedDay('{{ $day['key'] }}')"
                >
                    {{ ucfirst(strtolower($day['label'])) }}
                </button>
            @endforeach
        </div>

        @php
            $day = $this->selectedReportDay;
            $date = $day['date'];
            $dayLabel = $day['label'];

            $formatElapsed = function ($date): string {
                if (! $date) {
                    return '--h --m';
                }

                $minutes = max(0, (int) $date->diffInMinutes(now()));
                $hours = intdiv($minutes, 60);
                $remainingMinutes = $minutes % 60;

                return str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . 'h '
                    . str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT) . 'm';
            };
        @endphp

        <section class="active-notes-day" aria-labelledby="active-notes-{{ $day['key'] }}">
            <h2 id="active-notes-{{ $day['key'] }}" class="active-notes-day-title">
                {{ $dayLabel }}
            </h2>

            @foreach($this->comerciales as $comercial)
                @php
                    $notes = $comercial->notasDeclaradas
                        ->map(function ($note) use ($date) {
                            return [
                                'note' => $note,
                                'activities' => $this->getDailyActivitiesForNote($note, $date),
                            ];
                        })
                        ->filter(function ($entry) use ($date) {
                            $note = $entry['note'] ?? null;
                            if (! $note instanceof \App\Models\Note) {
                                return false;
                            }

                            if ($entry['activities']->isNotEmpty()) {
                                return true;
                            }

                            if ($note->fecha_declaracion?->isSameDay($date)) {
                                return true;
                            }

                            if (\App\Support\SeguimientoRutaDisplay::reassignmentLogForDate($note, $date)) {
                                return true;
                            }

                            return ($note->ausencias ?? collect())
                                ->contains(fn($ausencia) => $ausencia->fecha?->isSameDay($date) || $ausencia->created_at?->isSameDay($date));
                        })
                        ->sortBy(function ($entry) {
                            $firstActivity = $entry['activities']->first();

                            return $firstActivity['created_at']
                                ?? $entry['note']?->fecha_declaracion
                                ?? now();
                        })
                        ->values();

                    $activeNotesCount = $notes->filter(function ($entry) {
                        $note = $entry['note'] ?? null;
                        if (! $note instanceof \App\Models\Note) {
                            return false;
                        }

                        $estado = $note->getRawOriginal('estado_terminal');
                        $isOpenState = $estado === null
                            || $estado === ''
                            || strtolower(trim((string) $estado)) === 'ausente';

                        return $isOpenState
                            && ! filled($note->venta)
                            && ! (bool) $note->reten;
                    })->count();

                    $declaredTodayCount = $notes
                        ->filter(function ($entry) use ($date) {
                            $note = $entry['note'] ?? null;
                            if (! $note instanceof \App\Models\Note) {
                                return false;
                            }

                            if ($note->fecha_declaracion?->isSameDay($date)) {
                                return true;
                            }

                            $estado = strtolower(trim((string) $note->getRawOriginal('estado_terminal')));
                            if ($estado !== 'ausente') {
                                return false;
                            }

                            return ($note->ausencias ?? collect())
                                ->contains(fn($ausencia) => $ausencia->fecha?->isSameDay($date) || $ausencia->created_at?->isSameDay($date));
                        })
                        ->count();

                    $fullName = trim($comercial->name . ' ' . $comercial->last_name);
                    $commercialLabelName = mb_strtoupper($fullName, 'UTF-8');
                @endphp

                <article class="active-notes-commercial">
                    <div class="active-notes-commercial-label">
                        Com {{ $comercial->empleado_id ?? 'SIN-ID' }} - {{ $commercialLabelName }}
                    </div>

                    @if($notes->isEmpty())
                        <div class="active-notes-summary">
                            SIN ACTIVIDAD REGISTRADA PARA {{ $dayLabel }}
                        </div>
                    @else
                        <div class="active-notes-summary">
                            @if($activeNotesCount > 0)
                                TIENE {{ $activeNotesCount }} {{ $activeNotesCount === 1 ? 'NOTA ACTIVA' : 'NOTAS ACTIVAS' }} PARA {{ $dayLabel }}
                            @else
                                SIN NOTAS ACTIVAS PARA {{ $dayLabel }}
                            @endif

                            @if($declaredTodayCount > 0)
                                · {{ $declaredTodayCount }} {{ $declaredTodayCount === 1 ? 'DECLARADA HOY' : 'DECLARADAS HOY' }}
                            @endif
                        </div>

                        @foreach($notes as $entry)
                            @php
                                $note = $entry['note'];
                                $activities = $entry['activities'];
                                $estadoVal = strtolower(trim((string) $note->getRawOriginal('estado_terminal')));

                                $showDeclaredBanner = $note->fecha_declaracion?->isSameDay($date);
                                $declaredAt = $showDeclaredBanner ? $note->fecha_declaracion : null;
                                $declaredEstadoLabel = $note->estado_terminal?->label() ?? 'S/E';

                                if (! $showDeclaredBanner && $estadoVal === 'ausente') {
                                    $ausenciaDelDia = ($note->ausencias ?? collect())
                                        ->filter(fn($ausencia) => $ausencia->fecha?->isSameDay($date) || $ausencia->created_at?->isSameDay($date))
                                        ->sortByDesc(fn($ausencia) => ($ausencia->fecha?->format('Y-m-d') ?? '') . ' ' . ($ausencia->hora ?? $ausencia->created_at?->format('H:i:s') ?? ''))
                                        ->first();

                                    if ($ausenciaDelDia) {
                                        $showDeclaredBanner = true;
                                        $fechaAusencia = $ausenciaDelDia->fecha?->toDateString() ?? $ausenciaDelDia->created_at?->toDateString() ?? $date->toDateString();
                                        $horaAusencia = $ausenciaDelDia->hora ?: $ausenciaDelDia->created_at?->format('H:i:s') ?: '00:00:00';
                                        $declaredAt = \Carbon\Carbon::parse("{$fechaAusencia} {$horaAusencia}");
                                        $declaredEstadoLabel = 'AUS';
                                    }
                                }

                                $declaredGpsLat = null;
                                $declaredGpsLng = null;
                                if ($showDeclaredBanner) {
                                    if ($estadoVal === 'nulo' && filled($note->lat) && filled($note->lng)) {
                                        $declaredGpsLat = $note->lat;
                                        $declaredGpsLng = $note->lng;
                                    } elseif ($estadoVal === 'confirmado' && filled($note->lat_dentro) && filled($note->lng_dentro)) {
                                        $declaredGpsLat = $note->lat_dentro;
                                        $declaredGpsLng = $note->lng_dentro;
                                    } elseif ($estadoVal === 'ausente') {
                                        $lastAusencia = ($note->ausencias ?? collect())
                                            ->filter(fn($ausencia) => $ausencia->fecha?->isSameDay($date) || $ausencia->created_at?->isSameDay($date))
                                            ->sortByDesc(fn($ausencia) => ($ausencia->fecha?->format('Y-m-d') ?? '') . ' ' . ($ausencia->hora ?? $ausencia->created_at?->format('H:i:s') ?? ''))
                                            ->first();
                                        $declaredGpsLat = filled($lastAusencia?->latitud)
                                            ? $lastAusencia->latitud
                                            : (filled($note->lat_dentro) ? $note->lat_dentro : null);
                                        $declaredGpsLng = filled($lastAusencia?->longitud)
                                            ? $lastAusencia->longitud
                                            : (filled($note->lng_dentro) ? $note->lng_dentro : null);
                                    }
                                }

                                $reassignmentBanner = \App\Support\SeguimientoRutaDisplay::reassignmentBannerForDate($note, $date);
                                $reassignedGpsLat = null;
                                $reassignedGpsLng = null;
                                if ($estadoVal === 'nulo' && filled($note->lat) && filled($note->lng)) {
                                    $reassignedGpsLat = $note->lat;
                                    $reassignedGpsLng = $note->lng;
                                } elseif ($estadoVal === 'confirmado' && filled($note->lat_dentro) && filled($note->lng_dentro)) {
                                    $reassignedGpsLat = $note->lat_dentro;
                                    $reassignedGpsLng = $note->lng_dentro;
                                } elseif ($estadoVal === 'ausente') {
                                    $lastAusencia = ($note->ausencias ?? collect())
                                        ->filter(fn($ausencia) => $ausencia->fecha?->isSameDay($date) || $ausencia->created_at?->isSameDay($date))
                                        ->sortByDesc(fn($ausencia) => ($ausencia->fecha?->format('Y-m-d') ?? '') . ' ' . ($ausencia->hora ?? $ausencia->created_at?->format('H:i:s') ?? ''))
                                        ->first();

                                    $reassignedGpsLat = filled($lastAusencia?->latitud)
                                        ? $lastAusencia->latitud
                                        : (filled($note->lat_dentro) ? $note->lat_dentro : null);

                                    $reassignedGpsLng = filled($lastAusencia?->longitud)
                                        ? $lastAusencia->longitud
                                        : (filled($note->lng_dentro) ? $note->lng_dentro : null);
                                }
                            @endphp

                            <div class="active-notes-note">
                                @if($showDeclaredBanner && $declaredAt)
                                    <div class="active-notes-declared-row">
                                        <div class="active-notes-declared">
                                            #{{ $note->nro_nota }} · Declarada {{ $date->isToday() ? 'hoy' : 'el ' . $date->format('d/m/Y') }} como {{ $declaredEstadoLabel }}
                                            a las {{ $declaredAt->format('H:i') }}
                                        </div>
                                        @if(filled($declaredGpsLat) && filled($declaredGpsLng))
                                            <a href="https://www.google.com/maps?q={{ $declaredGpsLat }},{{ $declaredGpsLng }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="active-notes-ir-btn"
                                            >IR</a>
                                        @endif
                                    </div>
                                @endif

                                @if($reassignmentBanner && $reassignmentBanner['reassigned_at'])
                                    <div class="active-notes-reassigned-row">
                                        <div class="active-notes-reassigned">
                                            #{{ $note->nro_nota }} · {{ $reassignmentBanner['label'] }}
                                            · a las {{ $reassignmentBanner['reassigned_at']->format('H:i') }}
                                        </div>
                                    </div>
                                @endif

                                @php
                                    $lastActivityAt = $activities->last()['created_at'] ?? null;
                                    $elapsedLabel = $formatElapsed($lastActivityAt);
                                    $customerName = mb_strtoupper($note->customer?->name ?: 'SIN CLIENTE', 'UTF-8');
                                    $fuenteValue = $note->fuente instanceof \App\Enums\FuenteNotas
                                        ? $note->fuente->value
                                        : (string) $note->fuente;
                                    $noteBg = match ($fuenteValue) {
                                        'CALLE' => '#ea580c',
                                        'VIP-INT' => '#16a34a',
                                        'VIP-EXT' => '#a16207',
                                        'PtaFria' => '#dc2626',
                                        'excel' => '#0284c7',
                                        default => '#6b7280',
                                    };
                                    $comercialId = $note->comercial_id;
                                    $notaUrl = $comercialId
                                        ? \App\Filament\Gerente\Pages\NotasDeComercial::getUrl(
                                            ['comercial_id' => $comercialId],
                                            panel: 'gerente',
                                        ) . '#note-' . $note->id
                                        : \App\Filament\Gerente\Resources\NotasGerenteResource::getUrl(
                                            'edit',
                                            ['record' => $note],
                                            panel: 'gerente',
                                        );
                                @endphp

                                @foreach($activities as $activity)
                                    <div class="active-notes-note-meta">
                                        {{ $activity['meta_label'] }} el {{ $activity['created_at']?->format('d/m/Y') ?? 'Sin fecha' }}
                                        a las <span class="active-notes-note-time">{{ $activity['created_at']?->format('H:i') ?? '--:--' }}</span>
                                    </div>

                                    <div class="active-notes-row">
                                        <a href="{{ $notaUrl }}" class="active-notes-badge" style="background: {{ $noteBg }}">
                                            {{ $note->nro_nota }}
                                        </a>
                                        <span class="active-notes-badge active-notes-badge-customer">{{ $customerName }}</span>
                                        <span class="active-notes-badge active-notes-badge-topic {{ $activity['type'] === 'venta' ? 'is-venta' : ($activity['type'] === 'ausente' ? 'is-ausente' : ($activity['type'] === 'nulo' ? 'is-nulo' : ($activity['type'] === 'observacion' ? 'is-observation' : ($activity['type'] === 'anotacion' ? 'is-annotation' : '')))) }}">
                                            {{ $activity['topic'] }}
                                        </span>
                                        @if(filled($activity['body'] ?? null))
                                            <span class="active-notes-body">{{ $activity['body'] }}</span>
                                        @endif
                                        <span class="active-notes-author">
                                            {{ $activity['author'] }}
                                        </span>
                                        <span class="active-notes-elapsed">{{ $elapsedLabel }}</span>
                                        @if(filled($activity['gps_lat'] ?? null) && filled($activity['gps_lng'] ?? null))
                                            <a href="https://www.google.com/maps?q={{ $activity['gps_lat'] }},{{ $activity['gps_lng'] }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="active-notes-ir-btn"
                                            >IR</a>
                                        @else
                                            <span></span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </article>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
