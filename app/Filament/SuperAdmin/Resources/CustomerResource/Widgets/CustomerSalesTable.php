<?php

namespace App\Filament\SuperAdmin\Resources\CustomerResource\Widgets;

use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\Customer;
use App\Models\Venta;
use Carbon\Carbon;
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
                ->toggleable()
                ->action(
                    Tables\Actions\Action::make('verDatosVenta')
                        ->modalHeading('Datos de la venta')
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->infolist(fn (Venta $record): array => $this->ventaDatosInfolist($record))
                ),

            TextColumn::make('nro_contrato')
                ->label('#Contrato')
                ->state(fn (Venta $r) => $this->formatContratoNumber(
                    $r->nro_contr_adm ?: ($r->nro_contrato ?: null)
                ))
                ->searchable(['nro_contrato', 'nro_contr_adm'])
                ->sortable()
                ->badge()
                ->color('success')
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
                ->sortable()
                ->toggleable(),

            TextColumn::make('fecha_venta')
                ->label('F. Venta')
                ->date('d/m/Y')
                ->sortable()
                ->color('danger')
                ->weight(FontWeight::Bold)
                ->grow(false)
                ->toggleable()
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
                ->grow(false)
                ->toggleable(),

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
                ->sortable()
                ->toggleable(),

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
        $fechaPromo = $this->formatMadridDate($venta->fecha_venta);
        $fechaEntrega = $this->formatMadridDate($venta->fecha_entrega);
        // Coherencia: #Contrato = nro_contr_adm (número admin del contrato)
        $nroContrato = $this->formatContratoNumber(
            filled($venta->nro_contr_adm)
                ? (string) $venta->nro_contr_adm
                : (filled($venta->nro_contrato) ? (string) $venta->nro_contrato : null)
        );
        $nroClienteAdm = filled($venta->nro_cliente_adm)
            ? (string) $venta->nro_cliente_adm
            : (filled($customer?->nro_cliente) ? (string) $customer->nro_cliente : '—');

        return [
            // Fila 1: nombre completo (sin cortar) a ancho completo
            Infolists\Components\Section::make()
                ->compact()
                ->columns(1)
                ->schema([
                    Infolists\Components\TextEntry::make('cliente_nombre')
                        ->label('Cliente')
                        ->state($nombre !== '' ? $nombre : '—')
                        ->weight(FontWeight::ExtraBold)
                        ->color('primary')
                        ->extraAttributes([
                            'style' => 'text-transform:uppercase;font-size:1rem;line-height:1.3;white-space:normal;overflow:visible;word-break:break-word;',
                        ]),
                ]),

            // Fila 2: #Contrato (admin) verde · fecha promo · nº cliente admin
            Infolists\Components\Section::make()
                ->compact()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('nro_contrato_show')
                        ->label('#Contrato')
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

                    Infolists\Components\TextEntry::make('nro_cliente_adm')
                        ->label('Nº cliente admin')
                        ->state($nroClienteAdm)
                        ->badge()
                        ->color('info')
                        ->weight(FontWeight::Bold),
                ]),

            // Fila 3: DNI · fecha contrato (roja)
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
                        ->state($fechaEntrega)
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

    protected function formatMadridDate(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            return $date->timezone('Europe/Madrid')->format('d/m/Y');
        } catch (\Throwable) {
            return is_string($value) ? $value : '—';
        }
    }

    /** Separa las 2 primeras cifras para leer mejor (ej. 834 → 83 4, 00955 → 00 955). */
    protected function formatContratoNumber(?string $value): string
    {
        $nro = trim((string) $value);
        if ($nro === '' || $nro === '—') {
            return '—';
        }

        if (mb_strlen($nro) <= 2) {
            return $nro;
        }

        return mb_substr($nro, 0, 2).' '.mb_substr($nro, 2);
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
