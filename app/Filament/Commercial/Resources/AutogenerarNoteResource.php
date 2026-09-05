<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\AutogenerarNoteResource\Pages;
use App\Filament\Commercial\Resources\AutogenerarNoteResource\RelationManagers;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\NoteStatus;
use App\Filament\Support\CustomerPhoneForm;
use App\Support\Filament\FechaNacimientoField;
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use App\Enums\EstadoTerminal;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Support\Colors\Color;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\TernaryFilter;
use App\Models\User;
use Illuminate\Validation\Rule;
use App\Models\Customer;
use Filament\Forms\Components\{
    TextInput,
    DatePicker,
};
use Filament\Forms\Get;
use Filament\Forms\Set;

class AutogenerarNoteResource extends Resource
{
    protected static ?string $model = Note::class;
    protected static ?string $slug = 'autogenerar-notes';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['commercial', 'team_leader', 'sales_manager']);
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
                            ])
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (count(array_filter(explode(' ', trim((string) $value)))) < 2) {
                                            $fail('Debes escribir al menos 2 apellidos del cliente');
                                        }
                                    };
                                },
                            ]),


                        Forms\Components\Grid::make(['default' => 3])
                            ->schema([
                                CustomerPhoneForm::make('phone', 'Teléfono', required: true)
                                    ->extraInputAttributes([
                                        'style' => 'font-weight: 800; color: #1e3a8a;',
                                    ]),

                                CustomerPhoneForm::make('secondary_phone', 'Tlf. 2')
                                    ->extraInputAttributes([
                                        'style' => 'font-weight: 800; color: #1e3a8a;',
                                    ]),

                                CustomerPhoneForm::make('third_phone', 'Tlf. 3')
                                    ->extraInputAttributes([
                                        'style' => 'font-weight: 800; color: #1e3a8a;',
                                    ]),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(['default' => 7])
                            ->schema([
                                FechaNacimientoField::configureDatePicker(
                                    DatePicker::make('fecha_nac')
                                        ->label('Fec. nac.')
                                        ->reactive(),
                                )->validationMessages([
                                    'required' => 'La fecha de nacimiento es obligatoria',
                                ])->columnSpan(3),

                                TextInput::make('age')
                                    ->numeric()
                                    ->label('Edad')
                                    ->readOnly()
                                    ->maxLength(2)
                                    ->maxValue(99)
                                    ->dehydrated(false)
                                    ->extraInputAttributes([
                                        'maxlength' => 2,
                                        'inputmode' => 'numeric',
                                        'style' => 'max-width: 2.75rem; padding-inline: 0.35rem; text-align: center;',
                                    ])
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->label('Correo')
                                    ->columnSpan(3),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 2])
                    ->compact(),

                Forms\Components\Section::make('Dirección de contacto')
                    ->schema([
                        Forms\Components\Grid::make(['default' => 6])
                            ->schema([
                                Forms\Components\TextInput::make('primary_address')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Dirección principal')
                                    ->columnSpan(5),

                                Forms\Components\TextInput::make('nro_piso')
                                    ->required()
                                    ->maxLength(6)
                                    ->label('Nro/Piso')
                                    ->extraInputAttributes([
                                        'maxlength' => 6,
                                        'style' => 'max-width: 4.75rem; padding-inline: 0.35rem; text-align: center;',
                                    ])
                                    ->columnSpan(1),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(['default' => 7])
                            ->schema([
                                Forms\Components\TextInput::make('ciudad')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Ayuntamiento')
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('provincia')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Provincia')
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('postal_code')
                                    ->required()
                                    ->maxLength(5)
                                    ->label('C.P.')
                                    ->extraInputAttributes([
                                        'maxlength' => 5,
                                        'inputmode' => 'numeric',
                                        'style' => 'max-width: 4.25rem; padding-inline: 0.35rem; text-align: center;',
                                    ])
                                    ->columnSpan(1),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)')
                            ->columnSpanFull(),

                    ])
                    ->columns(['default' => 2])
                    ->compact(),

                Forms\Components\Section::make('Tipo de Nota')
                    ->schema([

                        Forms\Components\Select::make('fuente')
                            ->label('Tipo')
                            ->native(false)
                            ->options(FuenteNotas::options())
                            ->default(FuenteNotas::CALLE->value)
                            ->dehydrated(fn() => auth()->user()?->canSeeVipSources())   // solo envía el valor si se muestra
                            ->required(fn() => auth()->user()?->canSeeVipSources()),



                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(NoteStatus::options())
                            ->default(NoteStatus::CONTACTED->value)
                            ->required()
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(['default' => 2])
                    ->compact(),

                Forms\Components\Section::make('Visita')
                    ->schema([
                        Forms\Components\DatePicker::make('visit_date')
                            ->label('Fecha de visita')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->default(now()->addDay()->toDateString()) // Default mañana
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),

                        Forms\Components\Select::make('visit_schedule')
                            ->options(HorarioNotas::options())
                            ->label('Horario')
                            ->default(HorarioNotas::TD->value) // Default TD
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),
                    ])
                    ->columns(['default' => 2])
                    ->compact()
                    ->hidden(fn(Forms\Get $get): bool =>
                        $get('status') !== NoteStatus::CONTACTED->value),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Repeater::make('observations')
                            ->label("")
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('author_id')
                                    ->default(auth()->id()),
                                Forms\Components\Textarea::make('observation')
                                    ->label('')
                                    ->placeholder('Escribe una observación')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Añadir observación')
                            ->defaultItems(1)
                            ->collapsible()
                            ->dehydrated(true)
                            ->collapsed()
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                // Usar el usuario autenticado como fallback
                                $author = auth()->user();

                                // Si hay un author_id en el estado, intentar cargar el usuario
                                if (isset($state['author_id'])) {
                                    $author = \App\Models\User::find($state['author_id']) ?? $author;
                                }

                                // Determinar el rol abreviado
                                $role = 'Tel. Op'; // Por defecto
                                if ($author->hasRole('commercial')) {
                                    $role = 'Com.';
                                } elseif ($author->hasRole('head_of_room')) {
                                    $role = 'Tel. Op';
                                }

                                // Formatear la fecha (usar now() si no hay fecha)
                                $date = now()->format('d/m/y');

                                // Limitar el texto de la observación para que no sea muy largo
                                $observationText = $state['observation'] ?? 'Nueva observación';
                                $limitedObservation = Str::limit($observationText, 30);

                                return "{$author->empleado_id} ({$role}) - {$date}: {$limitedObservation}";
                            }),

                    ])
                    ->compact(),
                Forms\Components\Section::make('Observaciones en Sala')
                    ->visible(function (?Note $record) {
                        if (!$record)
                            return false;

                        // Enum o string, ambos casos:
                        $isEnum = $record->estado_terminal instanceof EstadoTerminal;
                        return $isEnum
                            ? $record->estado_terminal === EstadoTerminal::SALA
                            : (string) $record->estado_terminal === EstadoTerminal::SALA->value;
                    })
                    ->schema([
                        Forms\Components\Placeholder::make('sala_observations_list')
                            ->label('')
                            ->content(function (?Note $record) {
                                if (!$record) {
                                    return new HtmlString('<em>—</em>');
                                }

                                $rows = $record->observacionesSala()
                                    ->with('author')
                                    ->orderByDesc('created_at')
                                    ->get();

                                if ($rows->isEmpty()) {
                                    return new HtmlString('<em>Sin observaciones de sala.</em>');
                                }

                                $items = $rows->map(function ($r) {
                                    $fecha = optional($r->created_at)->format('d/m/Y H:i') ?? '—';
                                    $autor = e(optional($r->author)->name ?? '—');
                                    $txt = e((string) ($r->observation ?? ''));
                                    return "<li><strong>{$fecha}</strong> · {$autor}: {$txt}</li>";
                                })->implode('');

                                return new HtmlString("<ul style='margin:0;padding-left:1.25rem'>{$items}</ul>");
                            }),
                    ])
                    ->compact(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListAutogenerarNotes::route('/'),
            'create' => Pages\CreateAutogenerarNote::route('/create'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['commercial', 'team_leader', 'sales_manager']);

    }
}
