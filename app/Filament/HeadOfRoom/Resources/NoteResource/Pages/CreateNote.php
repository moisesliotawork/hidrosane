<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Models\Customer;
use App\Models\Observation;
use App\Support\TeleoperatorCustomerNoteGuard;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;

    public bool $isEmergencyCreate = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $this->isEmergencyCreate = request()->boolean('emergency');

        // Si viene un customer_id, precargamos TODO
        if ($customerId = request()->query('customer_id')) {
            $customer = Customer::find($customerId);

            if ($customer) {
                $this->form->fill([
                    'customer_id' => $customer->id,
                    'first_names' => $customer->first_names,
                    'last_names' => $customer->last_names,
                    'phone' => $customer->phone,
                    'secondary_phone' => $customer->secondary_phone,
                    'third_phone' => $customer->third_phone,
                    'edadTelOp' => $customer->edadTelOp ?? null,
                    'email' => $customer->email,
                    'postal_code' => $customer->postal_code,
                    'nro_piso' => $customer->nro_piso,
                    'ayuntamiento' => $customer->ayuntamiento,
                    'provincia' => $customer->provincia,
                    'primary_address' => $customer->primary_address,
                    'secondary_address' => $customer->secondary_address,
                    'parish' => $customer->parish,
                ]);
            }
        }

        $prefillKeys = [
            'first_names',
            'last_names',
            'phone',
            'secondary_phone',
            'third_phone',
            'email',
            'edadTelOp',
            'primary_address',
            'secondary_address',
            'postal_code',
            'nro_piso',
            'ciudad',
            'provincia',
            'parish',
        ];

        $prefill = [];

        foreach ($prefillKeys as $key) {
            $value = request()->query($key);

            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($key, ['phone', 'secondary_phone', 'third_phone'], true)) {
                $digits = preg_replace('/\D+/', '', (string) $value);
                if (strlen($digits) === 9) {
                    $value = implode(' ', str_split($digits, 3));
                }
            }

            $prefill[$key] = $value;
        }

        if (! empty($prefill)) {
            $this->form->fill(array_merge(
                ['customer_id' => request()->query('customer_id')],
                $prefill,
            ));
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Guardar')
                ->action('create'),

            Actions\Action::make('guardarYBuscarOtro')
                ->label('Guardar y crear otro')
                ->color('gray')
                ->action(function () {
                    $this->create();

                    return redirect()->to(
                        \App\Filament\HeadOfRoom\Pages\BuscarCliente::getUrl()
                    );
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('danger')
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $thr = preg_replace('/\D+/', '', (string) ($data['third_phone'] ?? ''));

        $data['secondary_phone'] = $sec === '' ? null : $sec;
        $data['third_phone'] = $thr === '' ? null : $thr;

        $customer = $this->resolveTargetCustomer($data);

        if (! $this->isEmergencyCreate) {
            // Solo se evalúa EL cliente elegido; otro cliente con el mismo
            // teléfono no debe bloquear ni “secuestrar” la nota.
            $this->assertNoteCreationAllowedForCustomer($customer);
        }

        if ($customer->wasRecentlyCreated === false && $customer->exists) {
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'email' => $data['email'] ?? $customer->email,
                'postal_code' => $data['postal_code'] ?? $customer->postal_code,
                'ciudad' => $data['ciudad'] ?? $customer->ciudad,
                'nro_piso' => $data['nro_piso'] ?? $customer->nro_piso,
                'provincia' => $data['provincia'] ?? $customer->provincia,
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'parish' => $data['parish'] ?? $customer->parish,
                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,
            ]);
        }

        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        unset($data['edadTelOp']);

        return $data;
    }

    /**
     * Resuelve el cliente de la nota respetando teléfono compartido entre varios.
     * Prioridad: customer_id del formulario → coincidencia de nombre → único match → crear nuevo.
     */
    protected function resolveTargetCustomer(array $data): Customer
    {
        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $phoneMatches = $guard->resolveCustomersForPhone((string) ($data['phone'] ?? ''));

        $requestedId = (int) ($data['customer_id'] ?? 0);
        if ($requestedId > 0) {
            $requested = Customer::query()->find($requestedId);
            if ($requested) {
                return $requested;
            }
        }

        $byName = $this->matchCustomerByName($phoneMatches, $data);
        if ($byName) {
            return $byName;
        }

        $first = trim((string) ($data['first_names'] ?? ''));
        $last = trim((string) ($data['last_names'] ?? ''));
        $hasName = $first !== '' || $last !== '';

        if ($phoneMatches->count() === 1) {
            $only = $phoneMatches->first();
            // Mismo teléfono, nombre distinto → otro cliente (permitido).
            if ($hasName && ! $this->matchCustomerByName(collect([$only]), $data)) {
                return $this->createCustomerFromNoteData($data);
            }

            return $only;
        }

        if ($phoneMatches->isEmpty()) {
            return $this->createCustomerFromNoteData($data);
        }

        // Varios clientes con el mismo teléfono y sin customer_id/nombre claro:
        // crear uno nuevo (política: un teléfono puede tener más de un cliente).
        return $this->createCustomerFromNoteData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createCustomerFromNoteData(array $data): Customer
    {
        return Customer::create([
            'first_names' => $data['first_names'],
            'last_names' => $data['last_names'],
            'phone' => $data['phone'],
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'email' => $data['email'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'nro_piso' => $data['nro_piso'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'primary_address' => $data['primary_address'] ?? null,
            'secondary_address' => $data['secondary_address'] ?? null,
            'parish' => $data['parish'] ?? null,
            'edadTelOp' => $data['edadTelOp'] ?? null,
        ]);
    }

    /**
     * @param  Collection<int, Customer>  $candidates
     */
    protected function matchCustomerByName(Collection $candidates, array $data): ?Customer
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $first = mb_strtoupper(trim((string) ($data['first_names'] ?? '')));
        $last = mb_strtoupper(trim((string) ($data['last_names'] ?? '')));

        if ($first === '' && $last === '') {
            return null;
        }

        $exact = $candidates->first(function (Customer $customer) use ($first, $last) {
            return mb_strtoupper(trim((string) $customer->first_names)) === $first
                && mb_strtoupper(trim((string) $customer->last_names)) === $last;
        });

        if ($exact) {
            return $exact;
        }

        // Coincidencia parcial por apellidos + primer nombre (typos leves de tipografía)
        return $candidates->first(function (Customer $customer) use ($first, $last) {
            $cFirst = mb_strtoupper(trim((string) $customer->first_names));
            $cLast = mb_strtoupper(trim((string) $customer->last_names));

            if ($last !== '' && $cLast === $last && $first !== '' && str_contains($cFirst, explode(' ', $first)[0])) {
                return true;
            }

            return false;
        });
    }

    protected function afterCreate(): void
    {
        $observations = $this->form->getState()['observations'] ?? [];

        foreach ($observations as $observationData) {
            if (! empty($observationData['observation'])) {
                Observation::create([
                    'note_id' => $this->record->id,
                    'author_id' => $observationData['author_id'] ?? auth()->id(),
                    'observation' => $observationData['observation'],
                ]);
            }
        }
    }

    protected function assertNoteCreationAllowedForCustomer(Customer $customer): void
    {
        if ($customer->inhabilitado) {
            Notification::make()
                ->title('☠️ Cliente inhabilitado')
                ->body('Este cliente ya no puede ser contactado por la empresa, está descartado.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'phone' => 'Este cliente está inhabilitado y no puede ser contactado.',
            ]);
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $evaluation = $guard->evaluate(collect([$customer]));

        if ($evaluation->allowed) {
            return;
        }

        Notification::make()
            ->title('NO SE PUEDE LLAMAR')
            ->body($evaluation->message)
            ->danger()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'phone' => $evaluation->message,
        ]);
    }
}
