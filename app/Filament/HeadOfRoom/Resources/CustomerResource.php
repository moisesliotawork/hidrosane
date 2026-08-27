<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\CustomerResource\Pages;
use App\Filament\HeadOfRoom\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Pos.Gl:cliente';
    protected static ?string $modelLabel = 'Posicion Global de Cliente';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Datos Personales del Cliente')
                ->columns(6)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre de Cliente')
                        ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                        ->color('success')
                        ->weight(\Filament\Support\Enums\FontWeight::ExtraBold),

                    TextEntry::make('nro_cliente')->label('ID/Cliente'),

                    TextEntry::make('dni')
                        ->label('DNI'),

                    TextEntry::make('primary_address')
                        ->label('DOMICILIO')
                        ->state(function (Customer $r) {
                            return "{$r->primary_address}, {$r->nro_piso} - {$r->ciudad} ({$r->postal_code})";
                        })
                        ->columnSpan(2),

                    TextEntry::make('secondary_address')
                        ->label('DOMICILIO 2')
                        ->visible(fn(Customer $r) => filled($r->secondary_address)),

                    TextEntry::make('fecha_nac')
                        ->label('F. Nac')
                        ->state(fn (Customer $r) => $r->fechaNacDisplay('d/m/Y') ?? '—')
                        ->suffix(function (Customer $r) {
                            $fechaNac = $r->safeFechaNac();

                            if ($fechaNac === null) {
                                return null;
                            }

                            $d = $fechaNac->diff(now());

                            return " ({$d->y} años {$d->m} meses y {$d->d} días)";
                        }),

                ])
                ->columnSpan(6),

            // Alerta grande cuando el cliente está inhabilitado
            Section::make('')
                ->columnSpan(6)
                ->visible(fn(Customer $r) => (bool) $r->inhabilitado)
                ->schema([
                    TextEntry::make('inhabilitado_alert')
                        ->label('')
                        ->state(fn() => '')
                        ->extraAttributes(['class' => 'hidden'])
                        ->helperText(new HtmlString(
                            '<div style="background:#7f1d1d;border:3px solid #dc2626;border-radius:12px;padding:24px 32px;text-align:center;">'
                            . '<div style="font-size:64px;line-height:1;">☠️</div>'
                            . '<div style="color:#fca5a5;font-size:22px;font-weight:900;margin-top:12px;letter-spacing:1px;">'
                            . 'ESTE CLIENTE YA NO PUEDE SER CONTACTADO POR LA EMPRESA</div>'
                            . '<div style="color:#f87171;font-size:18px;font-weight:700;margin-top:8px;">ESTÁ DESCARTADO</div>'
                            . '</div>'
                        )),
                ]),

            Section::make('Teléfonos')
                ->columns(2)
                ->schema([
                    TextEntry::make('all_phones')
                        ->label('TELÉFONOS CLIENTE')
                        ->state(function (Customer $r): string {
                            $fmt = fn(?string $p): string => $p ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3)) : '';
                            return collect([$r->phone, $r->secondary_phone, $r->third_phone])
                                ->filter()->map($fmt)->join('   |   ') ?: '—';
                        })
                        ->color('warning')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold),

                    TextEntry::make('all_phones_commercial')
                        ->label('TELÉFONOS COMERCIAL')
                        ->state(function (Customer $r): string {
                            $fmt = fn(?string $p): string => $p ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3)) : '';
                            return collect([$r->phone1_commercial, $r->phone2_commercial])
                                ->filter()->map($fmt)->join('   |   ') ?: '—';
                        })
                        ->color('warning')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->visible(fn(Customer $r) => filled($r->phone1_commercial) || filled($r->phone2_commercial)),
                ])
                ->columnSpan(6),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre de Cliente')
                    ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                    ->searchable(['first_names', 'last_names'])
                    ->wrap(),

                TextColumn::make('nro_cliente')
                    ->label('ID/Cliente')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ventas_count')
                    ->label('#VENTAS')
                    ->state(fn(Customer $r): int => $r->ventas()->count())
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray'),


                TextColumn::make('phones')
                    ->label('TELEFONOS')
                    ->state(function (Customer $r): string {
                        $fmt = fn(?string $p): string => $p ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3)) : '';
                        return collect([$r->phone, $r->secondary_phone, $r->third_phone])
                            ->filter()->map($fmt)->join(' | ') ?: '—';
                    })
                    ->color('warning')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable(['phone', 'secondary_phone']),

                TextColumn::make('phones_commercial')
                    ->label('TEL. COMERCIAL')
                    ->state(function (Customer $r): string {
                        $fmt = fn(?string $p): string => $p ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3)) : '';
                        return collect([$r->phone1_commercial, $r->phone2_commercial])
                            ->filter()->map($fmt)->join(' | ') ?: '—';
                    })
                    ->color('warning')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('phone1_commercial', 'like', "%{$search}%")
                                     ->orWhere('phone2_commercial', 'like', "%{$search}%");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inhabilitado')
                    ->label('INHAB')
                    ->state(fn(Customer $r) => $r->inhabilitado ? '☠️' : '')
                    ->color(fn(Customer $r) => $r->inhabilitado ? 'danger' : null)
                    ->weight(fn(Customer $r) => $r->inhabilitado ? \Filament\Support\Enums\FontWeight::Bold : null)
                    ->alignCenter()
                    ->sortable(),

            ])
            ->filters([
                Filter::make('nro_nota')
                    ->label('No. NOTA')
                    ->form([
                        TextInput::make('value')
                            ->label('No. NOTA')
                            ->placeholder('Buscar por número de nota')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = trim((string) ($data['value'] ?? ''));

                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereHas(
                            'notes',
                            fn (Builder $q) => $q->where('nro_nota', $value)
                        );
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null)
                        ? 'No. NOTA: ' . $data['value']
                        : null),

                Filter::make('dni')
                    ->label('DNI')
                    ->form([
                        TextInput::make('value')
                            ->label('DNI')
                            ->placeholder('Buscar por DNI')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = trim((string) ($data['value'] ?? ''));

                        if ($value === '') {
                            return $query;
                        }

                        return $query->where('dni', 'like', "%{$value}%");
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null)
                        ? 'DNI: ' . $data['value']
                        : null),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(), // Ver “Vision Global del Cliente”
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // sin acciones destructivas por ahora
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
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }

}
