<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\EstadoTerminal;
use App\Filament\SuperAdmin\Resources\DclaraNotasResource\Pages;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DclaraNotasResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'DclaraNOTAS';
    protected static ?string $modelLabel = 'Declaración';
    protected static ?string $pluralModelLabel = 'DclaraNOTAS';
    protected static ?string $breadcrumb = 'DclaraNOTAS';
    protected static ?string $slug = 'dclara-notas';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('fecha_declaracion')
            ->orderByDesc('fecha_declaracion')
            ->orderByDesc('id')
            ->with([
                'customer:id,first_names,last_names',
                'customer.latestVenta',
                'comercial:id,name,last_name,empleado_id',
                'venta:id,note_id,nro_cliente_adm',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_declaracion', 'desc')
            ->defaultKeySort(false)
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('nro_nota')
                    ->label('Nº Nota')
                    ->badge()
                    ->color(Color::Indigo)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nro_cliente')
                    ->label('Nº Cliente')
                    ->badge()
                    ->color(Color::Gray)
                    ->getStateUsing(function (Note $record): string {
                        $nro = $record->venta?->nro_cliente_adm;

                        if (filled($nro)) {
                            return (string) $nro;
                        }

                        return $record->customer?->latestVenta?->nro_cliente_adm ?? '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('venta', fn(Builder $vq) => $vq->where('nro_cliente_adm', 'like', "%{$search}%"))
                                ->orWhereHas('customer.ventas', fn(Builder $vq) => $vq->where('nro_cliente_adm', 'like', "%{$search}%"));
                        });
                    }),

                TextColumn::make('nombre_cliente')
                    ->label('Nombre del Cliente')
                    ->getStateUsing(fn(Note $record): string => mb_strtoupper(trim(
                        ($record->customer?->first_names ?? '') . ' ' . ($record->customer?->last_names ?? '')
                    )))
                    ->color(Color::Blue)
                    ->weight(FontWeight::Bold)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search): void {
                            $q->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%")
                                ->orWhereRaw(
                                    "CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?",
                                    ["%{$search}%"]
                                );
                        });
                    })
                    ->wrap(),

                TextColumn::make('estado_terminal')
                    ->label('Declara')
                    ->badge()
                    ->formatStateUsing(fn(?EstadoTerminal $state): string => $state?->enNotaLabel() ?? '—')
                    ->color(fn(?EstadoTerminal $state): string => $state?->enNotaColor() ?? 'gray')
                    ->sortable(),

                TextColumn::make('comercial.empleado_id')
                    ->label('ID Comercial')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('comercial_nombre')
                    ->label('Comercial')
                    ->badge()
                    ->color(Color::Sky)
                    ->getStateUsing(fn(Note $record): string => trim(
                        ($record->comercial?->name ?? '') . ' ' . ($record->comercial?->last_name ?? '')
                    ) ?: '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('comercial', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereRaw(
                                    "CONCAT(COALESCE(name,''),' ',COALESCE(last_name,'')) LIKE ?",
                                    ["%{$search}%"]
                                );
                        });
                    }),

                TextColumn::make('fecha_declaracion')
                    ->label('Fecha')
                    ->badge()
                    ->color(Color::Emerald)
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('dia_declaracion')
                    ->label('Día')
                    ->badge()
                    ->color(Color::Amber)
                    ->getStateUsing(fn(Note $record): string => $record->fecha_declaracion
                        ? mb_strtoupper($record->fecha_declaracion->locale('es')->isoFormat('dddd'))
                        : '—'),

                TextColumn::make('hora_declaracion')
                    ->label('Hora')
                    ->badge()
                    ->color(Color::Violet)
                    ->getStateUsing(fn(Note $record): string => $record->fecha_declaracion?->format('H:i') ?? '—'),
            ])
            ->filters([
                Filter::make('fecha_exacta')
                    ->label('Fecha específica')
                    ->form([
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query->when(
                        $data['fecha'] ?? null,
                        fn(Builder $q, $fecha) => $q->whereDate('fecha_declaracion', $fecha)
                    ))
                    ->indicateUsing(fn(array $data): ?string => filled($data['fecha'] ?? null)
                        ? 'Fecha: ' . \Carbon\Carbon::parse($data['fecha'])->format('d/m/Y')
                        : null),

                Tables\Filters\SelectFilter::make('estado_terminal')
                    ->label('Declara')
                    ->options([
                        EstadoTerminal::CONFIRMADO->value => EstadoTerminal::CONFIRMADO->enNotaLabel(),
                        EstadoTerminal::NUL->value => EstadoTerminal::NUL->enNotaLabel(),
                        EstadoTerminal::AUSENTE->value => EstadoTerminal::AUSENTE->enNotaLabel(),
                        EstadoTerminal::VENTA->value => EstadoTerminal::VENTA->enNotaLabel(),
                        EstadoTerminal::SALA->value => EstadoTerminal::SALA->enNotaLabel(),
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDclaraNotas::route('/'),
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

    public static function canViewAny(): bool
    {
        return true;
    }
}
