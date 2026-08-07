<?php

namespace App\Filament\SuperAdmin\Resources\CustomerResource\Widgets;

use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Customer;
use App\Models\Venta;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CustomerSalesTable extends BaseWidget
{
    protected static ?string $heading = 'HISTÓRICO DE CONTRATOS';

    protected int|string|array $columnSpan = 'full';

    /** Filament inyecta el registro actual del ViewRecord */
    public ?Customer $record = null;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return null;
    }

    protected function getTableQuery(): Builder
    {
        return Venta::query()
            ->with(['note', 'comercial', 'customer'])
            ->where('customer_id', $this->record?->id)
            ->latest('fecha_venta');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('ver_datos')
                ->label('Ver Datos')
                ->state('Datos/Vta')
                ->badge()
                ->color('info')
                ->alignCenter()
                ->grow(false)
                ->action(
                    Tables\Actions\Action::make('verDatosVenta')
                        ->modalHeading('Datos de la venta')
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->infolist(fn (Venta $record): array => $this->ventaDatosInfolist($record))
                ),

            TextColumn::make('nro_contrato')
                ->label('Contrato')
                ->searchable()
                ->sortable()
                ->badge()
                ->color('gray')
                ->grow(false)
                ->toggleable(),

            TextColumn::make('note.nro_nota')
                ->label('# Nota')
                ->formatStateUsing(fn ($state) => $state && strlen($state) === 5
                    ? substr($state, 0, 3).' '.substr($state, 3, 2)
                    : $state)
                ->badge()
                ->color('gray')
                ->grow(false)
                ->sortable(),

            TextColumn::make('fecha_venta')
                ->label('F. Venta')
                ->date('d/m/Y')
                ->sortable()
                ->color('danger')
                ->weight(FontWeight::Bold)
                ->grow(false)
                ->action(
                    Tables\Actions\Action::make('edit_fecha_venta')
                        ->modalHeading('Editar fecha de venta')
                        ->modalWidth('sm')
                        ->form([
                            DatePicker::make('fecha_venta')
                                ->label('Fecha de venta')
                                ->displayFormat('d/m/Y')
                                ->required(),
                        ])
                        ->fillForm(fn (Venta $record) => ['fecha_venta' => $record->fecha_venta])
                        ->action(function (Venta $record, array $data): void {
                            $record->update(['fecha_venta' => $data['fecha_venta']]);
                        })
                ),

            TextColumn::make('importe_total')
                ->label('Importe')
                ->money('EUR', true)
                ->sortable()
                ->grow(false),

            TextColumn::make('modalidad_pago')
                ->label('Modalidad')
                ->badge()
                ->grow(false)
                ->toggleable(),

            TextColumn::make('estado_venta_label')
                ->label('Estado')
                ->badge()
                ->color(fn (Venta $r) => $r->estado_venta_color ?? 'gray')
                ->grow(false)
                ->sortable(),

            TextColumn::make('fecha_entrega')
                ->label('F. Entrega')
                ->date('d/m/Y')
                ->sortable()
                ->badge()
                ->color('gray')
                ->grow(false)
                ->toggleable(),

            TextColumn::make('horario_entrega')
                ->label('Horario')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('comercial.empleado_id')
                ->label('Com.')
                ->badge()
                ->color('success')
                ->grow(false)
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Infolists\Components\Component>
     */
    protected function ventaDatosInfolist(Venta $venta): array
    {
        $customer = $venta->customer ?? $this->record;
        $nombre = mb_strtoupper(trim(($customer?->first_names ?? '').' '.($customer?->last_names ?? '')));
        $fechaPromo = $venta->fecha_venta
            ? $venta->fecha_venta->timezone('Europe/Madrid')->format('d/m/Y')
            : '—';
        $nroContrato = $venta->nro_contrato ?: ($venta->nro_contr_adm ?: '—');
        $nroAdmin = $venta->nro_cliente_adm ?: ($venta->nro_contr_adm ?: '—');

        return [
            // Fila 1: nombre → nº contrato → fecha promo (mismo estilo badge)
            Infolists\Components\Section::make()
                ->compact()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('cliente_nombre')
                        ->label('Cliente')
                        ->state($nombre !== '' ? $nombre : '—')
                        ->weight(FontWeight::ExtraBold)
                        ->color('primary')
                        ->extraAttributes([
                            'style' => 'text-transform:uppercase;font-size:0.95rem;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;',
                        ]),

                    Infolists\Components\TextEntry::make('nro_contrato_show')
                        ->label('Nº contrato')
                        ->state($nroContrato)
                        ->badge()
                        ->color('success')
                        ->weight(FontWeight::Bold),

                    Infolists\Components\TextEntry::make('fecha_promo')
                        ->label('Fecha promo')
                        ->state($fechaPromo)
                        ->badge()
                        ->color('success')
                        ->weight(FontWeight::Bold),
                ]),

            // Fila 2: DNI · nº admin · fecha contrato (roja)
            Infolists\Components\Section::make()
                ->compact()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('dni')
                        ->label('DNI')
                        ->state(filled($customer?->dni) ? $customer->dni : '—')
                        ->badge()
                        ->color('gray')
                        ->weight(FontWeight::Bold),

                    Infolists\Components\TextEntry::make('nro_cliente_adm')
                        ->label('Nº contrato admin')
                        ->state($nroAdmin)
                        ->badge()
                        ->color('info')
                        ->weight(FontWeight::Bold),

                    Infolists\Components\TextEntry::make('fecha_contrato')
                        ->label('Fecha de contrato')
                        ->state($fechaPromo)
                        ->weight(FontWeight::ExtraBold)
                        ->color('danger')
                        ->extraAttributes([
                            'style' => 'font-size:0.95rem;line-height:1.15;white-space:nowrap;',
                        ]),
                ]),

            // Resto: una sola fila compacta con badges
            Infolists\Components\Section::make()
                ->compact()
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('importe_total')
                        ->label('Importe')
                        ->state($venta->importe_total)
                        ->money('EUR')
                        ->badge()
                        ->color('warning'),

                    Infolists\Components\TextEntry::make('modalidad_pago')
                        ->label('Modalidad')
                        ->state($venta->modalidad_pago ?: '—')
                        ->badge(),

                    Infolists\Components\TextEntry::make('estado')
                        ->label('Estado')
                        ->state($venta->estado_venta_label ?? '—')
                        ->badge()
                        ->color($venta->estado_venta_color ?? 'gray'),

                    Infolists\Components\TextEntry::make('fecha_entrega')
                        ->label('F. entrega')
                        ->state($venta->fecha_entrega?->timezone('Europe/Madrid')->format('d/m/Y') ?? '—')
                        ->badge()
                        ->color('gray'),

                    Infolists\Components\TextEntry::make('comercial')
                        ->label('Comercial')
                        ->state($venta->comercial?->empleado_id ?: '—')
                        ->badge()
                        ->color('success'),

                    Infolists\Components\TextEntry::make('nro_nota')
                        ->label('# Nota')
                        ->state($venta->note?->nro_nota ?: '—')
                        ->badge()
                        ->color('gray'),

                    Infolists\Components\TextEntry::make('abrir')
                        ->label(' ')
                        ->state(new HtmlString(
                            '<a href="'.e(VentaResource::getUrl('edit', ['record' => $venta])).'" '
                            .'class="fi-badge fi-color-primary" style="text-decoration:none;font-weight:700;">'
                            .'Abrir venta</a>'
                        ))
                        ->html(),
                ]),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Sin ventas registradas';
    }
}
