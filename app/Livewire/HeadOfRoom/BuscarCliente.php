<?php

namespace App\Livewire\HeadOfRoom;

use Livewire\Component;

use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;

use App\Models\Customer;
use App\Models\Note;
use App\Filament\Teleoperator\Resources\NoteResource;

use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;

use App\Enums\EstadoTerminal;
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
            'secondary_address' => null,
            'nro_piso' => null,
            'postal_code' => null,
            'ayuntamiento' => null,
            'provincia' => null,
        ]);
    }

    // ✅ Importante: con HasActions NO uses form(Form $form)
    protected function getFormStatePath(): string
    {
        return 'data';
    }

    protected function getFormSchema(): array
    {
        return [
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

                            Forms\Components\TextInput::make('secondary_address')
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
                                ->placeholder('15551')
                                ->minLength(5)
                                ->maxLength(5)
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
        ];
    }

    protected function continueCreateUrl(): string
    {
        $state = $this->form->getState();

        return NoteResource::getUrl('create', array_filter([
            'phone' => preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? '')) ?: null,

            'primary_address' => $state['primary_address'] ?? null,
            'secondary_address' => $state['secondary_address'] ?? null,
            'nro_piso' => $state['nro_piso'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'provincia' => $state['provincia'] ?? null,
            'ciudad' => $state['ayuntamiento'] ?? null,
        ]));
    }

    /**
     * Regla Jefe de Sala:
     * - muestra resumen de notas
     * - permite continuar SOLO si NO tiene OF ni ST
     */
    protected function notifyCustomerEncontrado(Customer $customer): void
    {
        $q = Note::query()->where('customer_id', $customer->id);

        $total = (clone $q)->count();

        $nulas = (clone $q)->where('estado_terminal', EstadoTerminal::NUL->value)->count();
        $conf = (clone $q)->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)->count();
        $vta = (clone $q)->where('estado_terminal', EstadoTerminal::VENTA->value)->count();
        $of = (clone $q)->where('estado_terminal', EstadoTerminal::SALA->value)->count();
        $st = (clone $q)->where('estado_terminal', EstadoTerminal::SIN_ESTADO->value)->count();

        $body = "Notas asoci: {$total}, Nulas: {$nulas}, Conf: {$conf}, Vta: {$vta}, Of: {$of}, ST: {$st}";

        $canContinue = ($of === 0 && $st === 0);

        $notification = Notification::make()
            ->title('Cliente encontrado en sistema')
            ->body($body)
            ->persistent()
            ->warning();

        if ($canContinue) {
            $notification->actions([
                NotificationAction::make('continuar')
                    ->label('Continuar y crear nota')
                    ->button()
                    ->color('success')
                    ->url(NoteResource::getUrl('create', [
                        'customer_id' => $customer->id,
                    ])),
            ]);
        } else {
            $notification->actions([
                NotificationAction::make('ir_notas')
                    ->label('Ver notas')
                    ->button()
                    ->color('danger')
                    ->url(NoteResource::getUrl('index')),
            ]);
        }

        $notification->send();
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
            $this->notifyCustomerEncontrado($customer);
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
            ->modalContent(view('livewire.head-of-room.modals.address-matches', [
                'rows' => $this->addressMatches,
                'continue_url' => $this->continueCreateUrl(), // ✅ botón arriba del modal
            ]));
    }

    public function buscarDireccion(): void
    {
        $state = $this->form->getState();

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

        // ✅ basta con 1 campo
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
            $pushIds('provincia', Customer::query()
                ->select('id')
                ->whereRaw("LOWER(TRIM(provincia)) LIKE ?", ['%' . $provincia . '%'])
                ->limit(200)->get());
        }

        // 3) ciudad
        if ($ciudad !== '') {
            $pushIds('ciudad', Customer::query()
                ->select('id')
                ->whereRaw("LOWER(TRIM(ciudad)) LIKE ?", ['%' . $ciudad . '%'])
                ->limit(200)->get());
        }

        // 4) CP
        if ($postalCode !== '') {
            $pushIds('postal_code', Customer::query()
                ->select('id')
                ->whereRaw("LOWER(TRIM(postal_code)) LIKE ?", ['%' . $postalCode . '%'])
                ->limit(200)->get());
        }

        // 5) dir principal
        if ($primaryAddress !== '') {
            $pushIds('primary_address', Customer::query()
                ->select('id')
                ->whereRaw("LOWER(TRIM(primary_address)) LIKE ?", ['%' . $primaryAddress . '%'])
                ->limit(200)->get());
        }

        // 6) dir secundaria
        if ($secondaryAddress !== '') {
            $pushIds('secondary_address', Customer::query()
                ->select('id')
                ->whereRaw("LOWER(TRIM(secondary_address)) LIKE ?", ['%' . $secondaryAddress . '%'])
                ->limit(200)->get());
        }

        // ✅ si NO hay coincidencias => crear nota con esos campos
        if ($allIds->isEmpty()) {
            redirect()->to($this->continueCreateUrl());
            return;
        }

        // ✅ si solo 1 => notificación con regla jefe de sala
        if ($allIds->count() === 1) {
            $customer = Customer::find($allIds->first());
            if ($customer) {
                $this->notifyCustomerEncontrado($customer);
                return;
            }
        }

        // ✅ conteos para resumen ST/OF/VTA + última nota
        $ST = EstadoTerminal::SIN_ESTADO->value;
        $OF = EstadoTerminal::SALA->value;
        $VTA = EstadoTerminal::VENTA->value;

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

            // ✅ Resumen como en notificación
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

                'note_date' => $c->last_note_at
                    ? \Carbon\Carbon::parse($c->last_note_at)->format('d/m/Y H:i')
                    : null,
                'note_status' => $c->last_note_status ?? null,

                // ✅ la columna "Resumen" del modal
                'note_excerpt' => $summary,
            ];
        }

        $this->addressMatchesTitle = "Coincidencias encontradas: {$allIds->count()}";
        $this->addressMatches = $rows;

        $this->mountAction('addressMatches');
    }

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}
