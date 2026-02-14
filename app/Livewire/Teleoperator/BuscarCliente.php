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
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;

class BuscarCliente extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public bool $phoneNotFound = false;
    public array $addressMatches = [];
    public ?string $addressMatchesTitle = null;

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
                                ->minLength(5)
                                ->maxLength(5)
                                ->placeholder('15551')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\TextInput::make('ayuntamiento')
                                ->label('Ayuntamiento/Localidad')
                                ->required()
                                ->visible(fn() => $this->phoneNotFound),

                            Forms\Components\Select::make('provincia')
                                ->label('Provincia')
                                ->required()
                                ->options([
                                    'Pontevedra' => 'Pontevedra',
                                    'A Coruña' => 'A Coruña',
                                    'Orense' => 'Orense',
                                    'Lugo' => 'Lugo',
                                ])
                                ->placeholder('Pontevedra')
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
     * Regla (por MESES calendario):
     * - En cualquier día del mes actual, se permite crear nota si la última nota
     *   pertenece al mes "5 meses atrás" o anterior (ej: 01-Feb permite Sep y antes).
     *
     * Implementación:
     * - Corte = primer día del mes de hace 4 meses.
     *   Ej: now = Feb 2026 -> cutoff = 2025-10-01. Todo lo < cutoff es Sep o antes => permitir.
     */
    protected function handleCustomerFound(Customer $customer, ?string $digits = null, array $extraCreateParams = []): void
    {
        $lastNote = $customer->notes()->latest('created_at')->first();

        // Si no tiene notas, permitir crear nota
        if (!$lastNote) {
            redirect()->to(NoteResource::getUrl('create', array_merge([
                'customer_id' => $customer->id,
                'phone' => $digits ?: null,
            ], $extraCreateParams)));
            return;
        }

        // ✅ Regla por meses (NO exacto en días)
        $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

        if ($lastNote->created_at?->lt($cutoff)) {
            $fecha = optional($lastNote->created_at)->format('d/m/Y');

            // Texto opcional: mostrar mes límite (para que el operador lo entienda)
            $mesLimite = $cutoff->copy()->subMonthNoOverflow()->translatedFormat('F Y'); // mes anterior al cutoff = "mes permitido"
            $this->notifyClienteAntiguo(
                "El cliente existe, pero la última llamada/nota fue el {$fecha}. " .
                "Regla por meses: permitido si la última nota es de {$mesLimite} o antes. Puedes crear una nota nueva."
            );

            redirect()->to(NoteResource::getUrl('create', array_merge([
                'customer_id' => $customer->id,   // ✅ clave
                'phone' => $digits ?: null,
            ], $extraCreateParams)));
            return;
        }

        // ❌ Última nota reciente: duplicada
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

        $customer = Customer::query()
            ->where('phone', $digits)
            ->orWhere('secondary_phone', $digits)
            ->first();

        if ($customer) {
            $this->phoneNotFound = false;
            $this->handleCustomerFound($customer, $digits);
            return;
        }

        $this->phoneNotFound = true;
    }

    public function addressMatchesAction(): Action
    {
        return Action::make('addressMatches')
            ->label('Coincidencias')
            ->modalHeading($this->addressMatchesTitle ?: 'Coincidencias por dirección')
            ->modalWidth('7xl')
            ->modalSubmitAction(false) // no botón submit
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(view('livewire.teleoperator.modals.address-matches', [
                'rows' => $this->addressMatches,
            ]));
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

        // ✅ número para buscar token en nro_piso
        $numeroSolo = preg_replace('/\D+/', '', $nroPiso);

        // ✅ aquí acumulamos por prioridad
        $matchesByPriority = [
            'nro_piso' => collect(),
            'postal_code' => collect(),
            'provincia' => collect(),
            'ciudad' => collect(),
            'primary_address' => collect(),
        ];

        // Q1: nro/piso (más importante)
        $matchesByPriority['nro_piso'] = Customer::query()
            ->when($numeroSolo !== '', fn($q) => $q->whereRaw(
                "LOWER(TRIM(nro_piso)) REGEXP ?",
                ['(^|[^0-9])' . $numeroSolo . '([^0-9]|$)']
            ))
            ->when($numeroSolo === '', fn($q) => $q->whereRaw(
                "LOWER(TRIM(nro_piso)) LIKE ?",
                ['%' . $nroPiso . '%']
            ))
            ->limit(50)
            ->get();

        // Q2: postal
        $matchesByPriority['postal_code'] = Customer::query()
            ->whereRaw("LOWER(TRIM(postal_code)) = ?", [$postalCode])
            ->limit(50)
            ->get();

        // Q3: provincia
        $matchesByPriority['provincia'] = Customer::query()
            ->whereRaw("LOWER(TRIM(provincia)) = ?", [$provincia])
            ->limit(50)
            ->get();

        // Q4: ciudad/ayuntamiento  (en DB es ciudad)
        $matchesByPriority['ciudad'] = Customer::query()
            ->whereRaw("LOWER(TRIM(ciudad)) = ?", [$ayto])
            ->limit(50)
            ->get();

        // Q5: dirección principal (LIKE)
        $matchesByPriority['primary_address'] = Customer::query()
            ->whereRaw("LOWER(TRIM(primary_address)) LIKE ?", ['%' . $primaryAddress . '%'])
            ->limit(50)
            ->get();

        // ✅ Unir en una sola colección, sin duplicados, preservando prioridad
        $allCustomers = collect();
        foreach ($matchesByPriority as $group => $coll) {
            foreach ($coll as $c) {
                if (!$allCustomers->contains('id', $c->id)) {
                    // guardamos también “por qué coincidió primero”
                    $c->match_group = $group; // propiedad dinámica para la tabla del modal
                    $allCustomers->push($c);
                }
            }
        }

        // ✅ Si NO hay coincidencias en NINGÚN query → crear nota
        if ($allCustomers->isEmpty()) {
            redirect()->to(NoteResource::getUrl('create', [
                'phone' => $digits ?: null,
                'primary_address' => $state['primary_address'] ?? null,
                'nro_piso' => $state['nro_piso'] ?? null,
                'postal_code' => $state['postal_code'] ?? null,
                'ayuntamiento' => $state['ayuntamiento'] ?? null,
                'provincia' => $state['provincia'] ?? null,
            ]));
            return;
        }

        // ✅ Si hay 1 coincidencia TOTAL → usa flujo normal
        if ($allCustomers->count() === 1) {
            $customer = $allCustomers->first();

            $this->handleCustomerFound($customer, $digits, [
                'primary_address' => $state['primary_address'] ?? null,
                'nro_piso' => $state['nro_piso'] ?? null,
                'postal_code' => $state['postal_code'] ?? null,
                'ayuntamiento' => $state['ayuntamiento'] ?? null,
                'provincia' => $state['provincia'] ?? null,
            ]);
            return;
        }

        // ✅ Si hay varias → cargar notas y abrir modal
        $customers = Customer::query()
            ->whereIn('id', $allCustomers->pluck('id')->all())
            ->with(['notes' => fn($q) => $q->latest('created_at')->take(10)])
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($allCustomers as $lightCustomer) {
            $c = $customers->get($lightCustomer->id);
            if (!$c)
                continue;

            $notes = $c->notes ?? collect();
            $customerName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: ($c->name ?? 'Cliente');

            $baseCreateParams = array_filter([
                'customer_id' => $c->id,
                'phone' => $digits ?: ($c->phone ?? null),
                'primary_address' => $state['primary_address'] ?? null,
                'nro_piso' => $state['nro_piso'] ?? null,
                'postal_code' => $state['postal_code'] ?? null,
                'ayuntamiento' => $state['ayuntamiento'] ?? null,
                'provincia' => $state['provincia'] ?? null,
            ]);

            $matchLabel = match ($lightCustomer->match_group ?? null) {
                'nro_piso' => 'Coincide No. y Piso',
                'postal_code' => 'Coincide Código Postal',
                'provincia' => 'Coincide Provincia',
                'ciudad' => 'Coincide Ayuntamiento',
                'primary_address' => 'Coincide Dirección',
                default => 'Coincidencia',
            };

            if ($notes->isEmpty()) {
                $rows[] = [
                    'match_reason' => $matchLabel,
                    'customer_id' => $c->id,
                    'customer_name' => $customerName,
                    'customer_phone' => $c->phone ?? null,
                    'customer_address' => trim(($c->primary_address ?? '') . ' ' . ($c->nro_piso ?? '')),
                    'note_id' => null,
                    'note_date' => null,
                    'note_status' => null,
                    'note_excerpt' => 'Sin notas registradas',
                    'create_url' => NoteResource::getUrl('create', $baseCreateParams),
                ];
                continue;
            }

            foreach ($notes as $n) {
                $rows[] = [
                    'match_reason' => $matchLabel,
                    'customer_id' => $c->id,
                    'customer_name' => $customerName,
                    'customer_phone' => $c->phone ?? null,
                    'customer_address' => trim(($c->primary_address ?? '') . ' ' . ($c->nro_piso ?? '')),
                    'note_id' => $n->id,
                    'note_date' => optional($n->created_at)->format('d/m/Y H:i'),
                    'note_status' => $n->status ?? null,
                    'note_excerpt' => \Illuminate\Support\Str::limit((string) ($n->note ?? ''), 80),
                    'create_url' => NoteResource::getUrl('create', $baseCreateParams),
                ];
            }
        }

        $this->addressMatchesTitle = "Coincidencias encontradas: {$allCustomers->count()}";
        $this->addressMatches = $rows;

        $this->mountAction('addressMatches');
    }

    public function render()
    {
        return view('livewire.teleoperator.buscar-cliente');
    }
}
