<?php

namespace App\Enums;

enum EstadoVenta: string
{
    case EN_REVISION = 'en_revision';
    case COMITE = 'comite';
    case STAND_BY = 'stand_by';
    case EN_REPARTO = 'en_reparto';
    case NULO_EN_REPARTO = 'nulo_en_reparto';
    case FACTURADO = 'facturado';
    case FALTAN_DATOS = 'faltan_datos';
    case NULO_POR_OFICINA = 'nulo_por_oficina';
    case PENDIENTE_DE_COBRO = 'pendiente_de_cobro';
    case RETROCESO = 'retroceso';
    case NULO_FINANCIERO = 'nulo_financiero';
    case NO_SALE_A_CALLE = 'no_sale_a_calle';
    case NULO_POR_AUSENTE = 'nulo_por_ausente';

    /**
     * Devuelve la descripción legible de cada estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_REVISION => 'En revisión',
            self::COMITE => 'Comité',
            self::STAND_BY => 'Stand-by',
            self::EN_REPARTO => 'En reparto',
            self::NULO_EN_REPARTO => 'Nulo en reparto',
            self::FACTURADO => 'Facturado',
            self::FALTAN_DATOS => 'Faltan datos',
            self::NULO_POR_OFICINA => 'Nulo por oficina',
            self::PENDIENTE_DE_COBRO => 'Pendiente de cobro',
            self::RETROCESO => 'Retroceso',
            self::NULO_FINANCIERO => 'Nulo financiero',
            self::NO_SALE_A_CALLE => 'No sale a calle',
            self::NULO_POR_AUSENTE => 'Nulo por ausente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_REVISION => 'info',           // Azul
            self::COMITE => 'danger',              // Rojo
            self::STAND_BY => 'primary',           // Ámbar
            self::EN_REPARTO => 'success',         // Verde
            self::NULO_EN_REPARTO => 'orange',     // Naranja
            self::FACTURADO => 'gray',             // Gris
            self::FALTAN_DATOS => 'yellow',        // Amarillo
            self::NULO_POR_OFICINA => 'pink',      // Rosa
            self::PENDIENTE_DE_COBRO => 'warning', // Ámbar
            self::RETROCESO => 'orange',           // Naranja
            self::NULO_FINANCIERO => 'teal',       // Verde azulado
            self::NO_SALE_A_CALLE => 'purple',     // Morado
            self::NULO_POR_AUSENTE => 'lime',      // Verde lima
        };
    }

}
