<x-filament-panels::page>
    <style>
        .active-notes-page {
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
            background: #6b7280;
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
            grid-template-columns: auto auto minmax(0, 1fr) auto;
            align-items: start;
            gap: 4px;
            margin-top: 2px;
        }

        .active-notes-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            background: #fef3c7;
            color: #374151;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.3;
            white-space: nowrap;
        }

        .active-notes-badge-topic {
            background: #e5e7eb;
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

        .active-notes-empty-note {
            margin-top: 2px;
            color: #6b7280;
            font-size: 13px;
            font-style: italic;
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

        .dark .active-notes-page {
            color: #f9fafb;
        }

        .dark .active-notes-tab {
            border-color: #4b5563;
            background: #111827;
            color: #f9fafb;
        }

        .dark .active-notes-tab.is-active {
            border-color: #4ade80;
            background: #16a34a;
            color: #ffffff;
        }

        .dark .active-notes-note {
            border-color: #374151;
        }

        .dark .active-notes-note-meta,
        .dark .active-notes-empty-note {
            color: #d1d5db;
        }

        .dark .active-notes-body,
        .dark .active-notes-author {
            color: #f9fafb;
        }

        @media (max-width: 640px) {
            .active-notes-page {
                font-size: 13px;
            }

            .active-notes-row {
                grid-template-columns: auto auto minmax(0, 1fr);
            }

            .active-notes-author {
                grid-column: 3;
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
        @endphp

        <section class="active-notes-day" aria-labelledby="active-notes-{{ $day['key'] }}">
            <h2 id="active-notes-{{ $day['key'] }}" class="active-notes-day-title">
                {{ $dayLabel }}
            </h2>

            @foreach($this->comerciales as $comercial)
                @php
                    $notes = $comercial->notasDeclaradas
                        ->filter(fn($note) => $note->assignment_date?->isSameDay($date))
                        ->values();

                    $activeNotesCount = $notes->filter(function ($note) {
                        $estado = $note->getRawOriginal('estado_terminal');
                        $isOpenState = $estado === null
                            || $estado === ''
                            || strtolower(trim((string) $estado)) === 'ausente';

                        return $isOpenState
                            && ! $note->venta
                            && ! (bool) $note->reten;
                    })->count();

                    $declaredTodayCount = $notes
                        ->filter(fn($note) => $note->fecha_declaracion?->isToday())
                        ->count();

                    $fullName = trim($comercial->name . ' ' . $comercial->last_name);
                @endphp

                <article class="active-notes-commercial">
                    <div class="active-notes-commercial-label">
                        Comercial {{ $comercial->empleado_id ?? 'SIN-ID' }} {{ $fullName }}
                    </div>

                    @if($notes->isEmpty())
                        <div class="active-notes-summary">
                            SIN ANOTACIONES NO TIENE NOTAS ACTIVAS PARA {{ $dayLabel }}
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

                        @foreach($notes as $note)
                            <div class="active-notes-note">
                                @if($note->fecha_declaracion?->isToday())
                                    <div class="active-notes-declared">
                                        Declarada hoy como {{ $note->estado_terminal?->label() ?? 'S/E' }}
                                        a las {{ $note->fecha_declaracion->format('H:i') }}
                                    </div>
                                @endif

                                @php
                                    $anotaciones = $note->anotacionesVisitas->sortBy('created_at')->values();
                                @endphp

                                @forelse($anotaciones as $anotacion)
                                    <div class="active-notes-note-meta">
                                        Anotado el {{ $anotacion->created_at?->format('d/m/Y') ?? 'Sin fecha' }}
                                        a las <span class="active-notes-note-time">{{ $anotacion->created_at?->format('H:i') ?? '--:--' }}</span>
                                    </div>

                                    <div class="active-notes-row">
                                        <span class="active-notes-badge">{{ $note->nro_nota }}</span>
                                        <span class="active-notes-badge active-notes-badge-topic">
                                            {{ $anotacion->asunto ?: 'SIN ASUNTO' }}
                                        </span>
                                        <span class="active-notes-body">{{ $anotacion->cuerpo ?: 'Sin contenido' }}</span>
                                        <span class="active-notes-author">
                                            {{ $anotacion->autor?->full_name ?? $anotacion->autor?->display_name ?? 'SIN AUTOR' }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="active-notes-note-meta">
                                        Nota activa desde {{ $note->assignment_date?->format('d/m/Y') ?? 'Sin fecha' }}
                                        a las <span class="active-notes-note-time">{{ $note->assignment_date?->format('H:i') ?? '--:--' }}</span>
                                    </div>

                                    <div class="active-notes-row">
                                        <span class="active-notes-badge">{{ $note->nro_nota }}</span>
                                        <span class="active-notes-empty-note">Sin anotaciones registradas</span>
                                    </div>
                                @endforelse
                            </div>
                        @endforeach
                    @endif
                </article>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
