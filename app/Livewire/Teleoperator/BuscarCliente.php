<?php

namespace App\Livewire\Teleoperator;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\Customer;
use App\Filament\Teleoperator\Resources\NoteResource;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class BuscarCliente extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public bool $phoneNotFound = false;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,

            'primary_address' => null,
            'nro_piso' => null,
            'postal_code' => null,
            'ayuntamiento' => null,
            'provincia' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Buscar cliente')
                ->schema([
                    Forms\Components\TextInput::make('phone_query')
                        ->label('INGRESA NÚMERO DE TELÉFONO')
                        ->tel()
                        ->mask('999 999 999')
                        ->placeholder('999 999 999')
                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $digits = preg_replace('/\D+/', '', (string) $value);
                                if (strlen($digits) !== 9) {
                                    $fail('Debe tener exactamente 9 cifras.');
                                }
                            };
                        }),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('buscarTelefono')
                            ->label('Buscar')
                            ->color('warning')
                            ->action(fn() => $this->buscarTelefono()),
                    ]),

                    Forms\Components\Placeholder::make('no_encontrado')
                        ->content('NO SE ENCONTRO TELÉFONO')
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('primary_address')
                                ->label('Dirección (principal)')
                                ->placeholder('Calle')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nro_piso')
                                ->label('No. y Piso')
                                ->placeholder('Nº 1')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('postal_code')
                                ->label('Código Postal')
                                ->placeholder('15551')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('ayuntamiento')
                                ->label('Ayuntamiento/Localidad')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('provincia')
                                ->label('Provincia')
                                ->placeholder('CORUÑA')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),
                        ])
                        ->visible(fn() => $this->phoneNotFound),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('buscarDireccion')
                            ->label('Buscar por dirección')
                            ->color('info')
                            ->visible(fn() => $this->phoneNotFound)
                            ->action(fn() => $this->buscarDireccion()),
                    ]),
                ])
                ->columns(1),
        ])->statePath('data');
    }

    protected function notifyNotaDuplicada(string $detalle): void
    {
        Notification::make()
            ->title('NOTA DUPLICADA')
            ->body($detalle)
            ->danger()
            ->persistent()
            ->send();
    }

    protected function notifyClienteAntiguo(string $detalle): void
    {
        Notification::make()
            ->title('CLIENTE EXISTE')
            ->body($detalle)
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * Regla: si el cliente tiene notas, y la última es >= 5 meses, permitir crear nota y avisar.
     * Si la última es < 5 meses, bloquear (duplicada).
     */
    protected function handleCustomerFound(Customer $customer, ?string $digits = null, array $extraCreateParams = []): void
    {
        // OJO: asumo relación $customer->notes() existe (hasMany Note::class)
        $lastNote = $customer->notes()->latest('created_at')->first();

        // Si no tiene notas, normalmente lo dejamos crear nota (no pediste notificación aquí)
        if (!$lastNote) {
            redirect()->to(NoteResource::getUrl('create', array_merge([
                'customer_id' => $customer->id,
                'phone' => $digits ?: null,
            ], $extraCreateParams)));
            return;
        }

        $fiveMonthsAgo = now()->subMonthsNoOverflow(5);

        if ($lastNote->created_at?->lte($fiveMonthsAgo)) {
            $fecha = optional($lastNote->created_at)->format('d/m/Y');

            $this->notifyClienteAntiguo("El cliente existe, pero la última llamada/nota fue el {$fecha} (hace más de 5 meses). Puedes crear una nota nueva.");

            redirect()->to(NoteResource::getUrl('create', array_merge([
                'customer_id' => $customer->id,
                'phone' => $digits ?: null,
            ], $extraCreateParams)));
            return;
        }

        // Última nota reciente (<5 meses): lo tratamos como duplicado
        $fecha = optional($lastNote->created_at)->format('d/m/Y');
        $this->notifyNotaDuplicada("El cliente ya fue llamado recientemente. Última nota: {$fecha}.");
        redirect()->to(NoteResource::getUrl('index'));
    }

    public function buscarTelefono(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        if (strlen($digits) !== 9) {
            $this->phoneNotFound = false;
            return;
        }

        // 🔥 En vez de exists(), traemos el cliente
        $customer = Customer::query()
            ->where('phone', $digits)
            ->orWhere('secondary_phone', $digits)
            ->first();

        if ($customer) {
            $this->phoneNotFound = false;
            $this->handleCustomerFound($customer, $digits);
            return;
        }

        // ❌ No existe: mostramos mensaje + habilitamos dirección (paso 2)
        $this->phoneNotFound = true;
    }

    public function buscarDireccion(): void
    {
        $state = $this->form->getState();

        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        $norm = function (?string $v): string {
            $v = trim((string) $v);
            $v = preg_replace('/\s+/u', ' ', $v);
            return mb_strtolower($v, 'UTF-8');
        };

        $primaryAddress = $norm($state['primary_address'] ?? null);
        $nroPiso = $norm($state['nro_piso'] ?? null);
        $postalCode = $norm($state['postal_code'] ?? null);
        $ayto = $norm($state['ayuntamiento'] ?? null);
        $provincia = $norm($state['provincia'] ?? null);

        if ($primaryAddress === '' || $nroPiso === '' || $postalCode === '' || $ayto === '' || $provincia === '') {
            Notification::make()
                ->title('Faltan datos')
                ->body('Completa Dirección, No. y Piso, Código Postal, Ayuntamiento y Provincia.')
                ->warning()
                ->send();
            return;
        }

        // 🔥 En vez de exists(), traemos el cliente matching
        $customer = Customer::query()
            ->whereRaw('LOWER(TRIM(primary_address)) = ?', [$primaryAddress])
            ->whereRaw('LOWER(TRIM(nro_piso)) = ?', [$nroPiso])
            ->whereRaw('LOWER(TRIM(postal_code)) = ?', [$postalCode])
            ->whereRaw('LOWER(TRIM(ciudad)) = ?', [$ayto])
            ->whereRaw('LOWER(TRIM(provincia)) = ?', [$provincia])
            ->first();

        if ($customer) {
            // aplica la regla de 5 meses y redirección
            $this->handleCustomerFound($customer, $digits, [
                // por si quieres precargar también dirección en create
                'primary_address' => $state['primary_address'] ?? null,
                'nro_piso' => $state['nro_piso'] ?? null,
                'postal_code' => $state['postal_code'] ?? null,
                'ayuntamiento' => $state['ayuntamiento'] ?? null,
                'provincia' => $state['provincia'] ?? null,
            ]);
            return;
        }

        // ✅ No existe: crear nota nueva con lo digitado
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $digits ?: null,
            'primary_address' => $state['primary_address'] ?? null,
            'nro_piso' => $state['nro_piso'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'ayuntamiento' => $state['ayuntamiento'] ?? null,
            'provincia' => $state['provincia'] ?? null,
        ]));
    }

    public function render()
    {
        return view('livewire.teleoperator.buscar-cliente');
    }
}
