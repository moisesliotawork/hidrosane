<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TlfChangeResource\Pages;
use App\Models\Customer;
use App\Filament\Support\CustomerPhoneForm;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TlfChangeResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationLabel = 'TLF CHANGE';
    protected static ?string $modelLabel = 'TLF Change';
    protected static ?string $pluralModelLabel = 'TLF Change';
    protected static ?string $slug = 'tlf-change';
    protected static ?string $navigationGroup = 'General';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['latestVenta.note']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([

                TextColumn::make('latestVenta.note.nro_nota')
                    ->label('Nº Nota')
                    ->badge()
                    ->color(Color::Pink)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Cliente')
                    ->getStateUsing(fn(Customer $record) => mb_strtoupper(
                        trim($record->first_names . ' ' . $record->last_names)
                    ))
                    ->weight(FontWeight::Bold)
                    ->color(Color::Amber)
                    ->searchable(['first_names', 'last_names']),

                TextInputColumn::make('phone')
                    ->label('TEL 1')
                    ->extraAttributes(['class' => 'font-bold'])
                    ->updateStateUsing(fn (Customer $record, $state) => static::normalizePhoneColumn($record, 'phone', $state)),

                TextInputColumn::make('secondary_phone')
                    ->label('TEL 2')
                    ->extraAttributes(['class' => 'font-bold'])
                    ->updateStateUsing(fn (Customer $record, $state) => static::normalizePhoneColumn($record, 'secondary_phone', $state)),

                TextInputColumn::make('third_phone')
                    ->label('TEL 3')
                    ->extraAttributes(['class' => 'font-bold'])
                    ->updateStateUsing(fn (Customer $record, $state) => static::normalizePhoneColumn($record, 'third_phone', $state)),

                TextInputColumn::make('phone1_commercial')
                    ->label('COM 1')
                    ->extraAttributes(['class' => 'font-bold']),

                TextInputColumn::make('phone2_commercial')
                    ->label('COM 2')
                    ->extraAttributes(['class' => 'font-bold']),

            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    protected static function normalizePhoneColumn(Customer $record, string $field, mixed $state): ?string
    {
        $digits = CustomerPhoneForm::normalizeDigits(is_string($state) ? $state : null);

        if ($field === 'phone' && $digits === null) {
            throw ValidationException::withMessages([
                $field => 'Debe tener exactamente 9 cifras.',
            ]);
        }

        if ($digits !== null && strlen($digits) !== 9) {
            throw ValidationException::withMessages([
                $field => 'Debe tener exactamente 9 cifras.',
            ]);
        }

        $record->update([$field => $digits]);

        return $digits;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTlfChanges::route('/'),
        ];
    }
}
