<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ReasignadoOkResource\Pages;
use App\Filament\SuperAdmin\Resources\ReasignadoOkResource\RelationManagers;
use App\Models\NoteReassignmentBatch;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class ReasignadoOkResource extends Resource
{
    protected static ?string $model = NoteReassignmentBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'REASIGNADO OK';
    protected static ?string $pluralModelLabel = 'REASIGNADO OK';
    protected static ?string $modelLabel = 'Reasignación';
    protected static ?string $slug = 'reasignado-ok';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Detalle de la Reasignación')
                    ->schema([
                        Components\TextEntry::make('author_empleado')
                            ->label('ID Empleado')
                            ->badge()
                            ->color(Color::Gray)
                            ->getStateUsing(fn(NoteReassignmentBatch $record) => $record->author?->empleado_id ?? '—'),

                        Components\TextEntry::make('author_name')
                            ->label('Nombre / Comercial')
                            ->weight(FontWeight::Bold)
                            ->getStateUsing(fn(NoteReassignmentBatch $record) =>
                                trim(($record->author?->name ?? '') . ' ' . ($record->author?->last_name ?? ''))
                            ),

                        Components\TextEntry::make('reassigned_at')
                            ->label('Fecha y Hora')
                            ->dateTime('d/m/Y H:i:s'),

                        Components\TextEntry::make('receptor')
                            ->label('Comercial receptor')
                            ->badge()
                            ->color(Color::Blue)
                            ->getStateUsing(function (NoteReassignmentBatch $record) {
                                if ($record->to_reten) {
                                    return 'RETÉN';
                                }
                                $comercial = $record->toComercial;
                                if (!$comercial) return '—';
                                return trim("{$comercial->empleado_id} {$comercial->name} {$comercial->last_name}");
                            }),

                        Components\TextEntry::make('logs_count')
                            ->label('Número de notas')
                            ->badge()
                            ->color(Color::Indigo)
                            ->getStateUsing(fn(NoteReassignmentBatch $record) => $record->logs()->count()),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reassigned_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('author_empleado_id')
                    ->label('ID Empleado')
                    ->badge()
                    ->color(Color::Gray)
                    ->getStateUsing(fn(NoteReassignmentBatch $record) => $record->author?->empleado_id ?? '—')
                    ->sortable(query: fn($query, $direction) =>
                        $query->join('users as u_author', 'u_author.id', '=', 'note_reassignment_batches.author_id')
                              ->orderBy('u_author.empleado_id', $direction)
                    )
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('author', fn($q) => $q->where('empleado_id', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('author_name')
                    ->label('Nombre / Comercial')
                    ->weight(FontWeight::Bold)
                    ->color(Color::Pink)
                    ->getStateUsing(fn(NoteReassignmentBatch $record) =>
                        trim(($record->author?->name ?? '') . ' ' . ($record->author?->last_name ?? ''))
                    )
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('author', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('reassigned_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                Tables\Columns\TextColumn::make('logs_count')
                    ->label('Nº Notas')
                    ->badge()
                    ->color(Color::Indigo)
                    ->counts('logs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receptor')
                    ->label('Comercial receptor')
                    ->badge()
                    ->color(Color::Blue)
                    ->getStateUsing(function (NoteReassignmentBatch $record) {
                        if ($record->to_reten) {
                            return 'RETÉN';
                        }
                        $comercial = $record->toComercial;
                        if (!$comercial) return '—';
                        return trim("{$comercial->empleado_id} {$comercial->name} {$comercial->last_name}");
                    }),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver notas')
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'author:id,name,last_name,empleado_id',
                'toComercial:id,name,last_name,empleado_id',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReasignadoOk::route('/'),
            'view'  => Pages\ViewReasignadoOk::route('/{record}'),
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
