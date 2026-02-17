<?php

namespace App\Filament\Teleoperator\Pages;

use App\Models\Note;
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
                    'phone' => $this->phone, // ✅ parámetro que viene de la page anterior
                ])),
        ];
    }

    /**
     * Query base de la tabla
     */
    protected function getTableQuery(): Builder
    {
        return Note::query()->with(['customer']);
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
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100]);
    }
}
