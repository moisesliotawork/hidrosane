<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ListaAmanoResource\Pages;
use App\Models\ListaAmano;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListaAmanoResource extends Resource
{
    protected static ?string $model = ListaAmano::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'ListaAmano';

    protected static ?string $navigationGroup = 'RECUPERACION CONTRATOS';

    protected static ?string $modelLabel = 'Lista a mano';

    protected static ?string $pluralModelLabel = 'Lista a mano';

    protected static ?string $slug = 'lista-amano';

    protected static ?int $navigationSort = 96;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('mes_codigo')
                            ->label('Mes código')
                            ->helperText('Ej. Mayo25, Sept25, Enero26')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                                $parsed = ListaAmano::parseMesCodigo((string) $state);
                                if ($parsed === null) {
                                    return;
                                }
                                $set('mes', $parsed['mes']);
                                $set('anio', $parsed['anio']);
                                $set('mes_codigo', $parsed['codigo']);
                            }),
                        Forms\Components\TextInput::make('mes')
                            ->label('Mes (1-12)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(12),
                        Forms\Components\TextInput::make('anio')
                            ->label('Año')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Forms\Components\TextInput::make('pagina')
                            ->label('Página')
                            ->numeric(),
                        Forms\Components\TextInput::make('nro')
                            ->label('Nº línea')
                            ->numeric(),
                        Forms\Components\TextInput::make('cliente')
                            ->label('Cliente')
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('comercial_1')
                            ->label('Comercial 1'),
                        Forms\Components\TextInput::make('comercial_2')
                            ->label('Comercial 2'),
                        Forms\Components\Textarea::make('detalle')
                            ->label('Detalle')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('id')
                            ->label('ID')
                            ->description('ID', position: 'above')
                            ->sortable()
                            ->grow(false)
                            ->weight(FontWeight::SemiBold)
                            ->color('gray'),

                        Tables\Columns\TextColumn::make('cliente')
                            ->label('Cliente')
                            ->description('Cliente', position: 'above')
                            ->sortable()
                            ->searchable()
                            ->weight(FontWeight::Bold)
                            ->wrap()
                            ->grow(),

                        Tables\Columns\TextColumn::make('comerciales')
                            ->label('Comerciales')
                            ->description('Comerciales', position: 'above')
                            ->getStateUsing(function (ListaAmano $record): ?string {
                                $parts = array_values(array_filter([
                                    $record->comercial_1,
                                    $record->comercial_2,
                                ], fn ($v) => filled($v)));

                                return $parts === [] ? null : implode(' · ', $parts);
                            })
                            ->placeholder('—')
                            ->grow(false),

                        Tables\Columns\TextColumn::make('nro')
                            ->label('Nº')
                            ->description('Nº', position: 'above')
                            ->sortable()
                            ->grow(false),

                        Tables\Columns\TextColumn::make('pagina')
                            ->label('Pág.')
                            ->description('Pág.', position: 'above')
                            ->grow(false),

                        Tables\Columns\TextColumn::make('mes_codigo')
                            ->label('Mes')
                            ->description('Mes', position: 'above')
                            ->badge()
                            ->grow(false),
                    ])->from('md'),

                    Tables\Columns\TextColumn::make('detalle')
                        ->label('Detalle')
                        ->description('Detalle', position: 'above')
                        ->wrap()
                        ->color('gray')
                        ->placeholder('—'),

                    Tables\Columns\TextColumn::make('observaciones')
                        ->label('Obs.')
                        ->description('Observaciones', position: 'above')
                        ->wrap()
                        ->color('warning')
                        ->placeholder('')
                        ->visible(fn (?ListaAmano $record): bool => filled($record?->observaciones)),
                ])->space(1),
            ])
            ->filters([
                Filter::make('cliente_nombre')
                    ->label('Buscar por nombre de cliente')
                    ->form([
                        Forms\Components\TextInput::make('q')
                            ->label('Nombre de cliente')
                            ->placeholder('Ej. Carmen Martinez')
                            ->live(debounce: 400),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $q = trim((string) ($data['q'] ?? ''));
                        if ($q === '') {
                            return $query;
                        }

                        return $query->where('cliente', 'like', '%'.$q.'%');
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $q = trim((string) ($data['q'] ?? ''));

                        return $q !== '' ? 'Cliente: '.$q : null;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\Action::make('corregirNombre')
                    ->label('Nombre')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->tooltip('Corregir nombre/apellido (error de manuscrito)')
                    ->modalHeading(fn (ListaAmano $record): string => 'Corregir cliente — nº '.$record->nro)
                    ->modalSubmitActionLabel('Guardar nombre')
                    ->form([
                        Forms\Components\TextInput::make('cliente')
                            ->label('Nombre del cliente')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Corrige errores de extracción del listado manuscrito.'),
                    ])
                    ->fillForm(fn (ListaAmano $record): array => [
                        'cliente' => $record->cliente,
                    ])
                    ->action(function (ListaAmano $record, array $data): void {
                        $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($data['cliente'] ?? '')) ?? '');
                        if ($nombre === '') {
                            return;
                        }

                        $record->forceFill(['cliente' => $nombre])->save();
                    }),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('obs')
                    ->label('Obs.')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones de este registro')
                            ->rows(4)
                            ->required(false),
                    ])
                    ->fillForm(fn (ListaAmano $record): array => [
                        'observaciones' => $record->observaciones,
                    ])
                    ->action(function (ListaAmano $record, array $data): void {
                        $record->forceFill([
                            'observaciones' => $data['observaciones'] ?? null,
                        ])->save();
                    }),
            ])
            ->bulkActions([])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListaAmanos::route('/'),
            'create' => Pages\CreateListaAmano::route('/create'),
            'edit' => Pages\EditListaAmano::route('/{record}/edit'),
        ];
    }
}
