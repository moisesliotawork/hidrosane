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
    protected static ?string $navigationLabel = 'Posicion Global de Cliente';
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
            Section::make('Posición Global del Cliente')
                ->columns(6)
                ->schema([      
                    TextEntry::make('nro_cliente')->label('CLIENTE'),

                    TextEntry::make('name')
                        ->label('NOMBRE')
                        ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names))),

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

                    TextEntry::make('phones')
                        ->label('TELEFONOS')
                        ->state(
                            fn(Customer $r) =>
                            filled($r->secondary_phone)
                            ? "{$r->phone} | {$r->secondary_phone}"
                            : $r->phone
                        ),

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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_cliente')
                    ->label('CLIENTE')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('NOMBRE')
                    ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                    ->searchable(['first_names', 'last_names'])
                    ->wrap(),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phones')
                    ->label('TELEFONOS')
                    ->state(
                        fn(Customer $r) =>
                        filled($r->secondary_phone)
                        ? "{$r->phone} | {$r->secondary_phone}"
                        : $r->phone
                    )
                    ->searchable(['phone', 'secondary_phone']),

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
