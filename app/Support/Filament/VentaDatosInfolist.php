<?php

namespace App\Support\Filament;

use App\Models\ContratoRecuperado;
use App\Models\Venta;
use Filament\Infolists;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class VentaDatosInfolist
{
    /**
     * @return array<int, Infolists\Components\Component>
     */
    public static function schema(Venta $venta, string $editUrl): array
    {
        $venta->loadMissing(['customer', 'comercial', 'note', 'ventaOfertas.oferta', 'ventaOfertas.productos.producto']);

        $customer = $venta->customer;
        $nombre = mb_strtoupper(trim(($customer?->first_names ?? '').' '.($customer?->last_names ?? '')));
        $fechaPromo = self::formatMadridDate($venta->fecha_venta);
        $fechaEntrega = self::formatMadridDate($venta->fecha_entrega);
        $nroContrato = self::formatContratoNumber(
            filled($venta->nro_contr_adm)
                ? (string) $venta->nro_contr_adm
                : (filled($venta->nro_contrato) ? (string) $venta->nro_contrato : null)
        );
        $nroClienteAdm = filled($venta->nro_cliente_adm)
            ? (string) $venta->nro_cliente_adm
            : (filled($customer?->nro_cliente) ? (string) $customer->nro_cliente : '—');
        $contratoColor = ContratoRecuperado::isRecuperado((string) ($venta->nro_contr_adm ?? ''))
            ? 'warning'
            : 'success';

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
                        ->state(self::abrirVentaHtml($editUrl))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Ofertas y productos del contrato')
                ->compact()
                ->columns(1)
                ->schema([
                    Infolists\Components\TextEntry::make('ofertas_productos')
                        ->hiddenLabel()
                        ->state(self::formatOfertasProductosHtml($venta))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected static function abrirVentaHtml(string $editUrl): HtmlString
    {
        $url = e($editUrl);

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

    protected static function formatOfertasProductosHtml(Venta $venta): HtmlString
    {
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
            return new HtmlString('<span style="opacity:0.65;font-size:0.85rem;">Sin ofertas ni productos en este contrato.</span>');
        }

        return new HtmlString(implode('', $blocks));
    }

    protected static function formatMadridDate(mixed $value): string
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

    protected static function formatContratoNumber(?string $value): string
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
}
