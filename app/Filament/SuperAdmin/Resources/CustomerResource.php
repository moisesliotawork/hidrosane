<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\CustomerResource\Pages;
use App\Filament\SuperAdmin\Resources\CustomerResource\RelationManagers;
use App\Filament\Support\CustomerPhoneForm;
use App\Support\Filament\FechaNacimientoField;
use App\Filament\Support\CustomerPosicionGlobalTable;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Support\Enums\IconPosition;
use App\Models\Venta;
use App\Services\CustomerPrimaryKeyReassignmentService;
use App\Support\Filament\CustomerSoftDeleteTableAction;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Posicion Global de Cliente';
    protected static ?string $modelLabel = 'Posicion Global de Cliente';

    /** Justo encima del recurso Contratos en el menú */
    protected static ?int $navigationSort = -9;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return CustomerPosicionGlobalTable::applyEagerLoads(parent::getEloquentQuery());
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Posición Global del Cliente')
                ->columns(6)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre de Cliente')
                        ->state(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                        ->color('success')
                        ->weight(\Filament\Support\Enums\FontWeight::ExtraBold)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'whitespace-nowrap'])
                        ->suffixAction(
                            Action::make('editar_nombre')
                                ->icon('heroicon-o-pencil-square')
                                ->tooltip('Editar nombre')
                                ->modalHeading('Editar nombre del cliente')
                                ->modalSubmitActionLabel('Guardar')
                                ->form([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('first_names')
                                            ->label('Nombres')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('last_names')
                                            ->label('Apellidos')
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                                ])
                                ->fillForm(fn (Customer $record): array => [
                                    'first_names' => $record->first_names,
                                    'last_names' => $record->last_names,
                                ])
                                ->action(function (Customer $record, array $data): void {
                                    $record->update([
                                        'first_names' => $data['first_names'],
                                        'last_names' => $data['last_names'],
                                    ]);

                                    Notification::make()
                                        ->title('Nombre actualizado')
                                        ->success()
                                        ->send();
                                }),
                        ),

                    TextEntry::make('primary_address')
                        ->label('DOMICILIO')
                        ->state(function (Customer $r) {
                            return "{$r->primary_address}, {$r->nro_piso} - {$r->ciudad} ({$r->postal_code})";
                        })
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->columnSpan(4)
                        ->suffixAction(
                            Action::make('editar_domicilio')
                                ->icon('heroicon-o-pencil-square')
                                ->tooltip('Editar domicilio')
                                ->modalHeading('Editar domicilio')
                                ->modalSubmitActionLabel('Guardar')
                                ->form([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('primary_address')
                                            ->label('Domicilio')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('nro_piso')
                                            ->label('No. y Piso')
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('postal_code')
                                            ->label('Código postal')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('ciudad')
                                            ->label('Ciudad')
                                            ->maxLength(255),
                                    ]),
                                ])
                                ->fillForm(fn (Customer $record): array => [
                                    'primary_address' => $record->primary_address,
                                    'nro_piso' => $record->nro_piso,
                                    'postal_code' => $record->postal_code,
                                    'ciudad' => $record->ciudad,
                                ])
                                ->action(function (Customer $record, array $data): void {
                                    $record->update([
                                        'primary_address' => $data['primary_address'],
                                        'nro_piso' => $data['nro_piso'],
                                        'postal_code' => $data['postal_code'],
                                        'ciudad' => $data['ciudad'],
                                    ]);

                                    Notification::make()
                                        ->title('Domicilio actualizado')
                                        ->success()
                                        ->send();
                                }),
                        ),

                    TextEntry::make('dni')
                        ->label('DNI')
                        ->state(fn (Customer $r) => filled($r->dni) ? $r->dni : '—')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->columnSpan(1)
                        ->suffixAction(
                            Action::make('editar_dni')
                                ->icon('heroicon-o-pencil-square')
                                ->tooltip('Editar DNI')
                                ->modalHeading('Editar DNI')
                                ->modalSubmitActionLabel('Guardar')
                                ->form([
                                    Forms\Components\TextInput::make('dni')
                                        ->label('DNI')
                                        ->maxLength(255),
                                ])
                                ->fillForm(fn (Customer $record): array => [
                                    'dni' => $record->dni,
                                ])
                                ->action(function (Customer $record, array $data): void {
                                    $record->update([
                                        'dni' => $data['dni'],
                                    ]);

                                    Notification::make()
                                        ->title('DNI actualizado')
                                        ->success()
                                        ->send();
                                }),
                        ),

                    TextEntry::make('nro_cliente')
                        ->label('ID/Cliente')
                        ->state(fn(Customer $r) => $r->firstVentaClienteAdmin() ?: '—')
                        ->columnSpan(1),

                    TextEntry::make('fecha_nac')
                        ->label('Fecha de nacimiento')
                        ->columnSpanFull()
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->state(
                            fn (Customer $r) => $r->fechaNacDisplay('d/m/Y') ?? '—'
                        )
                        ->suffixAction(
                            Action::make('editar_fecha_nac')
                                ->icon('heroicon-o-pencil-square')
                                ->tooltip('Editar fecha de nacimiento')
                                ->modalHeading('Editar fecha de nacimiento')
                                ->modalSubmitActionLabel('Guardar')
                                ->form([
                                    FechaNacimientoField::configureDatePicker(
                                        Forms\Components\DatePicker::make('fecha_nac')
                                            ->label('Fecha de nacimiento'),
                                        required: false,
                                    )->nullable(),
                                ])
                                ->fillForm(fn (Customer $record): array => [
                                    'fecha_nac' => $record->storedFechaNac(),
                                ])
                                ->action(function (Customer $record, array $data): void {
                                    $record->update([
                                        'fecha_nac' => $data['fecha_nac'],
                                    ]);

                                    Notification::make()
                                        ->title('Fecha de nacimiento actualizada')
                                        ->success()
                                        ->send();
                                }),
                        ),

                    TextEntry::make('edad_cliente')
                        ->label('Edad')
                        ->columnSpan(2)
                        ->state(function (Customer $r): string {
                            $fechaNac = $r->safeFechaNac();

                            if ($fechaNac === null) {
                                return '—';
                            }

                            $d = $fechaNac->diff(now());

                            return "{$d->y} años {$d->m} meses y {$d->d} días";
                        }),

                    TextEntry::make('secondary_address')
                        ->label('DOMICILIO 2')
                        ->columnSpan(4)
                        ->visible(fn(Customer $r) => filled($r->secondary_address)),

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
                ->schema([
                    TextEntry::make('all_phones')
                        ->label('TELÉFONOS CLIENTE')
                        ->columnSpanFull()
                        ->state(fn (Customer $r) => CustomerPosicionGlobalTable::labeledAllPhonesHtml($r))
                        ->html()
                        ->suffixAction(
                            Action::make('editar_telefonos')
                                ->icon('heroicon-o-pencil-square')
                                ->tooltip('Editar teléfonos')
                                ->modalHeading('Editar teléfonos')
                                ->modalSubmitActionLabel('Guardar')
                                ->form([
                                    Forms\Components\Grid::make(2)->schema([
                                        CustomerPhoneForm::make('phone', required: true, strictDigits: false),
                                        CustomerPhoneForm::make('secondary_phone', strictDigits: false),
                                        CustomerPhoneForm::make('third_phone', strictDigits: false),
                                        CustomerPhoneForm::make('phone1_commercial', 'Teléfono comercial 1', strictDigits: false),
                                        CustomerPhoneForm::make('phone2_commercial', 'Teléfono comercial 2', strictDigits: false),
                                    ]),
                                ])
                                ->fillForm(fn (Customer $record): array => [
                                    'phone' => $record->phone,
                                    'secondary_phone' => $record->secondary_phone,
                                    'third_phone' => $record->third_phone,
                                    'phone1_commercial' => $record->phone1_commercial,
                                    'phone2_commercial' => $record->phone2_commercial,
                                ])
                                ->action(function (Customer $record, array $data): void {
                                    $record->update(CustomerPhoneForm::validateAndNormalizeFields($data, [
                                        'phone' => true,
                                        'secondary_phone' => false,
                                        'third_phone' => false,
                                        'phone1_commercial' => false,
                                        'phone2_commercial' => false,
                                    ]));

                                    Notification::make()
                                        ->title('Teléfonos actualizados')
                                        ->success()
                                        ->send();
                                }),
                        ),

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
                    ->color('warning')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->width('240px')
                    ->searchable(['first_names', 'last_names'])
                    ->extraAttributes(['class' => 'whitespace-nowrap'])
                    ->toggleable(),

                TextColumn::make('id')
                    ->label('ID_Cliente')
                    ->width('80px')
                    ->icon('heroicon-o-pencil-square')
                    ->iconPosition(IconPosition::After)
                    ->iconColor('gray')
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->sortable()
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereRaw('CAST(customers.id AS CHAR) LIKE ?', ["%{$search}%"]);
                        },
                    )
                    ->action(
                        TableAction::make('editIdCliente')
                            ->label('Editar ID_Cliente')
                            ->modalHeading('Editar ID_Cliente')
                            ->modalSubmitActionLabel('Guardar')
                            ->form([
                                Forms\Components\TextInput::make('id')
                                    ->label('Nuevo ID_Cliente')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->rule(fn (Customer $record) => Rule::unique('customers', 'id')->ignore($record->id)),
                            ])
                            ->fillForm(fn (Customer $record): array => ['id' => $record->id])
                            ->action(function (Customer $record, array $data): void {
                                try {
                                    CustomerPrimaryKeyReassignmentService::reassign($record, (int) $data['id']);

                                    Notification::make()
                                        ->title('ID_Cliente actualizado')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('No se pudo actualizar el ID_Cliente')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                    )
                    ->toggleable(),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? implode(' ', str_split($state, 4))
                        : '—')
                    ->color('warning')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ventas_count')
                    ->label('#VENTAS')
                    ->state(fn(Customer $r): int => $r->ventas()->count())
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('contr_recup')
                    ->label('ContrRecup')
                    ->state(function (Customer $r): bool {
                        if (! \Illuminate\Support\Facades\Schema::hasTable('contratos_recuperados')) {
                            return false;
                        }

                        return $r->ventas()
                            ->whereIn('nro_contr_adm', function ($query) {
                                $query->select('nro_contr_adm')->from('contratos_recuperados');
                            })
                            ->exists();
                    })
                    ->formatStateUsing(fn (bool $state): string => $state ? 'SI' : 'NO')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success')
                    ->toggleable(),

                TextColumn::make('inhabilitado')
                    ->label('INHAB')
                    ->state(fn(Customer $r) => $r->inhabilitado ? '☠️' : '')
                    ->color(fn(Customer $r) => $r->inhabilitado ? 'danger' : null)
                    ->weight(fn(Customer $r) => $r->inhabilitado ? \Filament\Support\Enums\FontWeight::Bold : null)
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('phones')
                    ->label('TELEFONOS')
                    ->state(function (Customer $r): string {
                        $fmt = fn(?string $p): string => $p ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3)) : '';
                        return collect([$r->phone, $r->secondary_phone, $r->third_phone])
                            ->filter()->map($fmt)->join(' | ') ?: '—';
                    })
                    ->color('warning')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable(['phone', 'secondary_phone'])
                    ->toggleable(),

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
                    ->toggleable(),

                CustomerPosicionGlobalTable::gpsDentroColumn()
                    ->toggleable(),

                TextColumn::make('nro_cliente')
                    ->label('ID/App/Cliente')
                    ->state(fn(Customer $r) => $r->firstVentaClienteAdmin())
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('ventas', function ($q) use ($search) {
                            // antes: nro_cliente_admin
                            $q->where('nro_cliente_adm', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        $firstVentaAdmin = Venta::select('nro_cliente_adm') // antes: nro_cliente_admin
                            ->whereColumn('ventas.customer_id', 'customers.id')
                            ->whereNotNull('nro_cliente_adm')
                            ->where('nro_cliente_adm', '!=', '')
                            ->orderBy('created_at', 'asc')
                            ->limit(1);

                        $query->orderBy($firstVentaAdmin, $direction);
                    })
                    ->toggleable(),

            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                CustomerSoftDeleteTableAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    CustomerSoftDeleteTableAction::bulk(),
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
        return true;
    }

}
