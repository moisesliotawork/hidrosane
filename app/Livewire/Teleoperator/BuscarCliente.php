<?php

namespace App\Livewire\Teleoperator;

use Livewire\Component;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;

use App\Models\Customer;
use App\Filament\Teleoperator\Resources\NoteResource;

use Filament\Notifications\Notification;

use App\Filament\Teleoperator\Pages\NotasDireccionPage;

class BuscarCliente extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public bool $phoneNotFound = false;

    // ⛔ comentado: no se usa sin búsqueda por dirección
    // public array $addressMatches = [];
    // public ?string $addressMatchesTitle = null;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,

            // ⛔ comentado: campos de dirección (no se usan por ahora)
            // 'primary_address' => null,
            // 'secondary_address' => null,
            // 'nro_piso' => null,
            // 'postal_code' => null,
            // 'ayuntamiento' => null,
            // 'provincia' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

                        /**
                         * ⛔ Comentado: UI de dirección + botón buscar por dirección
                         */
                        /*
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
                                        'A Coruña'   => 'A Coruña',
                                        'Orense'     => 'Orense',
                                        'Lugo'       => 'Lugo',
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
                        */
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
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
            ->orWhere('third_phone', $digits)
            ->first();

        // ✅ Si existe: aplica tu lógica (cliente antiguo / duplicada / etc.)
        if ($customer) {
            $this->phoneNotFound = false;
            $this->handleCustomerFound($customer, $digits);
            return;
        }

        // ✅ Si NO existe: ir directo a CREAR con el teléfono precargado
        $this->phoneNotFound = true;

        redirect()->to(NotasDireccionPage::getUrl([
            // opcional: pasar el teléfono para mostrarlo arriba o usarlo luego
            'phone' => $digits,
        ]));
    }

    /**
     * ⛔ Comentado: Action y método de dirección (no se usan por ahora)
     */
    /*
    public function addressMatchesAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('addressMatches')
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
        // deshabilitado por ahora
    }
    */

    public function render()
    {
        return view('livewire.teleoperator.buscar-cliente');
    }
}
