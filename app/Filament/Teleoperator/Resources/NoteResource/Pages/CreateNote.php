<?php

namespace App\Filament\Teleoperator\Resources\NoteResource\Pages;

use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Observation;
use Illuminate\Support\Str;
use Filament\Actions;
use Filament\Actions\Action;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
    protected array $pendingObservations = [];


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $prefill = [];

        // 1) Si viene un customer_id, cargamos TODO desde BD
        $customerId = request('customer_id');

        if ($customerId) {
            $customer = Customer::query()->find($customerId);

            if ($customer) {
                // ⚠️ Ajusta esta lista EXACTAMENTE a los campos que existen en tu formulario de Note
                $prefillFromCustomer = [
                    'first_names' => $customer->first_names,
                    'last_names' => $customer->last_names,
                    'phone' => $customer->phone,
                    'secondary_phone' => $customer->secondary_phone,
                    'email' => $customer->email,

                    'primary_address' => $customer->primary_address,
                    'secondary_address' => $customer->secondary_address,
                    'nro_piso' => $customer->nro_piso,
                    'postal_code' => $customer->postal_code,
                    'ciudad' => $customer->ciudad,
                    'provincia' => $customer->provincia,

                    'edadTelOp' => $customer->edadTelOp,
                    'fecha_nac' => $customer->fecha_nac ?? null, // si existe en Customer
                    // agrega aquí cualquier otro campo que tengas en Customer y también en el form
                ];

                // Formatea teléfonos para máscara "999 999 999" (solo para mostrar)
                $prefillFromCustomer['phone'] = $this->formatPhoneMask($prefillFromCustomer['phone'] ?? null);
                $prefillFromCustomer['secondary_phone'] = $this->formatPhoneMask($prefillFromCustomer['secondary_phone'] ?? null);

                $prefillFromCustomer = array_filter($prefillFromCustomer, fn($v) => $v !== null && $v !== '');

                $prefill = $prefillFromCustomer;
            }
        }

        // 2) Prefill por request (sirve cuando NO existe customer y vienes de "buscarDireccion")
        $prefillFromRequest = [
            'phone' => request('phone'),
            'primary_address' => request('primary_address'),
            'postal_code' => request('postal_code'),
            'ciudad' => request('ayuntamiento'),
            'provincia' => request('provincia'),
            'nro_piso' => request('nro_piso'),
        ];

        // formatea phone request para máscara
        if (!empty($prefillFromRequest['phone'])) {
            $prefillFromRequest['phone'] = $this->formatPhoneMask($prefillFromRequest['phone']);
        }

        $prefillFromRequest = array_filter($prefillFromRequest, fn($v) => $v !== null && $v !== '');

        // 3) Si hay request, que sobreescriba (por si el operador escribió algo distinto)
        $prefill = array_merge($prefill, $prefillFromRequest);

        // 4) Fill final
        if (!empty($prefill)) {
            $this->form->fill($prefill);
        }
    }

    protected function formatPhoneMask(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) !== 9) {
            return $value ?: null;
        }

        return implode(' ', str_split($digits, 3));
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
                    // 1) Guarda el registro (usa el flujo normal)
                    $this->create();

                    // 2) Redirige a la página de búsqueda
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
        // ===== 1) Normalizar teléfonos (solo dígitos) =====
        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $thr = preg_replace('/\D+/', '', (string) ($data['third_phone'] ?? ''));

        $data['secondary_phone'] = $sec === '' ? null : $sec;
        $data['third_phone'] = $thr === '' ? null : $thr;

        // ===== 2) Buscar cliente existente SOLO por teléfono =====
        // (lo busca en phone, secondary_phone, third_phone, phone1_commercial o phone2_commercial)
        $customer = null;

        if (!empty($data['phone'])) {
            $customer = Customer::query()
                ->where(function ($q) use ($data) {
                    $q->where('phone', $data['phone'])
                        ->orWhere('secondary_phone', $data['phone'])
                        ->orWhere('third_phone', $data['phone'])
                        ->orWhere('phone1_commercial', $data['phone'])
                        ->orWhere('phone2_commercial', $data['phone']);
                })
                ->first();
        }

        // ===== 3) Validar duplicados de teléfonos (excluyendo el customer encontrado) =====
        // Regla: ningún número del form puede existir en otro customer (en cualquiera de los 5 campos)
        $numerosAValidar = collect([
            $data['phone'] ?? null,
            $data['secondary_phone'] ?? null,
            $data['third_phone'] ?? null,
        ])->filter()->unique()->values();

        $duplicados = [];

        foreach ($numerosAValidar as $numero) {
            $existe = Customer::query()
                ->when($customer, fn($q) => $q->where('id', '!=', $customer->id))
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

        if (!empty($duplicados)) {
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

        // ===== 4) Calcular edad desde fecha_nac (si viene) =====
        $fechaNac = $data['fecha_nac'] ?? null;
        $computedAge = null;

        if ($fechaNac) {
            try {
                $computedAge = Carbon::parse($fechaNac)->age;
            } catch (\Throwable $e) {
                $computedAge = null;
            }
        }

        // ===== 5) Crear o actualizar Customer (según exista por teléfono) =====
        if ($customer) {
            $customer->update([
                // Si quieres permitir actualizar nombres con lo que escribió la teleoperadora:
                'first_names' => $data['first_names'] ?? $customer->first_names,
                'last_names' => $data['last_names'] ?? $customer->last_names,

                // OJO: no forzamos cambios de "phone" aquí para no romper estructura.
                // Solo actualizamos datos adicionales.
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'third_phone' => $data['third_phone'] ?? $customer->third_phone,

                'email' => $data['email'] ?? $customer->email,

                'postal_code' => $data['postal_code'] ?? $customer->postal_code,
                'ciudad' => $data['ciudad'] ?? $customer->ciudad,
                'nro_piso' => $data['nro_piso'] ?? $customer->nro_piso,
                'provincia' => $data['provincia'] ?? $customer->provincia,

                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,

                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,

                // Si en tu tabla customers existe fecha_nac y quieres guardarla:
                // 'fecha_nac' => $data['fecha_nac'] ?? $customer->fecha_nac,
            ]);
        } else {
            $customer = Customer::create([
                'first_names' => $data['first_names'] ?? null,
                'last_names' => $data['last_names'] ?? null,
                'phone' => $data['phone'],

                'secondary_phone' => $data['secondary_phone'] ?? null,
                'third_phone' => $data['third_phone'] ?? null,

                'email' => $data['email'] ?? null,

                'postal_code' => $data['postal_code'] ?? null,
                'ciudad' => $data['ciudad'] ?? null,
                'nro_piso' => $data['nro_piso'] ?? null,
                'provincia' => $data['provincia'] ?? null,

                'primary_address' => $data['primary_address'] ?? null,
                'secondary_address' => $data['secondary_address'] ?? null,

                'edadTelOp' => $data['edadTelOp'] ?? null,

                // Si existe en customers:
                // 'fecha_nac' => $data['fecha_nac'] ?? null,
            ]);
        }

        // ===== 6) Asignar IDs en la Note =====
        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        // No guardar campos ajenos a notes
        unset($data['edadTelOp']);

        // Observations (igual que ya lo tienes)
        $this->pendingObservations = $data['observations'] ?? [];
        unset($data['observations']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingObservations as $row) {
            $text = trim((string) ($row['observation'] ?? ''));

            if ($text === '') {
                continue;
            }

            Observation::create([
                'note_id' => $this->record->id,
                'author_id' => Auth::id(),
                'observation' => $text,
            ]);
        }
    }

}
