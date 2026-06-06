<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;
use App\Models\Customer;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class DuplicadosResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Duplicados';
    protected static ?string $modelLabel = 'Duplicado';
    protected static ?string $pluralModelLabel = 'Duplicados';
    protected static ?string $slug = 'duplicados';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $phoneFields = ['phone', 'secondary_phone', 'third_phone', 'phone1_commercial', 'phone2_commercial'];

        return parent::getEloquentQuery()
            ->with([
                'latestNote.user:id,name,last_name,empleado_id',
            ])
            ->withCount('ventas')
            ->whereIn('id', function ($sub) use ($phoneFields) {
                $sub->select('c1.id')
                    ->from('customers as c1')
                    ->join('customers as c2', 'c1.id', '!=', 'c2.id')
                    ->whereRaw(
                        "TRIM(CONCAT(COALESCE(c1.first_names,''),' ',COALESCE(c1.last_names,''))) "
                        . "= TRIM(CONCAT(COALESCE(c2.first_names,''),' ',COALESCE(c2.last_names,'')))"
                    )
                    ->whereRaw('c1.dni = c2.dni')
                    ->whereRaw("c1.dni IS NOT NULL AND c1.dni != ''")
                    ->where(function ($q) use ($phoneFields) {
                        foreach ($phoneFields as $f1) {
                            foreach ($phoneFields as $f2) {
                                $q->orWhereRaw(
                                    "(c1.`{$f1}` IS NOT NULL AND c1.`{$f1}` != '' AND c1.`{$f1}` = c2.`{$f2}`)"
                                );
                            }
                        }
                    });
            })
            ->orderByRaw("
                GREATEST(
                    COALESCE((SELECT MAX(fecha_venta) FROM ventas WHERE ventas.customer_id = customers.id), '1970-01-01'),
                    COALESCE((SELECT MAX(assignment_date) FROM notes WHERE notes.customer_id = customers.id), '1970-01-01'),
                    COALESCE((SELECT MAX(notes.created_at) FROM notes WHERE notes.customer_id = customers.id), '1970-01-01'),
                    customers.created_at
                ) DESC
            ");
    }

    public static function table(Table $table): Table
    {
        $fmt = fn(?string $p): string => $p
            ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3))
            : '—';

        return $table
            ->columns([
                TextColumn::make('nombre_cliente')
                    ->label('Nombre del Cliente')
                    ->getStateUsing(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                    ->weight(FontWeight::Bold)
                    ->color(Color::Green)
                    ->searchable(['first_names', 'last_names'])
                    ->wrap(),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nro_nota')
                    ->label('Nro. Nota')
                    ->badge()
                    ->color(Color::Indigo)
                    ->getStateUsing(fn(Customer $r) => $r->latestNote?->nro_nota ?? '—'),

                TextColumn::make('id')
                    ->label('ID Cliente')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Teléfono 1')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone))
                    ->color(Color::Amber)
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('secondary_phone')
                    ->label('Teléfono 2')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->secondary_phone))
                    ->color(Color::Amber)
                    ->searchable(),

                TextColumn::make('third_phone')
                    ->label('Teléfono 3')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->third_phone))
                    ->color(Color::Amber)
                    ->searchable(),

                TextColumn::make('phone1_commercial')
                    ->label('Tel. Comercial 1')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone1_commercial))
                    ->color(Color::Teal)
                    ->searchable(),

                TextColumn::make('phone2_commercial')
                    ->label('Tel. Comercial 2')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone2_commercial))
                    ->color(Color::Teal)
                    ->searchable(),

                TextColumn::make('fecha_asignacion')
                    ->label('Fecha Asignación')
                    ->getStateUsing(function (Customer $r): string {
                        $date = $r->latestNote?->assignment_date;
                        if (!$date) return '—';
                        return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderBy(
                            \App\Models\Note::select('assignment_date')
                                ->whereColumn('notes.customer_id', 'customers.id')
                                ->orderBy('id', 'desc')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('teleoperadora')
                    ->label('Teleoperadora')
                    ->getStateUsing(function (Customer $r): string {
                        $user = $r->latestNote?->user;
                        if (!$user) return '—';
                        return trim($user->name . ' ' . $user->last_name);
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('latestNote.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('fuente')
                    ->label('Fuente')
                    ->badge()
                    ->getStateUsing(function (Customer $r): string {
                        $fuente = $r->latestNote?->fuente;
                        if (!$fuente) return '—';
                        if ($fuente instanceof \App\Enums\FuenteNotas) {
                            return $fuente->getLabel();
                        }
                        $enum = \App\Enums\FuenteNotas::tryFrom((string) $fuente);
                        return $enum ? $enum->getLabel() : (string) $fuente;
                    })
                    ->color(function (Customer $r): string {
                        $fuente = $r->latestNote?->fuente;
                        if (!$fuente) return 'gray';
                        if (!($fuente instanceof \App\Enums\FuenteNotas)) {
                            $fuente = \App\Enums\FuenteNotas::tryFrom((string) $fuente);
                        }
                        return $fuente?->getColor() ?? 'gray';
                    }),

                TextColumn::make('ventas_count')
                    ->label('Nro. Ventas')
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDuplicados::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
