<?php

namespace App\Filament\Concerns;

use App\Filament\Support\CustomerPhoneForm;
use App\Models\Customer;
use App\Support\TeleoperatorCustomerNoteGuard;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithCreateNoteModal
{
    /** @var array{nombre:string, phone:string}|null */
    public ?array $createNoteDraft = null;

    /** @var list<array{id:int, nameParts:list<array{text:string, hit:bool}>, phones:list<array{text:string, hit:bool}>, place:string, ventaFecha:?string}> */
    public array $createNoteMatches = [];

    public function bootInteractsWithCreateNoteModal(): void
    {
        $key = 'create-note-modal.'.static::class;

        if (app()->bound($key)) {
            return;
        }

        app()->instance($key, true);

        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
            fn (): string => view('filament.head-of-room.create-note-modal')->render(),
            scopes: static::class,
        );
    }

    protected function createNoteModalAction(): Action
    {
        return Action::make('crearNota')
            ->label('Crear Nota')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->alpineClickHandler('window.horAbrirCrearNota()');
    }

    public function continuarCrearNota(string $nombre, string $phone): void
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($phone)
            ?? preg_replace('/\D+/', '', $phone);
        $digits = (string) $digits;

        if ($nombre === '' || strlen($digits) !== 9) {
            Notification::make()
                ->title('Escribe el nombre y un teléfono de 9 cifras')
                ->danger()
                ->send();
            $this->js("document.getElementById('hor-crear-nota').hidden = false");

            return;
        }

        $matches = $this->findCustomerMatches($nombre, $digits);

        if ($matches->isEmpty()) {
            $this->redirectToNewCustomerForm($nombre, $digits);

            return;
        }

        $this->createNoteDraft = [
            'nombre' => $nombre,
            'phone' => $digits,
        ];
        $words = $this->nameSearchWords($nombre);
        $this->createNoteMatches = $matches
            ->map(fn (Customer $customer): array => $this->customerMatchPayload($customer, $words, $digits))
            ->all();
        $this->dispatch('hor-crear-nota-matches', matches: $this->createNoteMatches);
    }

    public function asignarNotaACliente(mixed $customerId = null): void
    {
        $customerId = (int) $customerId;

        $customer = $customerId > 0 ? Customer::query()->find($customerId) : null;

        if (! $customer) {
            Notification::make()
                ->title('Selecciona un cliente')
                ->danger()
                ->send();
            $this->js("document.getElementById('hor-crear-nota').hidden = false");

            return;
        }

        $params = ['customer_id' => $customer->id];

        if ($this->fechaVentaRecienteDeCliente($customer) !== null) {
            $params['emergency'] = 1;
        }

        $this->redirect($this->getResource()::getUrl('create', $params));
    }

    public function crearNotaClienteNuevo(): void
    {
        $draft = $this->createNoteDraft ?? ['nombre' => '', 'phone' => ''];
        $this->redirectToNewCustomerForm(
            (string) $draft['nombre'],
            (string) $draft['phone'],
            nuevo: true,
        );
    }

    /**
     * @return Collection<int, Customer>
     */
    protected function findCustomerMatches(string $nombre, string $phone): Collection
    {
        $byPhone = app(TeleoperatorCustomerNoteGuard::class)->resolveCustomersForPhone($phone);

        $words = $this->nameSearchWords($nombre);

        $byName = collect();
        if ($words->isNotEmpty()) {
            $byName = Customer::query()
                ->where(function (Builder $query) use ($words) {
                    foreach ($words as $word) {
                        $like = '%'.$word.'%';
                        $query->where(function (Builder $inner) use ($like) {
                            $inner->where('first_names', 'like', $like)
                                ->orWhere('last_names', 'like', $like);
                        });
                    }
                })
                ->orderBy('last_names')
                ->limit(20)
                ->get();
        }

        return $byPhone->merge($byName)->unique('id')->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function nameSearchWords(string $nombre): Collection
    {
        return collect(preg_split('/\s+/u', trim($nombre)) ?: [])
            ->map(fn ($word) => trim((string) $word))
            ->filter(fn (string $word) => mb_strlen($word) >= 2)
            ->values();
    }

    /**
     * @param  Collection<int, string>  $words
     * @return array{id:int, nameParts:list<array{text:string, hit:bool}>, phones:list<array{text:string, hit:bool}>, place:string, ventaFecha:?string}
     */
    protected function customerMatchPayload(Customer $customer, Collection $words, string $digits): array
    {
        return [
            'id' => $customer->id,
            'nameParts' => $this->matchNameParts($customer, $words),
            'phones' => $this->matchPhoneParts($customer, $digits),
            'place' => collect([$customer->ciudad ?? null, $customer->provincia ?? null])->filter()->implode(', '),
            'ventaFecha' => $this->fechaVentaRecienteDeCliente($customer),
        ];
    }

    protected function fechaVentaRecienteDeCliente(Customer $customer): ?string
    {
        $recent = app(TeleoperatorCustomerNoteGuard::class)->recentVentaNote(collect([$customer]));
        $fecha = $recent['fecha'] ?? null;

        return filled($fecha) ? (string) $fecha : null;
    }

    /**
     * @param  Collection<int, string>  $words
     * @return list<array{text:string, hit:bool}>
     */
    protected function matchNameParts(Customer $customer, Collection $words): array
    {
        $first = $this->nameTokens((string) $customer->first_names);
        $last = $this->nameTokens((string) $customer->last_names);
        $parts = [];

        foreach ($first as $index => $token) {
            if ($index > 0) {
                $parts[] = ['text' => ' ', 'hit' => false];
            }
            $parts[] = ['text' => $token, 'hit' => $this->tokenMatches($token, $words)];
        }

        if ($first !== [] && $last !== []) {
            $parts[] = ['text' => ' ', 'hit' => false];
        }

        foreach ($last as $index => $token) {
            if ($index > 0) {
                $parts[] = ['text' => ' ', 'hit' => false];
            }
            $parts[] = ['text' => $token, 'hit' => $this->tokenMatches($token, $words)];
        }

        if ($parts === []) {
            $parts[] = ['text' => 'Cliente #'.$customer->id, 'hit' => false];
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    protected function nameTokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', trim($value)) ?: [];

        return array_values(array_filter($tokens, fn ($token) => $token !== ''));
    }

    /**
     * @param  Collection<int, string>  $words
     */
    protected function tokenMatches(string $token, Collection $words): bool
    {
        foreach ($words as $word) {
            if (mb_stripos($token, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{text:string, hit:bool}>
     */
    protected function matchPhoneParts(Customer $customer, string $digits): array
    {
        return collect([
            $customer->phone,
            $customer->secondary_phone,
            $customer->third_phone,
        ])
            ->filter()
            ->unique()
            ->values()
            ->map(function ($phone) use ($digits): array {
                $normalized = TeleoperatorCustomerNoteGuard::normalizePhoneDigits((string) $phone)
                    ?? preg_replace('/\D+/', '', (string) $phone);

                return [
                    'text' => CustomerPhoneForm::formatMask((string) $phone) ?: (string) $phone,
                    'hit' => (string) $normalized === $digits,
                ];
            })
            ->all();
    }

    protected function redirectToNewCustomerForm(string $nombre, string $phone, bool $nuevo = false): void
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        $parts = explode(' ', $nombre, 2);
        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($phone)
            ?? preg_replace('/\D+/', '', $phone);

        $params = [
            'first_names' => $parts[0] ?? '',
            'last_names' => $parts[1] ?? '',
            'phone' => $digits,
        ];

        if ($nuevo) {
            $params['emergency'] = 1;
            $params['nuevo'] = 1;
        }

        $this->redirect($this->getResource()::getUrl('create', $params));
    }
}
