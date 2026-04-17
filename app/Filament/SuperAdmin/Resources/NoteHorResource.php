<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Models\Note;
use App\Enums\FuenteNotas;
use App\Enums\EstadoTerminal;
use App\Filament\SuperAdmin\Resources\NoteHorResource\Pages;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

        $tnColumn = TextColumn::make('estado_terminal')
            ->label('TN')
            ->badge()
            ->formatStateUsing(function (Note $record): string {
                $et = $record->estado_terminal;
                return $et instanceof EstadoTerminal ? $et->label() : 'S/E';
            })
            ->color(function (Note $record): string {
                return match ($record->estado_terminal) {
                    EstadoTerminal::NUL       => 'danger',
                    EstadoTerminal::VENTA      => 'success',
                    EstadoTerminal::CONFIRMADO => 'warning',
                    EstadoTerminal::SALA       => 'pink',
                    EstadoTerminal::AUSENTE    => 'info',
                    default                    => 'gray',
                };
            })
            ->action(
                Action::make('cycleTN')
                    ->action(function (Note $record): void {
                        $cycle = [
                            ''           => EstadoTerminal::NUL->value,
                            'nulo'       => EstadoTerminal::VENTA->value,
                            'venta'      => EstadoTerminal::CONFIRMADO->value,
                            'confirmado' => EstadoTerminal::SALA->value,
                            'sala'       => EstadoTerminal::AUSENTE->value,
                            'ausente'    => EstadoTerminal::SIN_ESTADO->value,
                        ];

                        $current = $record->getRawOriginal('estado_terminal') ?? '';
                        $next    = $cycle[$current] ?? EstadoTerminal::SIN_ESTADO->value;

                        $record->update([
                            'estado_terminal'   => $next,
                            'fecha_declaracion' => $next === EstadoTerminal::SIN_ESTADO->value ? null : now(),
                        ]);

                        $nextLabel = EstadoTerminal::tryFrom($next)?->label() ?? 'S/E';

                        Notification::make()
                            ->title("TN → {$nextLabel}")
                            ->success()
                            ->send();
                    })
            );

        $existingColumns = array_values($table->getColumns());

        // Reemplazar la columna estado_terminal heredada por la versión ciclable
        $existingColumns = array_map(
            fn($col) => $col->getName() === 'estado_terminal' ? $tnColumn : $col,
            $existingColumns
        );

        array_splice($existingColumns, 1, 0, [$fuenteColumn]);

        return $table->columns(array_values($existingColumns));
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
