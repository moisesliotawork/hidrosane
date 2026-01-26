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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();

        $prefill = [
            'phone' => request('phone'),
            'primary_address' => request('primary_address'),
            // si luego pasas más:
            // 'postal_code' => request('postal_code'),
            // 'ciudad' => request('ciudad'),
            // 'provincia' => request('provincia'),
            // 'nro_piso' => request('nro_piso'),
        ];

        // formatea phone para la máscara
        if (!empty($prefill['phone'])) {
            $digits = preg_replace('/\D+/', '', (string) $prefill['phone']);
            if (strlen($digits) === 9) {
                $prefill['phone'] = implode(' ', str_split($digits, 3));
            }
        }

        // limpia null/vacíos
        $prefill = array_filter($prefill, fn($v) => $v !== null && $v !== '');

        $this->form->fill($prefill);
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
        // ===== Normalizar teléfonos recibidos del formulario =====
        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));

        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $data['secondary_phone'] = $sec === '' ? null : $sec;

        // ===== Buscar cliente existente por nombre + phone principal =====
        $normalizedFirstName = Str::slug(Str::lower($data['first_names']), '');
        $normalizedLastName = Str::slug(Str::lower($data['last_names']), '');

        $customer = Customer::query()
            ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirstName])
            ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLastName])
            ->where('phone', $data['phone'])
            ->first();

        // ===== Validar duplicados de teléfonos en cualquier columna de Customer =====
        $numerosAValidar = collect([
            $data['phone'] ?? null,
            $data['secondary_phone'] ?? null,
        ])->filter();

        $duplicados = [];

        foreach ($numerosAValidar as $numero) {
            $existe = Customer::query()
                // si ya detectamos un customer "actual", no queremos contarlo como duplicado de sí mismo
                ->when($customer, fn($q) => $q->where('id', '!=', $customer->id))
                ->where(function ($q) use ($numero) {
                    $q->where('phone', $numero)
                        ->orWhere('secondary_phone', $numero)
                        ->orWhere('third_phone', $numero); // ⇐ ajusta el nombre si es distinto
                })
                ->exists();

            if ($existe) {
                $duplicados[] = $numero;
            }
        }

        if (!empty($duplicados)) {
            // Notificación al usuario
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

            // Lanza error de validación para bloquear la creación
            throw ValidationException::withMessages([
                'phone' => 'Números de teléfono duplicados: ' . implode(', ', $duplicados),
            ]);
        }

        // ===== Calcular edad desde fecha_nac (si viene) =====
        $fechaNac = $data['fecha_nac'] ?? null;
        $computedAge = null;
        if ($fechaNac) {
            try {
                $computedAge = Carbon::parse($fechaNac)->age;
            } catch (\Throwable $e) {
                $computedAge = null;
            }
        }

        // ===== Crear o actualizar Customer =====
        if ($customer) {
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                // third_phone NO viene del form, se mantiene como está
                'email' => $data['email'] ?? $customer->email,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,
            ]);
        } else {
            $customer = Customer::create([
                'first_names' => $data['first_names'],
                'last_names' => $data['last_names'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                // third_phone no viene del form, así que queda null
                'email' => $data['email'] ?? null,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? null,
                'secondary_address' => $data['secondary_address'] ?? null,
                'edadTelOp' => $data['edadTelOp'] ?? null,
            ]);
        }

        // ===== Asignar IDs en la Note =====
        $data['user_id'] = Auth::id();
        $data['customer_id'] = $customer->id;
        $data['comercial_id'] = null;

        // No guardar campos ajenos a notes
        unset($data['edadTelOp']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $observations = $this->form->getState()['observations'] ?? [];

        foreach ($observations as $observationData) {
            if (!empty($observationData['observation'])) {
                Observation::create([
                    'note_id' => $this->record->id,
                    'author_id' => Auth::id(),
                    'observation' => $observationData['observation'],
                ]);
            }
        }
    }
}
