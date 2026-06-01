<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TlfChangeResource\Pages;
use App\Models\Venta;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class TlfChangeResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationLabel = 'TLF CHANGE';
    protected static ?string $modelLabel = 'TLF Change';
    protected static ?string $pluralModelLabel = 'TLF Change';
    protected static ?string $slug = 'tlf-change';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['note', 'customer']);
    }

    public static function table(Table $table): Table
    {
        $fmt = fn(?string $p): string => $p
            ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3))
            : '';

        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([

                TextColumn::make('note.nro_nota')
                    ->label('Nº Nota')
                    ->badge()
                    ->color(Color::Pink)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('customer.full_name')
                    ->label('Cliente')
                    ->getStateUsing(fn(Venta $record) => mb_strtoupper(
                        trim(($record->customer->first_names ?? '') . ' ' . ($record->customer->last_names ?? ''))
                    ))
                    ->weight(FontWeight::Bold)
                    ->color(Color::Amber)
                    ->searchable(query: fn(Builder $query, string $search) =>
                        $query->whereHas('customer', fn($q) =>
                            $q->where('first_names', 'like', "%{$search}%")
                              ->orWhere('last_names', 'like', "%{$search}%")
                        )
                    ),

                TextInputColumn::make('tel_1')
                    ->label('TEL 1')
                    ->getStateUsing(fn(Venta $record) => $record->customer?->phone)
                    ->updateStateUsing(fn(Venta $record, ?string $state) =>
                        $record->customer?->update(['phone' => $state])
                    )
                    ->formatStateUsing($fmt)
                    ->extraAttributes(['class' => 'font-bold']),

                TextInputColumn::make('tel_2')
                    ->label('TEL 2')
                    ->getStateUsing(fn(Venta $record) => $record->customer?->secondary_phone)
                    ->updateStateUsing(fn(Venta $record, ?string $state) =>
                        $record->customer?->update(['secondary_phone' => $state])
                    )
                    ->formatStateUsing($fmt)
                    ->extraAttributes(['class' => 'font-bold']),

                TextInputColumn::make('tel_3')
                    ->label('TEL 3')
                    ->getStateUsing(fn(Venta $record) => $record->customer?->third_phone)
                    ->updateStateUsing(fn(Venta $record, ?string $state) =>
                        $record->customer?->update(['third_phone' => $state])
                    )
                    ->formatStateUsing($fmt)
                    ->extraAttributes(['class' => 'font-bold']),

                TextInputColumn::make('com_1')
                    ->label('COM 1')
                    ->getStateUsing(fn(Venta $record) => $record->customer?->phone1_commercial)
                    ->updateStateUsing(fn(Venta $record, ?string $state) =>
                        $record->customer?->update(['phone1_commercial' => $state])
                    )
                    ->formatStateUsing($fmt)
                    ->extraAttributes(['class' => 'font-bold']),

                TextInputColumn::make('com_2')
                    ->label('COM 2')
                    ->getStateUsing(fn(Venta $record) => $record->customer?->phone2_commercial)
                    ->updateStateUsing(fn(Venta $record, ?string $state) =>
                        $record->customer?->update(['phone2_commercial' => $state])
                    )
                    ->formatStateUsing($fmt)
                    ->extraAttributes(['class' => 'font-bold']),

            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTlfChanges::route('/'),
        ];
    }
}
