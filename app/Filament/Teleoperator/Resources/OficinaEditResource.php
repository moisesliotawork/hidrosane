<?php

namespace App\Filament\Teleoperator\Resources;

use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use App\Enums\NoteStatus;
use App\Filament\Teleoperator\Resources\OficinaEditResource\Pages;
use App\Filament\Support\CustomerPhoneForm;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OficinaEditResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Oficina Edit';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

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

                        CustomerPhoneForm::make('phone', 'Teléfono', required: true),

                        CustomerPhoneForm::make('secondary_phone', 'Teléfono secundario (opcional)'),

                        Forms\Components\TextInput::make('edadTelOp')
                            ->numeric()
                            ->label('Edad Tel. Op')
                            ->maxValue(120)
                            ->minValue(0),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->label('Correo electrónico'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('primary_address')
                            ->required()
                            ->maxLength(255)
                            ->label('Dirección principal'),

                        Forms\Components\TextInput::make('nro_piso')
                            ->required()
                            ->maxLength(20)
                            ->label('No. y Piso'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(5)
                            ->minLength(5)
                            ->label('Código Postal'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ayuntamiento/Localidad'),

                        Forms\Components\Select::make('provincia')
                            ->label('Provincia')
                            ->required()
                            ->options([
                                'Pontevedra' => 'Pontevedra',
                                'A Coruña' => 'A Coruña',
                                'Orense' => 'Orense',
                                'Lugo' => 'Lugo',
                            ]),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)'),
                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->required()
                            ->native(false)
                            ->live()
                            ->label('Estado'),

                        Forms\Components\DatePicker::make('visit_date')
                            ->label('Fecha de visita')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),

                        Forms\Components\Select::make('visit_schedule')
                            ->options(HorarioNotas::options())
                            ->label('Horario de visita')
                            ->native(false)
                            ->searchable()
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),
                    ])->columns(2),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Repeater::make('observations')
                            ->label('')
                            ->schema([
                                Forms\Components\Hidden::make('author_id')
                                    ->default(auth()->id()),
                                Forms\Components\Textarea::make('observation')
                                    ->label('')
                                    ->placeholder('Escribe una observación')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Añadir observación')
                            ->defaultItems(0)
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                $author = auth()->user();
                                if (isset($state['author_id'])) {
                                    $author = \App\Models\User::find($state['author_id']) ?? $author;
                                }
                                $date = now()->format('d/m/y');
                                $observationText = $state['observation'] ?? 'Nueva observación';
                                return "{$author->empleado_id} - {$date}: " . Str::limit($observationText, 30);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('# Nota')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Nombre Cliente'),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Teléfono')
                    ->html()
                    ->formatStateUsing(fn($state) => '<span style="font-weight:bold;">' .
                        chunk_split(str_replace(' ', '', (string) $state), 3, ' ') . '</span>'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(NoteStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Fecha visita')
                    ->date('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->emptyStateHeading('Sin resultados')
            ->emptyStateDescription('Usa el botón BUSCAR TLF para encontrar una nota por teléfono.')
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOficinaEdit::route('/'),
            'edit'  => Pages\EditOficinaEdit::route('/{record}/edit'),
        ];
    }
}
