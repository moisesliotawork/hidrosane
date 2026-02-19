<?php

namespace App\Filament\Teleoperator\Pages;

use App\Models\Note;
use App\Enums\EstadoTerminal;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

use Filament\Actions\Action;
use App\Filament\Teleoperator\Resources\NoteResource;

class NotasDireccionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas (Dirección)';
    protected static ?string $title = 'Notas (Dirección)';
    protected static ?string $slug = 'notas-direccion';
    protected static ?int $navigationSort = 20;
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.teleoperator.pages.notas-direccion';

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
                ->url(fn () => NoteResource::getUrl('index')),

            Action::make('seguir')
                ->label('Seguir')
                ->icon('heroicon-o-arrow-right')
                ->color('warning')
                ->disabled(fn () => blank($this->phone))
                ->url(fn () => NoteResource::getUrl('create', [
                    'phone' => $this->phone,
                ])),
        ];
    }

    /**
     * Solo NOTAS NO CANDIDATAS:
     * - Rango: entre 1 y 4 meses atrás (por meses calendario)
     * - Estados terminal: NUL, VENTA, CONFIRMADO
     *
     * Ej: si hoy es Feb-2026:
     * - 1 a 4 meses atrás => Oct-2025, Nov-2025, Dic-2025, Ene-2026
     * - NO incluye Feb-2026 (0 meses) ni Sep-2025 (5 meses)
     */
    protected function getTableQuery(): Builder
    {
        $from = now()->startOfMonth()->subMonthsNoOverflow(4); // 1er día de hace 4 meses
        $to   = now()->startOfMonth()->subMonthsNoOverflow(0); // 1er día del mes actual (excluyente)

        return Note::query()
            ->with(['customer'])
            ->where('visit_date', '>=', $from)
            ->where('visit_date', '<=', $to)
            ->whereIn('estado_terminal', [
                EstadoTerminal::NUL->value,
                EstadoTerminal::VENTA->value,
                EstadoTerminal::CONFIRMADO->value,
            ]);
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
                    ->label('Nombre Cliente')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where(function (Builder $qq) use ($search) {
                                $qq->where('customers.first_names', 'like', "%{$search}%")
                                    ->orWhere('customers.last_names', 'like', "%{$search}%")
                                    ->orWhereRaw(
                                        "CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,'')) LIKE ?",
                                        ["%{$search}%"]
                                    );
                            });
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('primary_address', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('postal_code', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.provincia')
                    ->label('Provincia')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('provincia', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.ciudad')
                    ->label('Ciudad')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('ciudad', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.nro_piso')
                    ->label('Nro Piso')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('nro_piso', 'like', "%{$search}%");
                        });
                    }),
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
                            $query->whereHas('customer', function (Builder $q) use ($data) {
                                $q->where('primary_address', 'like', '%' . $data['value'] . '%');
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
