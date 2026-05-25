<x-filament-panels::page>
    <style>
        body,
        .fi-layout,
        .fi-main,
        .fi-page,
        .fi-page-content,
        .fi-section,
        .fi-section-content,
        .fi-section-content-ctn {
            background: #ffffff !important;
        }

        .active-notes-page {
            background: #ffffff;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.25;
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
            background: #ffffff;
            color: #374151;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
            cursor: pointer;
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
            display: grid;
            grid-template-columns: auto minmax(120px, auto) auto minmax(0, 1fr) auto auto;
            align-items: start;
            gap: 4px;
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
            background: #fbbf24;
            color: #78350f;
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
            text-align: right;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .active-notes-elapsed {
            display: inline-block;
            grid-column: -2 / -1;
            justify-self: end;
            padding: 1px 5px;
            border-radius: 2px;
            background: #e5e7eb;
            color: #111827;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            text-align: right;
            white-space: nowrap;
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

        @media (max-width: 640px) {
            .active-notes-page {
                font-size: 13px;
            }

            .active-notes-row {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .active-notes-badge-topic,
            .active-notes-body,
            .active-notes-author {
                grid-column: 1 / -1;
                text-align: left;
            }

            .active-notes-elapsed {
                grid-column: 1 / -1;
                text-align: left;
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
                        ->filter(fn($entry) => $entry['activities']->isNotEmpty())
                        ->sortBy(fn($entry) => $entry['activities']->first()['created_at'])
                        ->values();

                    $activeNotesCount = $notes->filter(function ($entry) {
                        $note = $entry['note'];
                        $estado = $note->getRawOriginal('estado_terminal');
                        $isOpenState = $estado === null
                            || $estado === ''
                            || strtolower(trim((string) $estado)) === 'ausente';

                        return $isOpenState
                            && ! $note->venta
                            && ! (bool) $note->reten;
                    })->count();

                    $declaredTodayCount = $notes
                        ->filter(fn($entry) => $entry['note']->fecha_declaracion?->isToday())
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
                            @endphp

                            <div class="active-notes-note">
                                @if($note->fecha_declaracion?->isToday())
                                    <div class="active-notes-declared">
                                        Declarada hoy como {{ $note->estado_terminal?->label() ?? 'S/E' }}
                                        a las {{ $note->fecha_declaracion->format('H:i') }}
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
                                        <span class="active-notes-badge active-notes-badge-topic {{ match($activity['type']) { 'observacion' => 'is-observation', 'venta' => 'is-venta', default => 'is-annotation' } }}">
                                            {{ $activity['topic'] }}
                                        </span>
                                        <span class="active-notes-body">{{ $activity['body'] }}</span>
                                        <span class="active-notes-author">
                                            {{ $activity['author'] }}
                                        </span>
                                        <span class="active-notes-elapsed">{{ $elapsedLabel }}</span>
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
