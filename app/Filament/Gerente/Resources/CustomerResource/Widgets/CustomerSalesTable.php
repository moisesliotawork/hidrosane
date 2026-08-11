<?php

namespace App\Filament\Gerente\Resources\CustomerResource\Widgets;

use App\Filament\Gerente\Resources\VentaResource;
use App\Models\ContratoRecuperado;
use App\Models\Customer;
use App\Models\Venta;
use Carbon\Carbon;
use Closure;
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

    /** @var list<string>|null */
    protected ?array $nrosContratoRecuperados = null;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        // Sin URL de fila: Ver Datos abre el modal sin navegar al edit.
        return null;
    }

    protected function getTableQuery(): Builder
    {
        return Venta::query()
            ->with([
                'note',
                'comercial',
                'customer',
                'ventaOfertas.oferta',
                'ventaOfertas.productos.producto',
            ])
            ->where('customer_id', $this->record?->id)
            ->latest('fecha_venta');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('nro_contrato')
                ->label('#Contrato')
                ->state(fn (Venta $r) => $this->formatContratoNumber(
                    $r->nro_contr_adm ?: ($r->nro_contrato ?: null)
                ))
                ->searchable(['nro_contrato', 'nro_contr_adm'])
                ->sortable()
                ->badge()
                ->color(fn (Venta $r) => $this->isContratoRecuperado($r) ? 'warning' : 'success')
                ->toggleable(),

            TextColumn::make('fecha_venta')
                ->label('F. Venta')
                ->date('d/m/Y')
                ->sortable()
                ->weight(FontWeight::Bold),

            TextColumn::make('importe_total')
                ->label('Importe')
                ->money('EUR', true)
                ->sortable(),

            TextColumn::make('modalidad_pago')
                ->label('Modalidad')
                ->badge()
                ->toggleable(),

            TextColumn::make('estado_venta_label')
                ->label('Estado')
                ->badge()
                ->color(fn (Venta $r) => $r->estado_venta_color ?? 'gray')
                ->sortable(),

            TextColumn::make('ver_datos')
                ->label('Ver Datos')
                ->state('VER DATOS')
                ->badge()
                ->color('info')
                ->alignCenter()
                ->action(
                    Tables\Actions\Action::make('verDatosVenta')
                        ->modalHeading('Datos de la venta')
                        ->modalWidth(MaxWidth::FourExtraLarge)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->infolist(fn (Venta $record): array => $this->ventaDatosInfolist($record))
                ),

            TextColumn::make('note.nro_nota')
                ->label('# Nota')
                ->formatStateUsing(fn ($state) => $state && strlen($state) === 5
                    ? substr($state, 0, 3).' '.substr($state, 3, 2)
                    : $state)
                ->sortable(),

            TextColumn::make('fecha_entrega')
                ->label('F. Entrega')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('horario_entrega')
                ->label('Horario')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('comercial.empleado_id')
                ->label('Com.')
                ->badge()
                ->color('success')
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
        $nroContrato = $this->formatContratoNumber(
            filled($venta->nro_contr_adm)
                ? (string) $venta->nro_contr_adm
                : (filled($venta->nro_contrato) ? (string) $venta->nro_contrato : null)
        );
        $nroClienteAdm = filled($venta->nro_cliente_adm)
            ? (string) $venta->nro_cliente_adm
            : (filled($customer?->nro_cliente) ? (string) $customer->nro_cliente : '—');
        $contratoColor = $this->isContratoRecuperado($venta) ? 'warning' : 'success';

        return [
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

            Infolists\Components\Section::make()
                ->compact()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('nro_contrato_show')
                        ->label('#Contrato')
                        ->state($nroContrato)
                        ->badge()
                        ->color($contratoColor)
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
                        ->state($this->abrirVentaHtml($venta))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Ofertas y productos del contrato')
                ->compact()
                ->columns(1)
                ->schema([
                    Infolists\Components\TextEntry::make('ofertas_productos')
                        ->hiddenLabel()
                        ->state($this->formatOfertasProductosHtml($venta))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function abrirVentaHtml(Venta $venta): HtmlString
    {
        $url = e(VentaResource::getUrl('edit', ['record' => $venta]));

        return new HtmlString(
            '<style>'
            .'@keyframes ohana-abrir-venta-blink{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(234,88,12,.55)}50%{opacity:.42;box-shadow:0 0 0 3px rgba(234,88,12,.15)}}'
            .'a.ohana-abrir-venta{display:inline-flex;align-items:center;padding:.28rem .7rem;border-radius:.4rem;'
            .'border:2px solid #ea580c;background:rgba(234,88,12,.16);color:#ea580c!important;'
            .'font-weight:900;font-size:.8rem;letter-spacing:.03em;text-decoration:none!important;'
            .'text-transform:uppercase;animation:ohana-abrir-venta-blink 1.05s ease-in-out infinite;}'
            .'a.ohana-abrir-venta:hover{background:rgba(234,88,12,.28);color:#c2410c!important;}'
            .'</style>'
            .'<a href="'.$url.'" class="ohana-abrir-venta" target="_blank" rel="noopener">Abrir venta</a>'
        );
    }

    protected function formatOfertasProductosHtml(Venta $venta): HtmlString
    {
        $venta->loadMissing(['ventaOfertas.oferta', 'ventaOfertas.productos.producto']);

        $blocks = [];
        foreach ($venta->ventaOfertas as $vo) {
            $ofertaNombre = trim((string) ($vo->oferta?->nombre ?? ''));
            if ($ofertaNombre === '') {
                $ofertaNombre = 'Oferta #'.($vo->oferta_id ?: $vo->id);
            }

            $precio = $vo->oferta?->precio_base;
            $precioTxt = is_numeric($precio)
                ? number_format((float) $precio, 2, ',', '.').' €'
                : null;

            $productos = $vo->productos
                ->map(function ($linea) {
                    $nombre = trim((string) ($linea->producto?->nombre ?? ''));
                    if ($nombre === '') {
                        return null;
                    }
                    $qty = (int) ($linea->cantidad ?? 1);
                    $suffix = $qty > 1 ? ' ×'.$qty : '';

                    return e($nombre).$suffix;
                })
                ->filter()
                ->values();

            $prodHtml = $productos->isEmpty()
                ? '<div style="font-size:0.8rem;opacity:0.65;padding-left:0.35rem;">Sin productos</div>'
                : $productos
                    ->map(fn (string $p) => '<div style="font-size:0.82rem;font-weight:600;line-height:1.35;padding:0.1rem 0 0.1rem 0.55rem;">› '.$p.'</div>')
                    ->implode('');

            $blocks[] = '<div style="margin-bottom:0.65rem;padding:0.55rem 0.7rem;border:1px solid rgba(148,163,184,0.35);border-radius:0.5rem;">'
                .'<div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.4rem 0.65rem;margin-bottom:0.25rem;">'
                .'<span style="font-size:0.85rem;font-weight:800;text-transform:uppercase;letter-spacing:0.03em;color:#16a34a;">'
                .e($ofertaNombre)
                .'</span>'
                .($precioTxt
                    ? '<span style="font-size:0.75rem;font-weight:700;color:#d97706;">'.$precioTxt.'</span>'
                    : '')
                .'</div>'
                .$prodHtml
                .'</div>';
        }

        if ($blocks === []) {
            $externos = collect($venta->productos_externos ?? [])
                ->map(function ($item) {
                    if (is_string($item)) {
                        return trim($item);
                    }
                    if (! is_array($item)) {
                        return null;
                    }

                    return trim((string) ($item['nombre'] ?? $item['name'] ?? $item['producto'] ?? ''));
                })
                ->filter()
                ->values();

            if ($externos->isNotEmpty()) {
                $lines = $externos
                    ->map(fn (string $p) => '<div style="font-size:0.82rem;font-weight:600;line-height:1.35;padding:0.1rem 0 0.1rem 0.55rem;">› '.e($p).'</div>')
                    ->implode('');

                return new HtmlString(
                    '<div style="margin-bottom:0.35rem;font-size:0.75rem;font-weight:700;color:#d97706;text-transform:uppercase;">Productos externos</div>'
                    .$lines
                );
            }

            return new HtmlString('<span style="opacity:0.65;font-size:0.85rem;">Sin ofertas ni productos en este contrato.</span>');
        }

        return new HtmlString(implode('', $blocks));
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

    protected function isContratoRecuperado(Venta $venta): bool
    {
        $nro = trim((string) ($venta->nro_contr_adm ?: ''));
        if ($nro === '') {
            return false;
        }

        return in_array($nro, $this->nrosContratoRecuperados(), true);
    }

    /** @return list<string> */
    protected function nrosContratoRecuperados(): array
    {
        if ($this->nrosContratoRecuperados !== null) {
            return $this->nrosContratoRecuperados;
        }

        $nros = Venta::query()
            ->where('customer_id', $this->record?->id)
            ->whereNotNull('nro_contr_adm')
            ->pluck('nro_contr_adm')
            ->all();

        return $this->nrosContratoRecuperados = ContratoRecuperado::nrosRecuperadosAmong($nros);
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
