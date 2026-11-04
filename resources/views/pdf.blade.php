<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Contrato {{ \App\Support\Brand::name() }}</title>
    <style>
        /* ===== Página y márgenes ===== */
        @page {
            size: 21cm 29.7cm;
            /* Carta: 8.5 x 11 pulgadas */
            margin-left: 15px;
            margin-right: 15px;
            margin-top: -10px;
            margin-bottom: 0px;
            /* Reducido para caber en 2 páginas */
        }

        * {
            font-size: 14.67px;
            font-family: "Helvetica", serif !important;
        }

        body {
            font-size: 14.67px;
            /* Ligeramente más pequeño que 12px */
            line-height: 0.9;
            /* Interlineado compacto */
            margin: 20px;
            /* Coincide con @page */
        }

        /* ===== Encabezados de sección ===== */
        .title-contracts {
            text-align: left;
            /* font-size: 15px !important; */
            /* Ajustado para ahorrar espacio */
            text-transform: uppercase;
            margin-top: 20px;
            /* Menor separación sobre la tabla siguiente */
            margin-bottom: 20px;
            text-decoration: underline;
        }

        h1.text-center {
            /* font-size: 16px; */
            margin-bottom: 10px;
        }

        /* ===== Tablas generales ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 0px;
        }

        td,
        th {
            border: 1px solid #000;
            padding: 4px 6px;
            /* Compactar padding */
            vertical-align: top;
        }

        th {
            font-weight: 900;
            background-color: #ddd;
        }

        .text-center {
            text-align: center;
        }

        .uppercase {
            text-transform: uppercase;
        }

        /* ===== Tablas sin bordes para datos anidados ===== */
        .no-border td,
        .no-border th {
            border: none;
            padding: 0;
            margin: 0;
        }

        .no-border table,
        .no-border table td {
            border: none;
            padding: 0;
            margin: 0;
        }

        /* ===== Encabezado con logo ===== */
        .header-table {
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .logo {
            width: 120px;
        }

        .header-info {
            text-align: left;
            /* font-size: 11px; */
            line-height: 1;
            padding-top: 20px !important;
            margin-right: 10px;
            font-size: 13px;
        }

        /* ===== Tabla de datos personales en dos columnas ===== */
        .two-column {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        .two-column td {
            width: 50%;
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .two-column td table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .two-column td table td {
            border: none;
            padding: 0px 0;
            /* Espacio interno ligero entre filas */
            margin: 0;
        }

        /* ===== Tabla de artículos (sección C) ===== */
        .article-table th,
        .article-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .article-table th {
            background-color: #ddd;
            font-weight: 900;
            text-align: left;
        }

        /* ===== Tabla de pagos (sección D) ===== */
        .payment-table th,
        .payment-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .payment-table th {
            background-color: #ddd;
            font-weight: 800;
            text-align: center;
            /* font-size: 10px; */
        }

        .payment-table td {
            text-align: center;
            /* font-size: 10px; */
        }

        .payment-table th[colspan] {
            text-align: left;
            /* font-size: 10px; */
        }

        /* ===== Evitar saltos de página dentro de secciones ===== */
        .section {
            page-break-inside: avoid;
            margin-bottom: 0px;
        }

        /* ===== Ajustes especiales para impresión ===== */
        @media print {
            body {
                margin: 15px;
                font-size: 10.5px;
                line-height: 1.35;
            }

            .title-contracts {
                margin-top: 10px;
                margin-bottom: 3px;
            }

            h1.text-center {
                margin-bottom: 8px;
                /* font-size: 15px; */
            }

            .header-table {
                margin-bottom: 10px;
            }

            .article-table tr.empty-row td {
                height: 0.9mm !important;
                line-height: 0.9mm !important;
                min-height: 0.9mm !important;
                padding: 0 !important;
                font-size: 0 !important;
                /* para evitar que algún espacio vacío afecte */
                border-width: 1px !important;
            }
        }
    </style>
</head>

<body>

    <!-- =========== Encabezado con Logo y Datos de Empresa =========== -->
    <table class="header-table no-border">
        <tr>
            <td>
                <img src="{{ public_path('images/logo.png') }}"
                    alt="Logo {{ \App\Support\Brand::name() }}" class="logo">
            </td>
            <td class="header-info">
                AVDA. DO AEROPORTO, 305<br>
                36317 CANDEAN<br>
                VIGO<br>
                www.hidrosane.es<br>
                DNI: 35569025P<br>
                Att. Al Cliente: 605 83 81 20<br>
                info@hidrosane.es
            </td>
            <td>
                <div class="section">
                    <h2 class="title-contracts" style="margin-bottom: 10px !important;">CONTRATO</h2>
                    <table class="no-border">
                        <tr class="no-border">
                            <td style="width:50%; vertical-align: top;">
                                <table class="no-border">
                                    <tr>
                                        <td>Cod.Contrato: <span class="uppercase">{{ $venta->nro_contrato }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Cód.Cliente: <span class="uppercase">{{
    strtoupper($venta->customer->id) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Deleg.: <span class="uppercase">Vigo</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Com.: <span class="uppercase">{{ $venta->comercial->empleado_id }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td style="width:50%; vertical-align: top;">
                                <table class="no-border">
                                    <tr>
                                        <td>Fec.Promo.:
                                            <span>{{ date('d-m-Y', strtotime($venta->created_at)) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Fec.Entr.:
                                            <span>{{ date('d-m-Y', strtotime($venta->fecha_entrega)) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Hora Entr.: <span
                                                class="uppercase">{{ strtoupper($venta->horario_entrega) }}</span></td>
                                    </tr>
                                    <tr>
                                        <td>Rep.: <span></span></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- =========== SECCIÓN B: DATOS PERSONALES DEL CLIENTE =========== -->
    <div class="section">
        <h2 class="title-contracts">A. DATOS PERSONALES DEL CLIENTE</h2>
        <table class="two-column" style="line-height: 1 !important;">
            <tr>
                <!-- COLUMNA IZQUIERDA -->
                <td>
                    <table class="no-border">
                        <tr>
                            <td>Nombre y apellidos: <span
                                    class="uppercase">{{ ucwords(trim($venta->customer->first_names . ' ' . $venta->customer->last_names)) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>DNI/NIE: <span class="uppercase">{{ strtoupper($venta->customer->dni) }}</span></td>
                        </tr>
                        <tr>
                            <td>Fecha nacimiento: <span
                                    class="uppercase">{{ $venta->customer->fechaNacDisplay('d-m-Y') ?? '' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>Estado civil:
                                <span class="lowercase">
                                    {{
    ($enum = \App\Enums\EstadoCivil::tryFrom(strtolower($venta->customer->estado_civil)))
    ? strtolower($enum->label())
    : strtolower($venta->customer->estado_civil)
                                    }}
                                </span>
                            </td>

                        </tr>
                        <tr>
                            @php
                                use Illuminate\Support\Str;
                                use App\Enums\SituacionLaboral;

                                $raw = $venta->customer->situacion_laboral;
                                $normalized = Str::slug($raw, '');
                                $enum = SituacionLaboral::tryFrom($normalized);
                            @endphp

                            <td>Situación laboral:
                                <span class="uppercase">
                                    {{ $enum ? strtoupper($enum->label()) : strtoupper($raw) }}
                                </span>
                            </td>

                        </tr>
                    </table>
                </td>

                <!-- COLUMNA DERECHA -->
                <td>
                    <table class="no-border">
                        <tr>
                            <td>Domicilio: <span
                                    class="uppercase">{{ strtoupper($venta->customer->primary_address) }}</span></td>
                        </tr>
                        <tr>
                            <td>CP: <span
                                    class="uppercase">{{ strtoupper($venta->customer->ciudad . ' - ' . $venta->customer->postal_code) }}</span>
                            </td>
                        </tr>
                        <tr>
                            @php

                                $phones = array_filter([
                                    $venta->customer->phone1_commercial ?? null,
                                    $venta->customer->phone2_commercial ?? null,
                                ]);
                            @endphp

                            <td>Teléfonos:
                                <span class="uppercase">
                                    {{ strtoupper(implode(' / ', $phones)) }}
                                </span>
                            </td>

                        </tr>
                        <tr>
                            @php
                                use App\Enums\TipoVivienda;

                                // valor crudo desde la BD
                                $raw = $venta->customer->tipo_vivienda ?? '';


                                $norm = Str::slug($raw, '');
                                if ($norm === 'alquilada') {
                                    $norm = 'alquiler';
                                }
                                $enum = TipoVivienda::tryFrom($norm);
                            @endphp

                            <td>
                                Vivienda:
                                <span class="uppercase">
                                    {{ $enum ? strtoupper($enum->label()) : strtoupper($raw) }}
                                </span>
                            </td>

                        </tr>
                        <tr>
                            @php
                                use App\Enums\IngresosRango;

                                $raw = $venta->customer->ingresos_rango ?? '';

                                $norm = Str::of($raw)
                                    ->replace(['€', ' '], '')
                                    ->lower()
                                    ->value();
                                if (Str::startsWith($norm, '>')) {
                                    $norm = '>1200';
                                }

                                $enum = IngresosRango::tryFrom($norm);
                            @endphp

                            <td>Ingresos:
                                <span class="uppercase">
                                    {{ $enum ? strtoupper($enum->label()) : strtoupper($raw) }}
                                </span>
                            </td>

                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    @php
        // 1) Aplanamos todas las líneas de producto y limitamos a 10 ítems
        $items = $venta->ventaOfertas
            ->flatMap(fn($o) => $o->productos)
            ->values()
            ->take(10);

        // 2) Partimos en “columnas” de 5 filas cada una
        $columns = $items->chunk(5);
        $firstCol = $columns->get(0, collect());
        $secondCol = $columns->get(1, collect());
    @endphp

    <div class="section">
        <h2 class="title-contracts">B. RELACIÓN DE ARTÍCULOS</h2>
        <table class="article-table">
            <thead>
                <tr>
                    <th style="width: 8%;  text-align:center;">POS</th>
                    <th style="width: 32%; text-align:center;">DESCRIPCIÓN DEL ARTÍCULO</th>
                    <th style="width: 10%; text-align:center;">CANT</th>
                    <th style="width: 8%;  text-align:center;">POS</th>
                    <th style="width: 32%; text-align:center;">DESCRIPCIÓN DEL ARTÍCULO</th>
                    <th style="width: 10%; text-align:center;">CANT</th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < 5; $i++)
                    @php
                        $isEmpty = !isset($firstCol[$i]) && !isset($secondCol[$i]);
                    @endphp
                    <tr class="{{ $isEmpty ? 'empty-row' : '' }}">
                        {{-- Columna A --}}
                        <td style="text-align:center;">
                            {!! isset($firstCol[$i]) ? $i + 1 : '&nbsp;' !!}
                        </td>
                        <td>
                            {!! isset($firstCol[$i]) ? strtoupper($firstCol[$i]->producto->nombre) : '&nbsp;' !!}
                        </td>
                        <td style="text-align:center;">
                            {!! isset($firstCol[$i]) ? $firstCol[$i]->cantidad : '&nbsp;' !!}
                        </td>

                        <td style="text-align:center;">
                            {!! isset($secondCol[$i]) ? $i + 6 : '&nbsp;' !!}
                        </td>
                        <td>
                            {!! isset($secondCol[$i]) ? strtoupper($secondCol[$i]->producto->nombre) : '&nbsp;' !!}
                        </td>
                        <td style="text-align:center;">
                            {!! isset($secondCol[$i]) ? $secondCol[$i]->cantidad : '&nbsp;' !!}
                        </td>
                    </tr>
                @endfor
            </tbody>

        </table>
    </div>


    <!-- =========== SECCIÓN D: IMPORTE Y FORMA DE PAGO =========== -->
    <div class="section">
        <h2 class="title-contracts">C. IMPORTE Y FORMA DE PAGO</h2>
        <table class="payment-table">
            <thead>
                <tr>
                    <th style="width: 20%;">ENTRADA</th>
                    <th style="width: 20%;">Nº DE CUOTAS</th>
                    <th style="width: 20%;">CUOTA MENSUAL</th>
                    <th style="width: 20%;">FECHA 1ª CUOTA</th>
                    <th style="width: 20%;">IMPORTE TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>

                    </td>
                    <td>{{ $venta->num_cuotas }}</td>
                    <td>{{ number_format($venta->cuota_mensual, 2, ',', '.') }} €</td>
                    <td>
                        @php
                            $fecha = strtotime($venta->created_at);
                            $mesSiguiente = date('m', strtotime('+1 month', $fecha));
                            $meses = [
                                '01' => 'Enero',
                                '02' => 'Febrero',
                                '03' => 'Marzo',
                                '04' => 'Abril',
                                '05' => 'Mayo',
                                '06' => 'Junio',
                                '07' => 'Julio',
                                '08' => 'Agosto',
                                '09' => 'Septiembre',
                                '10' => 'Octubre',
                                '11' => 'Noviembre',
                                '12' => 'Diciembre',
                            ];
                            echo $meses[$mesSiguiente];
                        @endphp
                    </td>

                    <td>{{ number_format($venta->importe_total, 2, ',', '.') }} €</td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: left;">
                        <strong>IBAN</strong>
                        {{ trim(chunk_split($venta->customer->iban, 4, ' ')) }}
                    </td>
                    <td colspan="3">
                        {{-- $contract->type->slug == "financed" ? $statement->iban : '' --}}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- =========== TEXTO LEGAL Y FIRMA =========== -->
    <div class="section">
        <p style="text-align: justify; margin-bottom: 8px; line-height: 1 !important;">
            Le informamos que usted dispone del plazo de treinta días naturales desde la recepción de la mercancía para
            ejercer su derecho de desistimiento, en base al artículo 102 del TRLGDCU sin necesidad de alegar causa o
            justificación alguna, su ejercicio no requiere formalidad, pudiendo, si lo desea, remitir cumplimentado el
            documento de desistimiento que se le entrega con el contrato de compraventa. El derecho de desistimiento
            no se aplicará en caso de que se hayan personalizado los productos conforme a las especificaciones del
            comprador, o bien se trate de bienes precintados que, una vez desprecintados, no puedan ser devueltos por
            razones de salud o higiene, de acuerdo con el artículo 103 del TRLGDCU. En relación al tratamiento de datos
            de carácter personal: Los datos facilitados por el cliente quedan estrictamente sometidos a lo dispuesto en
            el Reglamento 2016/679 General de Protección de Datos y la Ley Orgánica 3/2018, de 5 de diciembre, de
            Protección de Datos de Carácter Personal y Garantía de los Derechos Digitales, actuando como
            Responsable de tratamiento HIDROSANE con DNI 35569025P con la finalidad de
            gestionar el desarrollo, cumplimiento y la efectiva ejecución del contrato de compraventa. Podrá ejercer sus
            derechos de acceso, rectificación, limitación de tratamiento, supresión, portabilidad y oposición al
            tratamiento de sus datos de carácter personal, así como revocar los consentimientos que en su caso haya
            prestado u obtener más información, dirigiendo su petición a info@hidrosane.es / AVDA. DO AEROPORTO, 305, 36317 CANDEAN, VIGO. Para mayor información, LEA en el reverso las Condiciones Generales de
            Contratación.
        </p>

        <table class="no-border" style="margin-bottom: 8px; padding-top: 15px !important;">
            <tr>
                <td>Fdo. El Cliente:</td>
                <td style="height: 40px; text-align: center;">Fdo. la Empresa:</td>
            </tr>
            <tr style="padding-top: 10px;">
                <td class="text-center" style="width: 50%;"></td>
                <td class="text-center" style="width: 50%;">
                    <img src="data:image/png;base64,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oPqA9ili1K3KDUREREpSQCtSzobA9lT7f8eJMbe/U3cDERGR8hTQipSzAbEpzCo853LgYWBOhecUEREZGgpoRcpZj3rKDR4AZlR8XhERkaGggFaknHWpPqB9DPitu6+p+LwiIiJDQQGtSDnrElnaqiwluhtcU+E5RUREhooCWpFy1gPWr/B8i4DpRA9aERERaYECWpFy1qe6gLbR3eAXKjcQERFpnQJakXI2obqAdhWRmb2/ovOJiIgMJQW0IuVsQbTuqsIy4Cp3f6qi84mIiAwlBbQiBZmZAQcQwxWqMA+4s6JziYiIDC0FtCLFrQvsSTUZ2jXEIIXLKziXiIjIUFNAK1Lc+sCOVNOHdjnwY3dfWsG5REREhpoCWpHiNgHGA+MqONdc4OcVnEdERGToKaAVKW6j5K3d/zdrgIeBB9tekYiIiCigFSlhQ6LswNo8zxKi3GBV+0sSERERBbQixa1HNWNv5wFXV3AeERERQQGtSBlraD+gXQXcAdzd/nJEREQEFNCKlGG0PyVsIXCluy+sYD0iIiKCAlqRMjaivQztCPAo8MNqliMiIiKggFakjA1oL6BdDtwPPFbNckRERAQU0IqUsQntDVVYCHzf3VdUtB4RERFBAa1IGRvSeobWib6zv6tuOSIiIgIKaEUKMTMj/r+0OiVsMfBLd3+4ulWJiIgIKKAVKctbeM4IUTv702qXIiIiIqCAVqQMJ/rIljUP+CNwZ7XLEREREVBAK1KIuzcys8tLPnUNcC/wZY26FRERqYcCWpFyVpY8fhFwEzCzhrWIiIgICmhFylpd4tgRYBZwqbsvrWk9IiIiQ08BrUhxKykX0C4msrM31LMcERERAQW0ImUsp3hAOwLcA/yWGKggIiIiNVFAK1LcAmBJwWMfB64DfjVqQ5mIiIjUQAGtSHHziaB2Tc5xy4lSg6nAkzWvSUREZOgpoBUp7hHgduCpjGOcaNP1DeB6ZWdFRETqp4BWpCB3Xwn8DLgj47AngauBqxXMioiIdIYCWpFyfgf8ghhlO3qD2CrgMeAK4AvuPq8LaxMRERlK63Z7ASL9xN1Xm9m3gO2AQ4GtiJrahcAtwNeAB7u3QhERkeFjuisqUp6ZbQRsD2xOZGpXAfcnZQkiIiLSQQpoRURERKSvqYZWRERERPqaAloRERER6WsKaEVERESkrymgFREREZG+poBWRERERPqaAloRERER6WsKaEVERESkrymgFREREZG+poBWRERERPqaAloRERER6WsKaEVERESkrymgFREREZG+poBWRERERPqaAloRERER6WsKaEVERESkrymgFREREZG+poBWRERERPqaAloRERER6WsKaEVERESkr/1/pvRoMYbHtMcAAAAASUVORK5CYII="
                        width="110px" alt="Firma Empresa">
                </td>
            </tr>
        </table>
        <br>
        <p style="margin-bottom: 12px;">
            En __________________, a _____ de ________________ de 20_____
        </p>
    </div>

    <!-- =========== CONDICIONES GENERALES DE CONTRATACIÓN =========== -->
    <br><br><br>
    <div class="section">
        <h2 class="title-contracts">CONDICIONES GENERALES DE CONTRATACIÓN</h2>
        <p style="text-align: justify; margin-bottom: 8px; margin-top: -10px;">
            El comprador declara que la información que ha suministrado la empresa es exacta y veraz, debiendo
            comunicar cualquier circunstancia relevante en relación a su modificación o alteración de la capacidad de
            obrar.
            PLAZO DE ENTREGA: La empresa deberá entregar a la mayor brevedad posible los bienes objeto del
            contrato de compraventa y, en todo caso, en un plazo inferior a 30 días desde la fecha del contrato, salvo
            casos de fuerza mayor o causas ajenas a la empresa que esta no pueda controlar, en cuyo caso será
            debidamente informado el consumidor. En caso de que los productos objeto del contrato se hayan
            confeccionado de forma personalizada por el consumidor y usuario, esta fecha podría ser superior, en cuyo
            caso también se le informará debidamente antes de contratar. Las medidas prefabricadas de los colchones
            son Ancho 90, 105, 120, 135, 150 cm y Largo 180 y 190 cm. Todas las medidas que difieran de las señaladas
            habrán de confeccionarse aparte al ser medidas especiales. LENGUA DEL CONTRATO: La lengua del
            presente contrato es el castellano. No obstante, el consumidor tendrá derecho a exigir que se le entregue
            una copia en la lengua oficial del lugar de celebración del contrato. Las características principales de los
            bienes objeto del contrato figuran totalmente detalladas en el catálogo y en las instrucciones que acompañan
            a cada producto previa explicación y muestra por parte del vendedor y comprobación por parte del cliente.

            DERECHO DE DESISTIMIENTO: De conformidad con lo establecido en los artículos 102 y siguientes del
            Texto Refundido de la Ley General para la Defensa de los Consumidores y Usuarios (RDL 1/2007, 16 de
            noviembre), el consumidor tiene el derecho a desistir del presente contrato en el plazo de treinta días
            naturales a contar desde la entrega del último de los productos objeto del contrato, de forma totalmente
            gratuita, sin necesidad de alegar causa alguna y sin que ello conlleve ninguna penalización. Para el
            ejercicio
            de este derecho, bastará cualquier forma admitida en derecho siempre que permita dejar constancia
            fehaciente de su ejercicio así como del día en que dicha declaración de voluntad se ha emitido. En concreto,
            podrá el consumidor remitirnos el documento de desistimiento entregado junto con el contrato, debidamente
            cumplimentado, a la sede social de la empresa, cuya dirección figura en el encabezamiento, o bien mediante
            correo electrónico. CONSECUENCIAS DEL EJERCICIO DEL DERECHO DE DESISTIMIENTO: se procederá a la restitución
            recíproca de prestaciones, en el sentido previsto en los artículos 1303 y 1308 del Código Civil. El cliente
            deberá poner a disposición los bienes objeto del contrato para que la empresa pueda recogerlos, o bien si lo
            prefiriese podrá enviar él mismo los bienes objeto del contrato. La restitución recíproca de prestaciones
            habrá de hacerse a la mayor brevedad posible, y nunca en un plazo superior a catorce días naturales. En
            caso de que por ley no le asista el derecho de desistimiento al consumidor y usuario, por las causas
            previstas en el artículo 103 TRLGDCU, este será debidamente informado de que la contratación del producto
            en cuestión no conlleva anejo el derecho de desistimiento. Esto puede ocurrir, a título ejemplificativo, con
            bienes confeccionados conforme a las especificaciones del consumidor o claramente personalizados, así como
            bienes precintados que, una vez desempaquetados tras la entrega, no puedan ser devueltos por razones de
            salud o higiene.

            GARANTÍA LEGAL DE CONFORMIDAD: Todos los productos tienen una garantía legal de
            conformidad de tres años desde la entrega del producto, consistente en la reparación, sustitución o, en su
            caso, rebaja del precio o resolución del contrato, de conformidad con lo establecido en los arts. 114 y
            siguientes TRLGDCU. En el caso de productos de naturaleza duradera, el cliente tendrá derecho a un
            adecuado servicio técnico posventa y repuestos, de conformidad con el art. 127bis TRLGDCU. CONTRATOS
            VINCULADOS O COMPLEMENTARIOS: En caso de que el cliente haya optado por la financiación como
            método de pago, total o parcial, la empresa, actuando como intermediario en la contratación con la entidad
            financiera, le informará de todas las características y modalidades de la financiación, número de cuotas,
            plazos, etc. Toda esa información será nuevamente explicada por la entidad financiera cuando
            posteriormente se ponga en contacto para confirmar la operación, todo ello de conformidad con la Ley de
            Crédito al Consumo. En caso de desistimiento del contrato, conllevará la resolución automática de todo
            contrato vinculado o complementario al mismo, sin que Ud. tenga que desistir de los mismos. Para cualquier
            comunicación, reclamación o incidencia puede dirigirse a la dirección de correo postal, electrónico o número
            de teléfono indicados en el encabezamiento.

            PROTECCIÓN DE DATOS: HIDROSANE con DNI 35569025P, responsable del tratamiento,
            informa, de conformidad con lo establecido en el REGLAMENTO (UE) 2016/679 y la Ley Orgánica 3/2018 de
            Protección de Datos, que sus datos de carácter personal son tratados con la finalidad de: (I) Gestión y
            perfeccionamiento del contrato suscrito de compraventa (incluidas gestiones de facturación). (II) Cumplir
            con las obligaciones legales impuestas a la actividad. (III) Atender y gestionar las reclamaciones de los
            interesados y servicios postventa. (IV) Enviar información sobre los productos/servicios contratados. Podrá
            ejercer sus derechos de acceso, rectificación, limitación de tratamiento, supresión, portabilidad y
            oposición al tratamiento de sus datos de carácter personal, así como revocar los consentimientos que en su
            caso haya prestado, dirigiendo su petición a info@hidrosane.es o a AVDA. DO AEROPORTO, 305, 36317 CANDEAN, VIGO.
            <br>
            El delegado de protección de datos es NEW PROJECT SERVICIOS EMPRESARIALES SL. Correo de contacto:
            DPD@NPSERVICIOS.COM.
            <br>
            Marque si presta su consentimiento expreso para:
            <br>
            º Enviar publicidad postal o por correo electrónico sobre nuestros servicios/productos.<br>
            º Realizar encuestas de opinión.<br>
            º Participar en sorteos.<br>
            º Ceder datos a entidades de crédito para su financiación (necesario si elige este método de pago).
        </p>

        <div style="margin-top: 0px;">
            <p>
                Fdo., DNI/NIE {{ strtoupper($venta->customer->dni) }}
            </p>
            <p style="padding-top:120px !important;">
                En __________________, a _____ de ________________ de 20_____
            </p>
        </div>
    </div>
</body>

</html>
