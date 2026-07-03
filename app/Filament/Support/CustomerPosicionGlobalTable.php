<?php

namespace App\Filament\Support;

use App\Filament\Gerente\Resources\VentaResource;
use App\Models\Customer;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class CustomerPosicionGlobalTable
{
    public static function applyEagerLoads(Builder $query): Builder
    {
        return $query
            ->with([
                'latestNoteWithDentroGps',
                'latestNoteWithGps',
                'notes' => fn ($q) => $q
                    ->select(['id', 'customer_id', 'nro_nota'])
                    ->whereNotNull('nro_nota')
                    ->where('nro_nota', '!=', '')
                    ->orderByDesc('id'),
                'ventas' => fn ($q) => $q
                    ->select(['id', 'customer_id', 'nro_contr_adm', 'fecha_venta'])
                    ->whereNotNull('nro_contr_adm')
                    ->where('nro_contr_adm', '!=', '')
                    ->orderByDesc('fecha_venta')
                    ->orderByDesc('id'),
            ])
            ->withCount('ventas');
    }

    public static function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search): void {
            $q->where('first_names', 'like', "%{$search}%")
                ->orWhere('last_names', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(COALESCE(first_names,''), ' ', COALESCE(last_names,'')) LIKE ?", ["%{$search}%"])
                ->orWhere('dni', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('secondary_phone', 'like', "%{$search}%")
                ->orWhere('third_phone', 'like', "%{$search}%")
                ->orWhere('phone1_commercial', 'like', "%{$search}%")
                ->orWhere('phone2_commercial', 'like', "%{$search}%")
                ->orWhereHas('ventas', fn (Builder $vq) => $vq->where('nro_cliente_adm', 'like', "%{$search}%"));
        });
    }

    public static function displayName(Customer $customer): string
    {
        return mb_strtoupper(trim("{$customer->first_names} {$customer->last_names}"), 'UTF-8');
    }

    /** @param  iterable<string|null>  $phones */
    public static function formatPhones(iterable $phones): string
    {
        $badges = self::phoneBadges($phones);

        return $badges !== [] ? collect($badges)->pluck('display')->join(' | ') : '—';
    }

    /** @return list<array{display: string, tel: string}> */
    public static function phoneBadges(iterable $phones): array
    {
        return collect($phones)
            ->filter(fn ($phone) => filled($phone))
            ->map(function (string $phone): array {
                $digits = preg_replace('/\D+/', '', $phone);
                $display = $digits !== '' ? implode(' ', str_split($digits, 3)) : $phone;

                return [
                    'display' => $display,
                    'tel' => $digits !== '' ? $digits : $phone,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{display: string, tel: string}> */
    public static function clientPhoneBadges(Customer $customer): array
    {
        return self::phoneBadges([$customer->phone, $customer->secondary_phone, $customer->third_phone]);
    }

    /** @return list<array{display: string, tel: string}> */
    public static function commercialPhoneBadges(Customer $customer): array
    {
        return self::phoneBadges([$customer->phone1_commercial, $customer->phone2_commercial]);
    }

    public static function clientPhones(Customer $customer): string
    {
        return self::formatPhones([$customer->phone, $customer->secondary_phone, $customer->third_phone]);
    }

    public static function commercialPhones(Customer $customer): string
    {
        return self::formatPhones([$customer->phone1_commercial, $customer->phone2_commercial]);
    }

    public static function labeledAllPhonesHtml(Customer $customer): string
    {
        return self::labeledPhonesHtml([
            'Tlf 1' => $customer->phone,
            'Tlf 2' => $customer->secondary_phone,
            'Tlf 3' => $customer->third_phone,
            'Tlf Com 1' => $customer->phone1_commercial,
            'Tlf Com 2' => $customer->phone2_commercial,
        ]);
    }

    /** @param  array<string, string|null>  $phones */
    public static function labeledPhonesHtml(array $phones): string
    {
        $items = collect($phones)
            ->filter(fn (?string $phone) => filled($phone))
            ->map(function (string $phone, string $label): string {
                $digits = preg_replace('/\D+/', '', $phone);
                $display = $digits !== '' ? implode(' ', str_split($digits, 3)) : $phone;

                return '<span class="inline-flex flex-col items-start mr-5 mb-1">'
                    . '<span class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 uppercase">' . e($label) . '</span>'
                    . '<span class="text-sm font-bold text-amber-500">' . e($display) . '</span>'
                    . '</span>';
            })
            ->values();

        return $items->isNotEmpty() ? '<span class="flex flex-wrap items-end gap-y-1">' . $items->join('') . '</span>' : '—';
    }

    public static function streetAddress(Customer $customer): string
    {
        $parts = collect([
            filled($customer->primary_address)
                ? trim("{$customer->primary_address}" . (filled($customer->nro_piso) ? ", {$customer->nro_piso}" : ''))
                : null,
            filled($customer->secondary_address) ? trim($customer->secondary_address) : null,
        ])->filter()->values();

        return $parts->isNotEmpty() ? $parts->join(' | ') : '—';
    }

    public static function locality(Customer $customer): string
    {
        $parts = collect([
            filled($customer->ciudad) ? $customer->ciudad : null,
            filled($customer->postal_code) ? "CP {$customer->postal_code}" : null,
            filled($customer->provincia) ? $customer->provincia : null,
        ])->filter()->values();

        return $parts->isNotEmpty() ? $parts->join(' · ') : '—';
    }

    /** @deprecated Use streetAddress() and locality() */
    public static function fullAddress(Customer $customer): string
    {
        $street = self::streetAddress($customer);
        $locality = self::locality($customer);

        if ($street === '—') {
            return $locality;
        }

        if ($locality === '—') {
            return $street;
        }

        return "{$street} | {$locality}";
    }

    /** @return list<string> */
    public static function noteNumbers(Customer $customer): array
    {
        $notes = $customer->relationLoaded('notes')
            ? ($customer->getRelation('notes') ?? collect())
            : $customer->notes()->whereNotNull('nro_nota')->where('nro_nota', '!=', '')->get();

        return $notes
            ->pluck('nro_nota')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function contractNumbers(Customer $customer): array
    {
        return collect(self::contractsForCard($customer))
            ->pluck('nro')
            ->all();
    }

    /** @return list<array{nro: string, url: string}> */
    public static function contractsForCard(Customer $customer): array
    {
        $ventas = $customer->relationLoaded('ventas')
            ? ($customer->getRelation('ventas') ?? collect())
            : $customer->ventas()->whereNotNull('nro_contr_adm')->where('nro_contr_adm', '!=', '')->get();

        return $ventas
            ->filter(fn ($venta) => filled($venta->nro_contr_adm))
            ->unique('nro_contr_adm')
            ->map(fn ($venta) => [
                'nro' => (string) $venta->nro_contr_adm,
                'url' => VentaResource::getUrl('edit', ['record' => $venta], panel: 'gerente'),
            ])
            ->values()
            ->all();
    }

    public static function gpsDentroColumn(): TextColumn
    {
        return TextColumn::make('dentro_gps')
            ->label('GPS')
            ->state(fn ($record): ?string => $record->hasAnyGps() ? 'GPS' : null)
            ->placeholder('')
            ->url(fn ($record): ?string => $record->anyGpsMapsUrl())
            ->openUrlInNewTab()
            ->icon('heroicon-o-map-pin')
            ->badge()
            ->color('success')
            ->alignCenter();
    }
}
