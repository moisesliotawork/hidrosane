<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\Com1Com2Resource\Pages;
use App\Models\CommercialPhoneLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;

class Com1Com2Resource extends Resource
{
    protected static ?string $model = CommercialPhoneLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'COM1 - COM2';
    protected static ?string $pluralModelLabel = 'COM1 - COM2';
    protected static ?string $modelLabel = 'Registro COM';
    protected static ?string $slug = 'com1-com2';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('user.id')
                    ->label('ID Usuario')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user_full_name')
                    ->label('Nombre Usuario')
                    ->getStateUsing(fn(CommercialPhoneLog $record) => trim(($record->user?->name ?? '') . ' ' . ($record->user?->last_name ?? '')))
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('user_role')
                    ->label('Cargo')
                    ->badge()
                    ->color(Color::Blue)
                    ->getStateUsing(function (CommercialPhoneLog $record) {
                        $roleName = $record->user?->roles->first()?->name;
                        if (!$roleName) {
                            return '—';
                        }
                        $enum = \App\Enums\UserRole::tryFrom($roleName);
                        return $enum ? $enum->label() : $roleName;
                    }),

                Tables\Columns\TextColumn::make('phone1_commercial')
                    ->label('TlfCom1')
                    ->badge()
                    ->color(Color::Green)
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone2_commercial')
                    ->label('TlfCom2')
                    ->badge()
                    ->color(Color::Teal)
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['user:id,name,last_name', 'user.roles:id,name']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCom1Com2::route('/'),
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

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
