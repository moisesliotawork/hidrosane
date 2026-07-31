<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ListContractResource\Pages;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListContractResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'ListContract';

    protected static ?string $modelLabel = 'ListContract';

    protected static ?string $pluralModelLabel = 'ListContract';

    protected static ?string $breadcrumb = 'ListContract';

    protected static ?string $slug = 'list-contract';

    protected static ?int $navigationSort = 95;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'customer:id,first_names,last_names,dni',
                'comercial:id,empleado_id,name,last_name',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('en_app')
                    ->label('EnApp')
                    ->inline(false),

                Forms\Components\Textarea::make('list_descripcion')
                    ->label('Descripción')
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('nro_contr_adm')
                    ->label('# Contrato')
                    ->color(Color::Indigo)
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('id')
                    ->label('ID registro')
                    ->sortable()
                    ->color(Color::Gray),

                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->getStateUsing(fn (Venta $record): string => mb_strtoupper(trim(
                        ($record->customer?->first_names ?? '') . ' ' . ($record->customer?->last_names ?? '')
                    )) ?: '—')
                    ->weight(FontWeight::SemiBold)
                    ->limit(40),

                Tables\Columns\TextColumn::make('customer.dni')
                    ->label('DNI')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('fecha_venta')
                    ->label('Fecha contrato')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('ID empleado')
                    ->placeholder('—')
                    ->color(Color::Pink),

                Tables\Columns\CheckboxColumn::make('en_app')
                    ->label('EnApp')
                    ->alignCenter(),
            ])
            ->filters([
                Filter::make('nro_contrato')
                    ->label('Buscar por #Contrato')
                    ->form([
                        Forms\Components\TextInput::make('nro')
                            ->label('Buscar por #Contrato')
                            ->placeholder('Ej. 1232')
                            ->prefix('#'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $nro = trim((string) ($data['nro'] ?? ''));

                        if ($nro === '') {
                            return $query;
                        }

                        return $query->where('nro_contr_adm', 'like', '%' . $nro . '%');
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $nro = trim((string) ($data['nro'] ?? ''));

                        return $nro !== '' ? '#Contrato: ' . $nro : null;
                    }),

                Filter::make('registro_id')
                    ->label('Buscar por ID del registro')
                    ->form([
                        Forms\Components\TextInput::make('id')
                            ->label('Buscar por ID del registro de contrato')
                            ->placeholder('Ej. 2080')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $id = $data['id'] ?? null;

                        if ($id === null || $id === '') {
                            return $query;
                        }

                        return $query->where('ventas.id', (int) $id);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $id = $data['id'] ?? null;

                        return ($id !== null && $id !== '') ? 'ID registro: ' . $id : null;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Desc.')
                    ->button()
                    ->size(ActionSize::ExtraSmall),
            ])
            ->bulkActions([])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'edit' => Pages\EditListContract::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
