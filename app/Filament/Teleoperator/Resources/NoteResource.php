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
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

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
                            ->label('Nombres')
                            ->validationMessages([
                                'required' => 'Los nombres son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('last_names')
                            ->required()
                            ->maxLength(255)
                            ->label('Apellidos')
                            ->validationMessages([
                                'required' => 'Los apellidos son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->label('Teléfono')
                            ->validationMessages([
                                'required' => 'El telefono es obligatorio',
                            ]),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->maxLength(20)
                            ->label('Teléfono secundario (opcional)'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->label('Correo electrónico'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(20)
                            ->label('Código postal')
                            ->validationMessages([
                                'required' => 'El codigo postal es obligatorio',
                            ]),

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

                        Forms\Components\Select::make('fuente')
                            ->options(FuenteNotas::options())
                            ->required()
                            ->native(false)
                            ->label('Fuente de la nota')
                            ->hidden(fn(string $operation): bool => $operation === 'create'),

                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->required()
                            ->native(false)
                            ->live()
                            ->label('Estado')
                            ->validationMessages([
                                'required' => 'El estado es obligatorio',
                            ]),

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



                        Forms\Components\Select::make('visit_schedule')
                            ->options(HorarioNotas::options()) // Usa las opciones del enum
                            ->label('Horario de visita')
                            ->native(false) // Mejora la UI en desktop
                            ->searchable() // Permite búsqueda
                            ->required() // Si es obligatorio
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value)
                        ,
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
                Tables\Columns\TextColumn::make('fuente')
                    ->badge()
                    ->color(fn(FuenteNotas $state): string => $state->getColor())
                    ->formatStateUsing(fn(FuenteNotas $state): string => $state->getLabel())
                    ->label('Tipo'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('Nombres y Apellidos'),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->searchable()
                    ->label('Teléfono')
                    ->html()
                    ->formatStateUsing(fn($state) => '<span style="font-size: 1rem; font-weight: bold;">' . $state . '</span>'),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP'),

                Tables\Columns\TextColumn::make('created_at')
                    ->date('j F Y')
                    ->sortable()
                    ->label("Fech/Creación"),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(NoteStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->sortable()
                    ->label('Estado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(NoteStatus::options())
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                //Tables\Actions\Action::make('changeFuente')
                //    ->form([
                //        Forms\Components\Select::make('fuente')
                //            ->options(FuenteNotas::options())
                //            ->required()
                //            ->native(false)
                //            ->label('Nueva Fuente')
                //    ])
                //    ->action(function (Note $record, array $data): void {
                //        $record->fuente = $data['fuente'];
                //        $record->save();
                //    })
                //    ->icon('heroicon-o-pencil-square')
                //    ->modalHeading('Cambiar Fuente')
                //    ->modalButton('Guardar')
                //    ->label('Cambiar Fuente')
            ])
            ->bulkActions([

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
            ->where('user_id', auth()->id())
            ->whereNull('comercial_id');
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
