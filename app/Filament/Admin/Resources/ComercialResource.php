<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ComercialResource\Pages;
use App\Filament\Admin\Resources\ComercialResource\RelationManagers;
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

class ComercialResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Ver Tlf COM';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('commercial');
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

                    ->getStateUsing(
                        fn(User $record) =>
                        $record->notesComercial()->where('show_phone', true)->exists()
                    )

                    ->updateStateUsing(function (User $record, bool $state) {
                        $record->notesComercial()->update(['show_phone' => $state]);
                        return $state;
                    })

                    ->afterStateUpdated(function (User $record, bool $state) {
                        $total = $record->notesComercial()->count();

                        Notification::make()
                            ->title($state ? 'Teléfonos ACTIVADOS' : 'Teléfonos DESACTIVADOS')
                            ->body("Se actualizaron {$total} notas.")
                            ->success()
                            ->send();
                    }),


            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activarTelefonos')
                        ->label('Activar teléfonos')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $totalNotas = 0;

                            /** @var \App\Models\User $user */
                            foreach ($records as $user) {
                                // Actualiza TODAS las notas del comercial a show_phone = true
                                $totalNotas += $user->notesComercial()->update(['show_phone' => true]);
                            }

                            Notification::make()
                                ->title('Teléfonos ACTIVADOS')
                                ->body("Se actualizaron {$totalNotas} notas en total.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('desactivarTelefonos')
                        ->label('Desactivar teléfonos')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $totalNotas = 0;

                            /** @var \App\Models\User $user */
                            foreach ($records as $user) {
                                // Actualiza TODAS las notas del comercial a show_phone = false
                                $totalNotas += $user->notesComercial()->update(['show_phone' => false]);
                            }

                            Notification::make()
                                ->title('Teléfonos DESACTIVADOS')
                                ->body("Se actualizaron {$totalNotas} notas en total.")
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
