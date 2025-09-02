<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\ComercialResource\Pages;
use App\Filament\Gerente\Resources\ComercialResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Component as Livewire;

class ComercialResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Ver Tlf COM';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->role('commercial')
            ->withExists([
                'notesComercial as has_any_show_phone' => fn($q) => $q->where('show_phone', true),
                'roles as is_team_leader' => fn($q) => $q->where('name', 'team_leader'),
            ]);
    }



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->label('Nombre'),
                TextColumn::make('email')->label('Email'),

                ToggleColumn::make('phones_visible')
                    ->label('Teléfonos')

                    // Estado visible según notas
                    ->getStateUsing(fn(User $record) => (bool) ($record->has_any_show_phone ?? false))

                    // (Opcional) Deshabilitar el toggle en UI si es líder
                    ->disabled(fn(User $record) => (bool) ($record->is_team_leader ?? false))

                    // Guardar cambios solo si NO es líder
                    ->updateStateUsing(function (User $record, bool $state) {
                        if ($record->is_team_leader ?? false) {
                            // Aviso y devolvemos el estado anterior (no cambiamos nada)
                            Notification::make()
                                ->title('Acción no permitida')
                                ->body('Este usuario es líder de equipo y no se puede cambiar la visibilidad de teléfonos desde aquí.')
                                ->danger()
                                ->send();

                            // Reponer el estado previo (derivado de sus notas):
                            return (bool) ($record->has_any_show_phone ?? false);
                        }

                        // Actualiza todas las notas del comercial
                        $record->notesComercial()->update(['show_phone' => $state]);
                        return $state;
                    })

                    // Notificar solo cuando SÍ se actualizó
                    ->afterStateUpdated(function (User $record, bool $state) {
                        if ($record->is_team_leader ?? false) {
                            return; // ya se notificó arriba; no hubo cambios
                        }

                        $total = $record->notesComercial()->count();

                        // refresco lógico del flag precargado
                        $record->unsetRelation('notesComercial');
                        $record->setAttribute('has_any_show_phone', $state);

                        Notification::make()
                            ->title($state ? 'Teléfonos ACTIVADOS' : 'Teléfonos DESACTIVADOS')
                            ->body("Se actualizaron {$total} notas.")
                            ->success()
                            ->send();
                    })

            ])
            ->filters([
                //
            ])
            ->actions([

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\BulkAction::make('activarTelefonos')
                        ->label('Activar teléfonos')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->deselectRecordsAfterCompletion() // opcional, UX
                        ->action(function (Collection $records, Livewire $livewire) {
                            $totalNotas = 0;
                            $saltados = 0;

                            /** @var \App\Models\User $user */
                            foreach ($records as $user) {
                                $isLeader = (bool) ($user->is_team_leader ?? $user->roles()->where('name', 'team_leader')->exists());
                                if ($isLeader) {
                                    $saltados++;
                                    continue;
                                }

                                // Actualiza DB
                                $totalNotas += $user->notesComercial()->update(['show_phone' => true]);

                                // Refleja en memoria para el render inmediato del toggle:
                                $user->setAttribute('has_any_show_phone', true);
                            }

                            // Forzamos que la tabla se repinte (consulta fresh con withExists)
                            $livewire->dispatch('refreshTable');

                            Notification::make()
                                ->title('Teléfonos ACTIVADOS')
                                ->body("Se actualizaron {$totalNotas} notas en total. Usuarios omitidos por ser líderes de equipo: {$saltados}.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('desactivarTelefonos')
                        ->label('Desactivar teléfonos')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, Livewire $livewire) {
                            $totalNotas = 0;
                            $saltados = 0;

                            /** @var \App\Models\User $user */
                            foreach ($records as $user) {
                                $isLeader = (bool) ($user->is_team_leader ?? $user->roles()->where('name', 'team_leader')->exists());
                                if ($isLeader) {
                                    $saltados++;
                                    continue;
                                }

                                // Actualiza DB
                                $totalNotas += $user->notesComercial()->update(['show_phone' => false]);

                                // Refleja en memoria para el render inmediato del toggle:
                                $user->setAttribute('has_any_show_phone', false);
                            }

                            // Forzar repintado de la tabla
                            $livewire->dispatch('refreshTable');

                            Notification::make()
                                ->title('Teléfonos DESACTIVADOS')
                                ->body("Se actualizaron {$totalNotas} notas en total. Usuarios omitidos por ser líderes de equipo: {$saltados}.")
                                ->success()
                                ->send();
                        }),

                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComercials::route('/'),
            'create' => Pages\CreateComercial::route('/create'),
            'edit' => Pages\EditComercial::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdited(): bool
    {
        return false;
    }
}
