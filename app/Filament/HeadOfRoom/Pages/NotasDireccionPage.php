<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Models\Note;
use App\Enums\EstadoTerminal;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

use Filament\Actions\Action;
use App\Filament\HeadOfRoom\Resources\NoteResource;

class NotasDireccionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas (Dirección)';
    protected static ?string $title = 'Notas (Dirección)';
    protected static ?string $slug = 'notas-direccion';
    protected static ?int $navigationSort = 20;
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.head-of-room.pages.notas-direccion';

    public ?string $phone = null;

    public function mount(): void
    {
        $this->phone = request()->get('phone');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descartar')
                ->label('Descartar')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn() => NoteResource::getUrl('index')),

            Action::make('seguir')
                ->label('Seguir')
                ->icon('heroicon-o-arrow-right')
                ->color('warning')
                ->disabled(fn() => blank($this->phone))
                ->url(fn() => NoteResource::getUrl('create', [
                    'phone' => $this->phone,
                ])),
        ];
    }

    /**
     * SOLO NOTAS NO CANDIDATAS:
     * - Rango: entre 1 y 4 meses atrás (por meses calendario)
     * - Estados terminal: NUL, VENTA, CONFIRMADO
     *
     * Ej: si hoy es Feb-2026:
     * - 1 a 4 meses atrás => Oct-2025, Nov-2025, Dic-2025, Ene-2026
     * - NO incluye Feb-2026 (0 meses) ni Sep-2025 (5 meses)
     */

    protected function getTableQuery(): Builder
    {
        $from     = now()->startOfMonth()->subMonthsNoOverflow(4);
        $todayEnd = now()->endOfDay();
        $tomorrow = now()->addDay()->startOfDay();

        return Note::query()
            ->with(['customer'])
            ->where(function (Builder $q) use ($from, $todayEnd, $tomorrow) {
                // (A) visit_date futura (cualquier estado)
                $q->where('visit_date', '>=', $tomorrow)

                // (B) visit_date en los últimos 4 meses (cualquier estado)
                ->orWhere(function (Builder $h) use ($from, $todayEnd) {
                    $h->whereNotNull('visit_date')
                        ->where('visit_date', '>=', $from)
                        ->where('visit_date', '<=', $todayEnd);
                })

                // (C) assignment_date en los últimos 4 meses (sin importar visit_date ni estado)
                ->orWhere(function (Builder $c) use ($from, $todayEnd) {
                    $c->whereNotNull('assignment_date')
                        ->where('assignment_date', '>=', $from)
                        ->where('assignment_date', '<=', $todayEnd);
                });
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('Nro Nota')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Nombre Cliente'),

                Tables\Columns\TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $term = '%' . mb_strtolower($search) . '%';
                        return $query->whereHas('customer', function (Builder $q) use ($term) {
                            $q->whereRaw('LOWER(primary_address) LIKE ?', [$term]);
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP'),

                Tables\Columns\TextColumn::make('customer.provincia')
                    ->label('Provincia'),

                Tables\Columns\TextColumn::make('customer.ciudad')
                    ->label('Ciudad'),

                Tables\Columns\TextColumn::make('customer.nro_piso')
                    ->label('Nro Piso'),
            ])
            ->filters([
                Tables\Filters\Filter::make('primary_address')
                    ->label('Dirección')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Dirección primaria')
                            ->placeholder('Calle, número...')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $term = '%' . mb_strtolower($data['value']) . '%';
                            $query->whereHas('customer', function (Builder $q) use ($term) {
                                $q->whereRaw('LOWER(primary_address) LIKE ?', [$term]);
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('postal_code')
                    ->label('CP')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Código Postal')
                            ->placeholder('Ej: 15551')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('customer', function (Builder $q) use ($data) {
                                $q->where('postal_code', 'like', '%' . $data['value'] . '%');
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('nro_piso')
                    ->label('Nro Piso')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Nro Piso')
                            ->placeholder('Ej: 3B / 2 / 1-A')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('customer', function (Builder $q) use ($data) {
                                $q->where('nro_piso', 'like', '%' . $data['value'] . '%');
                            });
                        }
                        return $query;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100]);
    }
}
