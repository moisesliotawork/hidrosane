<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\AbsentHistoryResource\Pages;
use App\Filament\SuperAdmin\Resources\AbsentHistoryResource\RelationManagers;
use App\Models\AbsentHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Colors\Color;

class AbsentHistoryResource extends Resource
{
    protected static ?string $model = AbsentHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Ausentes';
    protected static ?string $pluralModelLabel = 'Ausentes';
    protected static ?string $modelLabel = 'Ausente';
    protected static ?string $slug = 'ausentes';

    public static function form(Form $form): Form
    {
        // No usamos formularios (solo lectura)
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn() => static::getEloquentQuery())
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                // nro_nota (de la nota relacionada)
                Tables\Columns\TextColumn::make('note.nro_nota')
                    ->label('# Nota')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable()
                    ->searchable(),

                // Nombre del cliente (first_names + last_names usando accessor name en Customer)
                Tables\Columns\TextColumn::make('note.customer.name')
                    ->label('Cliente')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('note.customer', function (Builder $q) use ($search) {
                            $q->where('customers.first_names', 'like', "%{$search}%")
                                ->orWhere('customers.last_names', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,'')) LIKE ?", ["%{$search}%"]);
                        });
                    }),

                // Código TelOP (teleoperadora que generó la nota)
                Tables\Columns\TextColumn::make('note.user.empleado_id')
                    ->label('TelOP')
                    ->badge()
                    ->color(Color::Pink)
                    ->sortable(),

                // Código Comercial
                Tables\Columns\TextColumn::make('note.comercial.empleado_id')
                    ->label('Comercial')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable(),

                // CP del cliente
                Tables\Columns\TextColumn::make('note.customer.postalCode.code')
                    ->label('CP')
                    ->sortable()
                    ->searchable(),

                // Horario de visita (de la nota)
                Tables\Columns\TextColumn::make('note.visit_schedule')
                    ->label('Horario')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                // Fecha de asignación del comercial
                Tables\Columns\TextColumn::make('note.assignment_date')
                    ->label('Asig.')
                    ->date('d/m/Y')
                    ->sortable(),

                // Campos propios del historial de ausencia:
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha Aus')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                Tables\Columns\TextColumn::make('hora')
                    ->label('Hora Aus')
                    ->badge()
                    ->color(Color::Green)
                    ->sortable(),

                Tables\Columns\TextColumn::make('latitud')
                    ->label('Latitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('longitud')
                    ->label('Longitud')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mapa')
                    ->label('Mapa')
                    ->state(fn($record) => ($record->latitud && $record->longitud) ? 'Abrir mapa' : '—')
                    ->url(fn($record) => ($record->latitud && $record->longitud)
                        ? "https://www.google.com/maps?q={$record->latitud},{$record->longitud}"
                        : null, shouldOpenInNewTab: true)
                    ->badge()
                    ->color(fn($record) => ($record->latitud && $record->longitud) ? Color::Green : Color::Gray),
            ])
            ->filters([

            ])
            ->actions([])       // Solo lectura
            ->bulkActions([]);  // Solo lectura
    }

    public static function getEloquentQuery(): Builder
    {
        // Cargamos relaciones para evitar N+1 y permitir ordenar/filtrar
        return parent::getEloquentQuery()
            ->with([
                'note:id,nro_nota,user_id,comercial_id,customer_id,visit_schedule,assignment_date',
                'note.user:id,empleado_id',
                'note.comercial:id,empleado_id',
                'note.customer:id,first_names,last_names,postal_code_id',
                'note.customer.postalCode:id,code',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbsentHistories::route('/'),
            'create' => Pages\CreateAbsentHistory::route('/create'),
            'edit' => Pages\EditAbsentHistory::route('/{record}/edit'),
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
