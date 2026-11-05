<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class NroContratoAdmin
{
    /**
     * Valores de nro_contr_adm que corresponden al término buscado.
     *
     * "1" y "001" encuentran el contrato 1 (y 1-B), no el 791 ni el 1001.
     *
     * @return list<string>
     */
    public static function searchValues(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $compact = preg_replace('/\s+/', '', $term) ?: $term;
        $bOnly = false;
        $base = $compact;

        if (preg_match('/^(.*)-([Bb])$/', $compact, $matches)) {
            $base = $matches[1];
            $bOnly = true;
        } elseif (preg_match('/^(\d+)[Bb]$/', $compact, $matches)) {
            // Abby a veces guardó el titular como 382B (sin guion).
            $base = $matches[1];
        }

        $out = [$term, $compact];

        if ($base !== '' && ctype_digit($base)) {
            $int = ltrim($base, '0');
            if ($int === '') {
                $int = '0';
            }

            $variants = [$int];
            foreach ([2, 3, 4, 5] as $width) {
                $variants[] = str_pad($int, $width, '0', STR_PAD_LEFT);
            }

            foreach (array_unique($variants) as $variant) {
                if ($bOnly) {
                    $out[] = $variant.'-B';
                    $out[] = $variant.'-b';
                    $out[] = $variant.'B';
                    $out[] = $variant.'b';
                } else {
                    $out[] = $variant;
                    $out[] = $variant.'-B';
                    $out[] = $variant.'-b';
                    $out[] = $variant.'B';
                    $out[] = $variant.'b';
                }
            }
        }

        return array_values(array_unique(array_filter($out, fn (string $value): bool => $value !== '')));
    }

    /**
     * Nº que el listado está buscando: caja de SuperAdmin o columna Nº Contrato.
     */
    public static function resolveNroSearchTermFromLivewire(object $livewire): ?string
    {
        if (isset($livewire->nroContratoBusqueda)) {
            $term = trim((string) $livewire->nroContratoBusqueda);
            if ($term !== '') {
                return $term;
            }
        }

        if (isset($livewire->tableColumnSearches) && is_array($livewire->tableColumnSearches)) {
            $term = trim((string) ($livewire->tableColumnSearches['nro_contr_adm'] ?? ''));
            if ($term !== '') {
                return $term;
            }
        }

        return null;
    }

    /**
     * Admin y gerente no listan los -B, salvo que se busque un número concreto
     * (entonces sí tiene que aparecer el 1-B junto al 1).
     */
    public static function constrainAdminGerenteList(Builder $query, ?string $nroSearch): void
    {
        if (filled($nroSearch)) {
            return;
        }

        $query->where(function (Builder $q): void {
            $q->whereNull('nro_contr_adm')
                ->orWhere('nro_contr_adm', '=', '')
                ->orWhere('nro_contr_adm', 'not like', '%-B%');
        });
    }
}
