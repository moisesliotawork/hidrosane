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

                Tables\Columns\TextColumn::make('nro_contrato')
                    ->label('No. Contrato')
                    ->badge()
                    ->color(Color::Indigo)
                    ->getStateUsing(fn(CommercialPhoneLog $record) => $record->customer?->latestVenta?->nro_contr_adm ?? '—'),

                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->getStateUsing(fn(CommercialPhoneLog $record) => $record->customer?->name ?? '—')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('customer', function ($q) use ($search) {
                            $q->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('phone_slot')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => $state === 1 ? Color::Green : Color::Teal)
                    ->formatStateUsing(fn($state) => $state === 1 ? 'COM1' : ($state === 2 ? 'COM2' : '—')),

                Tables\Columns\TextColumn::make('phone_value')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_contrato')
                    ->label('Fecha del Contrato')
                    ->getStateUsing(function (CommercialPhoneLog $record) {
                        $fecha = $record->customer?->latestVenta?->fecha_venta;
                        if (!$fecha) return '—';
                        return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
                    }),

                Tables\Columns\TextColumn::make('fuente')
                    ->label('Fuente')
                    ->badge()
                    ->color(Color::Orange)
                    ->getStateUsing(function (CommercialPhoneLog $record) {
                        $origen = $record->customer?->latestVenta?->origen_venta;
                        if (!$origen) return '—';
                        if ($origen instanceof \App\Enums\OrigenVenta) {
                            return $origen->label();
                        }
                        $enum = \App\Enums\OrigenVenta::tryFrom((string) $origen);
                        return $enum ? $enum->label() : $origen;
                    }),

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
            ->with([
                'user:id,name,last_name',
                'user.roles:id,name',
                'customer:id,first_names,last_names',
                'customer.latestVenta:id,customer_id,nro_contr_adm,fecha_venta,origen_venta',
            ]);
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
