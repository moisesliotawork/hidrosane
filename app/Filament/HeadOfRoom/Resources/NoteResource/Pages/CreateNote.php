<?php

namespace App\Filament\HeadOfRoom\Resources\NoteResource\Pages;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Models\Customer;
use App\Models\Observation;
use App\Support\TeleoperatorCustomerNoteGuard;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
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
            $this->form->fill($prefill);
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

        if (! $this->isEmergencyCreate) {
            $this->assertNoteCreationAllowed($data);
        }

        $customer = app(TeleoperatorCustomerNoteGuard::class)
            ->resolveCustomersForPhone($data['phone'])
            ->first();

        if (! $this->isEmergencyCreate) {
            $this->assertNoDuplicatePhones($data, $customer);

            if ($customer && $customer->inhabilitado) {
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
        }

        if ($customer) {
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'email' => $data['email'] ?? $customer->email,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'parish' => $data['parish'] ?? $customer->parish,
                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,
            ]);
        } else {
            $customer = Customer::create([
                'first_names' => $data['first_names'],
                'last_names' => $data['last_names'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? null,
                'secondary_address' => $data['secondary_address'] ?? null,
                'parish' => $data['parish'] ?? null,
                'edadTelOp' => $data['edadTelOp'] ?? null,
            ]);
        }

        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        unset($data['edadTelOp']);

        return $data;
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

    protected function assertNoteCreationAllowed(array $data): void
    {
        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $customers = $guard->resolveCustomersForPhones([
            $data['phone'] ?? null,
            $data['secondary_phone'] ?? null,
            $data['third_phone'] ?? null,
        ]);

        $evaluation = $guard->evaluate($customers);

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

    protected function assertNoDuplicatePhones(array $data, ?Customer $customer): void
    {
        $numerosAValidar = collect([
            $data['phone'] ?? null,
            $data['secondary_phone'] ?? null,
            $data['third_phone'] ?? null,
        ])->filter()->unique()->values();

        $duplicados = [];

        foreach ($numerosAValidar as $numero) {
            $existe = Customer::query()
                ->when($customer, fn ($q) => $q->where('id', '!=', $customer->id))
                ->where(function ($q) use ($numero) {
                    $q->where('phone', $numero)
                        ->orWhere('secondary_phone', $numero)
                        ->orWhere('third_phone', $numero)
                        ->orWhere('phone1_commercial', $numero)
                        ->orWhere('phone2_commercial', $numero);
                })
                ->exists();

            if ($existe) {
                $duplicados[] = $numero;
            }
        }

        if ($duplicados === []) {
            return;
        }

        Notification::make()
            ->title('Teléfono(s) ya registrado(s)')
            ->body(
                'Los siguientes números ya están registrados en la base de datos: ' .
                implode(', ', $duplicados) .
                '. No se puede crear la nota con teléfonos duplicados.'
            )
            ->danger()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'phone' => 'Números de teléfono duplicados: ' . implode(', ', $duplicados),
        ]);
    }
}
