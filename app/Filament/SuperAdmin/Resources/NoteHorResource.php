<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Models\Note;
use App\Enums\FuenteNotas;
use App\Filament\SuperAdmin\Resources\NoteHorResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;

class NoteHorResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel  = 'Notas HOR';
    protected static ?string $modelLabel       = 'Nota HOR';
    protected static ?string $pluralModelLabel = 'Notas HOR';
    protected static ?string $breadcrumb       = 'Notas HOR';
    protected static ?string $slug             = 'notas-hor';

    public static function form(Form $form): Form
    {
        return \App\Filament\HeadOfRoom\Resources\NoteResource::form($form);
    }

    public static function table(Table $table): Table
    {
        // Delega al NoteResource de HeadOfRoom y luego elimina el
        // headerAction de BuscarCliente que no existe en este panel.
        $table = \App\Filament\HeadOfRoom\Resources\NoteResource::table($table)
            ->headerActions([]);

        $fuenteColumn = TextColumn::make('fuente')
            ->label('Fuente')
            ->badge()
            ->color(fn($state) => $state instanceof FuenteNotas ? $state->getColor() : 'gray')
            ->formatStateUsing(fn($state) => $state instanceof FuenteNotas ? $state->getLabel() : $state)
            ->action(
                Action::make('rotateFuente')
                    ->action(function (Note $record): void {
                        $cases   = FuenteNotas::cases();
                        $current = $record->fuente instanceof FuenteNotas ? $record->fuente : null;
                        $idx     = $current !== null ? array_search($current, $cases, true) : false;
                        $next    = $cases[$idx !== false ? ($idx + 1) % count($cases) : 0];
                        $record->update(['fuente' => $next->value]);
                    })
            );

        $existingColumns = array_values($table->getColumns());
        array_splice($existingColumns, 1, 0, [$fuenteColumn]);

        return $table->columns($existingColumns);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNoteHors::route('/'),
            'edit'  => Pages\EditNoteHor::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}
