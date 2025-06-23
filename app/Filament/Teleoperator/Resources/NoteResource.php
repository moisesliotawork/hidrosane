<?php

namespace App\Filament\Teleoperator\Resources;

use App\Filament\Teleoperator\Resources\NoteResource\Pages;
use App\Filament\Teleoperator\Resources\NoteResource\RelationManagers;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\NoteStatus;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('customer_id'),
                Forms\Components\Hidden::make('comercial_id'),

                Forms\Components\Section::make('Información Personal')
                    ->schema([
                        Forms\Components\TextInput::make('first_names')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombres'),

                        Forms\Components\TextInput::make('last_names')
                            ->required()
                            ->maxLength(255)
                            ->label('Apellidos'),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->label('Teléfono'),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->maxLength(20)
                            ->label('Teléfono secundario (opcional)'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->label('Correo electrónico'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(20)
                            ->label('Código postal'),

                        Forms\Components\TextInput::make('primary_address')
                            ->required()
                            ->maxLength(255)
                            ->label('Dirección principal'),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)'),

                        Forms\Components\TextInput::make('parish')
                            ->maxLength(255)
                            ->label('Parroquia (opcional)'),
                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->required()
                            ->native(false)
                            ->live() // Esto permite que el formulario reaccione a cambios
                            ->label('Estado'),

                        Forms\Components\Textarea::make('observations')
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->label('Observaciones'),
                    ]),

                Forms\Components\Section::make('Reprogramación')
                    ->schema([
                        Forms\Components\DatePicker::make('reschedule_date')
                            ->label('Fecha de reprogramación')
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::RESCHEDULED->value),

                        Forms\Components\Textarea::make('reschedule_notes')
                            ->maxLength(65535)
                            ->label('Notas de reprogramación')
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::RESCHEDULED->value),
                    ])->columns(2)
                    ->hidden(fn(Forms\Get $get): bool =>
                        $get('status') !== NoteStatus::RESCHEDULED->value),

                Forms\Components\Section::make('Visita')
                    ->schema([
                        Forms\Components\DatePicker::make('visit_date')
                            ->label('Fecha de visita')
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),

                        Forms\Components\TextInput::make('visit_schedule')
                            ->maxLength(255)
                            ->label('Horario de visita')
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),
                    ])->columns(2)
                    ->hidden(fn(Forms\Get $get): bool =>
                        $get('status') !== NoteStatus::CONTACTED->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([20, 25, 30, 40, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('Nombres y Apellidos'),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->searchable()
                    ->label('Teléfono'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(NoteStatus $state): string => match ($state) {
                        NoteStatus::CONTACTED => 'success',
                        NoteStatus::RESCHEDULED => 'warning',
                        NoteStatus::NULL => 'danger',
                    })
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->sortable()
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(NoteStatus::options())
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}
