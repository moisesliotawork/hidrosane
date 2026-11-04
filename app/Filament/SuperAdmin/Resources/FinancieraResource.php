<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\FinancieraResource\Pages;
use App\Models\Financiera;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinancieraResource extends Resource
{
    protected static ?string $model = Financiera::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'FINANCIERAS';

    protected static ?string $modelLabel = 'financiera';

    protected static ?string $pluralModelLabel = 'Financieras';

    protected static ?string $breadcrumb = 'FINANCIERAS';

    protected static ?string $slug = 'financieras';

    protected static ?string $navigationGroup = 'General';

    protected static ?int $navigationSort = 999;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->rule(function (Forms\Components\TextInput $component): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($component): void {
                        $record = $component->getRecord();
                        $nombre = trim((string) $value);
                        $ocupado = Financiera::query()
                            ->where('codigo', $nombre)
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($ocupado) {
                            $fail('Ya existe una financiera con ese nombre.');
                        }
                    };
                })
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_inclusion')
                    ->label('Fecha inclusión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Financiera $record): string => $record->estaActiva() ? 'ACTIVA' : 'BAJA')
                    ->color(fn (string $state): string => $state === 'ACTIVA' ? 'success' : 'danger'),
            ])
            ->defaultSort('nombre')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar nombre'),
                Tables\Actions\Action::make('baja')
                    ->label('Dar de baja')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('danger')
                    ->visible(fn (Financiera $record): bool => $record->estaActiva())
                    ->requiresConfirmation()
                    ->modalHeading('Dar de baja')
                    ->modalDescription('Dejará de salir al elegir financiera en un contrato. Los contratos que ya la tienen siguen igual.')
                    ->action(function (Financiera $record): void {
                        $record->update(['baja_at' => now()]);
                        Notification::make()->title('Financiera dada de baja')->success()->send();
                    }),
                Tables\Actions\Action::make('activar')
                    ->label('Activar')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->visible(fn (Financiera $record): bool => ! $record->estaActiva())
                    ->requiresConfirmation()
                    ->action(function (Financiera $record): void {
                        $record->update(['baja_at' => null]);
                        Notification::make()->title('Financiera activa')->success()->send();
                    }),
                Tables\Actions\Action::make('borrar')
                    ->label('Borrar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Borrar financiera')
                    ->modalDescription('Solo se borra si ningún contrato la usa. Si hay contratos, dale de baja.')
                    ->action(function (Financiera $record): void {
                        $usos = Venta::withTrashed()->where('financiera', $record->codigo)->count();
                        if ($usos > 0) {
                            Notification::make()
                                ->title('No se puede borrar')
                                ->body($usos === 1
                                    ? 'Hay 1 contrato con esta financiera. Dale de baja.'
                                    : "Hay {$usos} contratos con esta financiera. Dale de baja.")
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                        Notification::make()->title('Financiera borrada')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancieras::route('/'),
        ];
    }
}
