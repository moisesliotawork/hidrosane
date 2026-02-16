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
use Illuminate\Support\Facades\DB;

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
            'secondary_address' => null, // ✅ nuevo
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

                            Forms\Components\TextInput::make('secondary_address') // ✅ nuevo
                                ->label('Dirección (secundaria)')
                                ->placeholder('Bloque / Escalera / Referencia')
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

            $mesLimite = $cutoff->copy()->subMonthNoOverflow()->translatedFormat('F Y');
            $this->notifyClienteAntiguo(
                "El cliente existe, pero la última llamada/nota fue el {$fecha}. " .
                "Regla por meses: permitido si la última nota es de {$mesLimite} o antes. Puedes crear una nota nueva."
            );

            redirect()->to(NoteResource::getUrl('create', array_merge([
                'customer_id' => $customer->id,
                'phone' => $digits ?: null,
            ], $extraCreateParams)));
            return;
        }

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
            ->modalSubmitAction(false)
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
        $secondaryAddress = $norm($state['secondary_address'] ?? null);
        $nroPiso = $norm($state['nro_piso'] ?? null);
        $postalCode = $norm($state['postal_code'] ?? null);
        $ciudad = $norm($state['ayuntamiento'] ?? null); // DB: ciudad
        $provincia = $norm($state['provincia'] ?? null);

        if (
            $primaryAddress === '' &&
            $secondaryAddress === '' &&
            $nroPiso === '' &&
            $postalCode === '' &&
            $ciudad === '' &&
            $provincia === ''
        ) {
            Notification::make()
                ->title('Faltan datos')
                ->body('Escribe al menos un dato (nro/piso, CP, provincia, ciudad, dirección).')
                ->warning()
                ->send();
            return;
        }

        // ✅ OR por campo + razones
        $allIds = collect();
        $reasonsById = [];

        $pushIds = function (string $group, \Illuminate\Support\Collection $ids) use (&$allIds, &$reasonsById) {
            foreach ($ids as $row) {
                $id = is_object($row) ? $row->id : $row;

                if (!$allIds->contains($id)) {
                    $allIds->push($id);
                }

                $reasonsById[$id] ??= [];
                $reasonsById[$id][] = $group;
            }
        };

        // 1) nro_piso
        if ($nroPiso !== '') {
            $numeroSolo = preg_replace('/\D+/', '', $nroPiso);

            $q = Customer::query()->select('id');

            if ($numeroSolo !== '') {
                $q->whereRaw(
                    "LOWER(TRIM(nro_piso)) REGEXP ?",
                    ['(^|[^0-9])' . $numeroSolo . '([^0-9]|$)']
                );
            } else {
                $q->whereRaw("LOWER(TRIM(nro_piso)) LIKE ?", ['%' . $nroPiso . '%']);
            }

            $pushIds('nro_piso', $q->limit(200)->get());
        }

        // 2) provincia
        if ($provincia !== '') {
            $pushIds(
                'provincia',
                Customer::query()
                    ->select('id')
                    ->whereRaw("LOWER(TRIM(provincia)) LIKE ?", ['%' . $provincia . '%'])
                    ->limit(200)
                    ->get()
            );
        }

        // 3) ciudad
        if ($ciudad !== '') {
            $pushIds(
                'ciudad',
                Customer::query()
                    ->select('id')
                    ->whereRaw("LOWER(TRIM(ciudad)) LIKE ?", ['%' . $ciudad . '%'])
                    ->limit(200)
                    ->get()
            );
        }

        // 4) CP
        if ($postalCode !== '') {
            $pushIds(
                'postal_code',
                Customer::query()
                    ->select('id')
                    ->whereRaw("LOWER(TRIM(postal_code)) LIKE ?", ['%' . $postalCode . '%'])
                    ->limit(200)
                    ->get()
            );
        }

        // 5) dirección principal
        if ($primaryAddress !== '') {
            $pushIds(
                'primary_address',
                Customer::query()
                    ->select('id')
                    ->whereRaw("LOWER(TRIM(primary_address)) LIKE ?", ['%' . $primaryAddress . '%'])
                    ->limit(200)
                    ->get()
            );
        }

        // 6) dirección secundaria
        if ($secondaryAddress !== '') {
            $pushIds(
                'secondary_address',
                Customer::query()
                    ->select('id')
                    ->whereRaw("LOWER(TRIM(secondary_address)) LIKE ?", ['%' . $secondaryAddress . '%'])
                    ->limit(200)
                    ->get()
            );
        }

        // ✅ si no hubo coincidencias => NO crear nota (solo avisar)
        if ($allIds->isEmpty()) {
            Notification::make()
                ->title('Sin coincidencias')
                ->body('No se encontraron clientes con esos datos de dirección.')
                ->warning()
                ->send();
            return;
        }

        // ✅ si hay solo 1 => flujo normal (puede redirigir a create según tu regla de meses)
        if ($allIds->count() === 1) {
            $customer = Customer::find($allIds->first());
            if ($customer) {
                $this->handleCustomerFound($customer, $digits, array_filter([
                    'primary_address' => $state['primary_address'] ?? null,
                    'secondary_address' => $state['secondary_address'] ?? null,
                    'nro_piso' => $state['nro_piso'] ?? null,
                    'postal_code' => $state['postal_code'] ?? null,
                    'ayuntamiento' => $state['ayuntamiento'] ?? null,
                    'provincia' => $state['provincia'] ?? null,
                ]));
                return;
            }
        }

        // ⚠️ Ajusta estos valores a tus enums/valores reales en DB:
        $ST = 'ST';
        $OF = 'OF';
        $VTA = 'VTA';

        // ✅ traer customers + conteos por estado_terminal + última nota (subselect)
        $customers = Customer::query()
            ->whereIn('id', $allIds->all())
            ->select([
                'customers.*',
                DB::raw("(SELECT MAX(n.created_at) FROM notes n WHERE n.customer_id = customers.id) as last_note_at"),
                DB::raw("(SELECT n2.status FROM notes n2 WHERE n2.customer_id = customers.id ORDER BY n2.created_at DESC LIMIT 1) as last_note_status"),
            ])
            ->withCount([
                'notes as st_count' => fn($q) => $q->where('estado_terminal', $ST),
                'notes as of_count' => fn($q) => $q->where('estado_terminal', $OF),
                'notes as vta_count' => fn($q) => $q->where('estado_terminal', $VTA),
            ])
            ->get()
            ->keyBy('id');

        $label = fn(string $g) => match ($g) {
            'nro_piso' => 'Coincide No. y Piso',
            'provincia' => 'Coincide Provincia',
            'ciudad' => 'Coincide Ciudad',
            'postal_code' => 'Coincide CP',
            'primary_address' => 'Coincide Dir. principal',
            'secondary_address' => 'Coincide Dir. secundaria',
            default => 'Coincidencia',
        };

        $rows = [];

        foreach ($allIds as $id) {
            $c = $customers->get($id);
            if (!$c)
                continue;

            $reasons = array_values(array_unique($reasonsById[$id] ?? []));
            $matchLabel = implode(' • ', array_map($label, $reasons)) ?: 'Coincidencia';

            $customerName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: ($c->name ?? 'Cliente');

            $summary = sprintf(
                'ST: %d, OF: %d, VTA: %d',
                (int) ($c->st_count ?? 0),
                (int) ($c->of_count ?? 0),
                (int) ($c->vta_count ?? 0),
            );

            $rows[] = [
                'match_reason' => $matchLabel,

                'customer_id' => $c->id,
                'customer_name' => $customerName,
                'customer_phone' => $c->phone ?? null,

                'primary_address' => $c->primary_address ?? null,
                'nro_piso' => $c->nro_piso ?? null,
                'secondary_address' => $c->secondary_address ?? null,
                'postal_code' => $c->postal_code ?? null,
                'ciudad' => $c->ciudad ?? null,
                'provincia' => $c->provincia ?? null,

                'note_date' => $c->last_note_at ? \Carbon\Carbon::parse($c->last_note_at)->format('d/m/Y H:i') : null,
                'note_status' => $c->last_note_status ?? null,

                // ✅ en la columna "Resumen" del modal
                'note_excerpt' => $summary,
            ];
        }

        $this->addressMatchesTitle = "Coincidencias encontradas: {$allIds->count()}";
        $this->addressMatches = $rows;

        $this->mountAction('addressMatches');
    }

    public function render()
    {
        return view('livewire.teleoperator.buscar-cliente');
    }
}
