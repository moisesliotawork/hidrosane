<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\NoteAssignmentResource\Pages;
use App\Models\Note;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class NoteAssignmentResource extends Resource
{
    protected static ?string $model = Note::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Asign.Comercial';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
// 1. ID Empleado (Relación)
                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('ID')
                ->badge(),


                // 3. Nº Nota
                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('Nº Nota')
                    ->badge()
                    ->color('yellow'),

                // 4. Cliente
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->weight('bold')
                    ->formatStateUsing(fn($state) => strtoupper($state ?? '---')),

                // 5. Teléfono
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->color('success')
                    ->weight('bold') // Pone el texto en negrilla
                    ->formatStateUsing(fn (string $state): string =>
                        // Esta regex agrupa de 3 en 3 y los separa con un espacio
                    trim(chunk_split($state, 3, ' '))
                    )
                    ->copyable() // Opcional: permite copiar el número al hacer clic
                    ->copyMessage('Teléfono copiado')
                    ->copyMessageDuration(1500),

                // 6. Fecha
                Tables\Columns\TextColumn::make('assignment_date')
                    ->label('Fecha Asig.')
                    ->weight('bold')
                    ->badge()
                    ->color('danger')
                    ->dateTime('d/m/Y  H:i'),





                // 2. Comercial
                Tables\Columns\TextColumn::make('comercial.name')
                    ->label('Comercial')
                    ->badge()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP')
                    ->badge()
                    ->color('info')
                    ->weight('bold'),



            ])
            ->groups([
                // Agrupamos por el campo físico 'comercial_id' para evitar el error de SQL
                Tables\Grouping\Group::make('comercial_id')
                    ->label('Comercial')
                    // Aquí es donde recuperamos el nombre para que se vea bonito
                    ->getTitleFromRecordUsing(fn ($record): string => $record->comercial?->name ?? 'Sin Comercial')
                    ->collapsible(),
            ])
            // Esto activa la separación por defecto
            ->defaultGroup('comercial_id')
           // ->groupingSettingsInHeader(false)
           ->filters([
               // 1. Filtro de Comercial
               \Filament\Tables\Filters\SelectFilter::make('comercial_id')
                   ->label('Comercial')
                   ->relationship('comercial', 'name')
                   ->searchable()

                   ->preload(),

               // 2. Filtro de Fecha Única
               \Filament\Tables\Filters\Filter::make('assignment_date')
                   ->label('Fecha Específica')
                   ->form([
                       \Filament\Forms\Components\DatePicker::make('date')
                           ->label('Seleccionar Fecha')
                           ->native(false)
                           ->displayFormat('d/m/Y')

                           ->closeOnDateSelection(), // Se cierra solo al elegir el día
                   ])
                   ->query(function ($query, array $data) {
                       return $query->when(
                           $data['date'],
                           fn ($query, $date) => $query->whereDate('assignment_date', $date),
                       );
                   })
                   ->indicator(fn (array $data): ?string => $data['date'] ? 'Fecha: ' . \Carbon\Carbon::parse($data['date'])->format('d/m/Y') : null),
           ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(2) // Ahora con 2 columnas queda perfecto (Comercial y Fecha)

            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    ViewAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical') // El icono de los 3 puntitos
                    ->tooltip('Acciones')
                    ->color('gray'),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNoteAssignments::route('/'),
        ];
    }
}
