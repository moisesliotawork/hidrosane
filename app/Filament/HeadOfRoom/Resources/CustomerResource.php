<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\CustomerResource\Pages;
use App\Filament\HeadOfRoom\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
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
                        ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names))),

                    TextEntry::make('nro_cliente')->label('ID/Cliente'),

                    TextEntry::make('dni')
                        ->label('DNI'),

                        TextEntry::make('primary_address')
                        ->label('DOMICILIO')
                        ->state(function (Customer $r) {
                            return "{$r->primary_address}, {$r->nro_piso} - {$r->ciudad} ({$r->postal_code})";
                        })
                        ->columnSpan(2),

                   // TextEntry::make('primary_address')->label('DOMICILIO'),

                    TextEntry::make('secondary_address')
                        ->label('DOMICILIO 2')
                        ->visible(fn(Customer $r) => filled($r->secondary_address)),

                    TextEntry::make('fecha_nac')
                        ->label('F. Nac')
                        ->state(
                            fn(Customer $r) =>
                            blank($r->fecha_nac)
                            ? '—'
                            : Carbon::parse($r->fecha_nac)->format('d/m/Y')
                        )
                        ->suffix(function (Customer $r) {
                            if (blank($r->fecha_nac))
                                return null;
                            $d = Carbon::parse($r->fecha_nac)->diff(now());
                            return " ({$d->y} años {$d->m} meses y {$d->d} días)";
                        }),

                ])
                ->columnSpan(6),

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

            ])
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
