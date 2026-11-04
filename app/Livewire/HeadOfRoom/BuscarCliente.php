<?php

namespace App\Livewire\HeadOfRoom;

use App\Filament\HeadOfRoom\Pages\NotasDireccionPage;
use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Filament\Support\CustomerPhoneForm;
use App\Models\Customer;
use App\Support\CustomerAddressSearch;
use App\Support\TeleoperatorCustomerNoteGuard;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Component;

class BuscarCliente extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    private const SESSION_KEY = 'head_of_room.buscar_cliente';

    public ?array $data = [];

    public bool $phoneNotFound = false;

    public bool $searchNotFound = false;

    /** @var list<array{id:int,label:string,phones:string,address:string,match_phone:bool,match_address:bool}> */
    public array $customerChoices = [];

    public ?string $searchedDigits = null;

    public ?string $searchedAddress = null;

    public ?string $ventaRecienteFecha = null;

    public function mount(): void
    {
        $saved = session(self::SESSION_KEY);

        if (is_array($saved)) {
            $this->phoneNotFound = (bool) ($saved['phoneNotFound'] ?? false);
            $this->searchNotFound = (bool) ($saved['searchNotFound'] ?? false);
            $this->customerChoices = is_array($saved['customerChoices'] ?? null)
                ? $saved['customerChoices']
                : [];
            $this->searchedDigits = isset($saved['searchedDigits'])
                ? (string) $saved['searchedDigits']
                : null;
            $this->searchedAddress = isset($saved['searchedAddress'])
                ? (string) $saved['searchedAddress']
                : null;

            $this->form->fill([
                'phone_query' => $saved['phone_query'] ?? null,
                'address_query' => $saved['address_query'] ?? null,
            ]);

            return;
        }

        $this->form->fill([
            'phone_query' => null,
            'address_query' => null,
        ]);
    }

    protected function persistSearchState(): void
    {
        $state = $this->data ?? [];

        session([
            self::SESSION_KEY => [
                'phone_query' => $state['phone_query'] ?? null,
                'address_query' => $state['address_query'] ?? null,
                'phoneNotFound' => $this->phoneNotFound,
                'searchNotFound' => $this->searchNotFound,
                'customerChoices' => $this->customerChoices,
                'searchedDigits' => $this->searchedDigits,
                'searchedAddress' => $this->searchedAddress,
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Buscar cliente')
                    ->schema([
                        Forms\Components\TextInput::make('phone_query')
                            ->label('NÚMERO DE TELÉFONO')
                            ->tel()
                            ->mask(CustomerPhoneForm::MASK)
                            ->placeholder(CustomerPhoneForm::PLACEHOLDER)
                            ->helperText('Match exacto (9 dígitos). No hace falta que coincida también la dirección.')
                            ->rule(CustomerPhoneForm::nineDigitValidationRule(allowEmpty: true)),

                        Forms\Components\TextInput::make('address_query')
                            ->label('DIRECCIÓN')
                            ->placeholder('Calle, lugar, piso, ciudad…')
                            ->maxLength(255)
                            ->helperText('Match parcial. Independiente del teléfono: basta con uno de los dos.'),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('buscarCliente')
                                ->label('Buscar')
                                ->color('warning')
                                ->action(fn () => $this->buscarCliente()),
                        ]),

                        Forms\Components\Placeholder::make('no_encontrado_telefono')
                            ->content('NO SE ENCONTRÓ TELÉFONO')
                            ->visible(fn () => $this->phoneNotFound),

                        Forms\Components\Placeholder::make('no_encontrado_busqueda')
                            ->content('NO SE ENCONTRARON CLIENTES CON ESOS DATOS')
                            ->visible(fn () => $this->searchNotFound),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function notifyNoSePuedeLlamar(string $detalle): void
    {
        Notification::make()
            ->title('NO SE PUEDE LLAMAR')
            ->body($detalle)
            ->danger()
            ->persistent()
            ->send();
    }

    protected function notifySePuedeLlamar(string $detalle): void
    {
        Notification::make()
            ->title('SE PUEDE LLAMAR')
            ->body($detalle)
            ->warning()
            ->persistent()
            ->send();
    }

    protected function notifyClienteExistePeroAntiguo(string $detalle): void
    {
        Notification::make()
            ->title('CLIENTE EXISTE (ANTIGUO)')
            ->body($detalle)
            ->warning()
            ->persistent()
            ->send();
    }

    protected function redirectToCreate(?int $customerId, ?string $digits = null): void
    {
        redirect()->to(NoteResource::getUrl('create', [
            'customer_id' => $customerId,
            'phone' => $digits ?: null,
        ]));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<int, true>  $phoneMatchIds
     * @param  array<int, true>  $addressMatchIds
     */
    protected function presentCustomerChoices(
        Collection $customers,
        ?string $digits = null,
        array $phoneMatchIds = [],
        array $addressMatchIds = [],
    ): void {
        $this->searchedDigits = $digits;
        $this->customerChoices = $customers->map(function (Customer $customer) use ($phoneMatchIds, $addressMatchIds) {
            $name = trim(($customer->first_names ?? '').' '.($customer->last_names ?? ''));
            $phones = collect([
                $customer->phone,
                $customer->secondary_phone,
                $customer->third_phone,
            ])->filter()->unique()->implode(' / ');

            $address = collect([
                $customer->primary_address,
                $customer->nro_piso,
                $customer->ciudad,
            ])->filter(fn ($v) => filled($v))->implode(', ');

            return [
                'id' => $customer->id,
                'label' => trim($name) !== '' ? $name : ('Cliente #'.$customer->id),
                'phones' => $phones,
                'address' => $address,
                'match_phone' => isset($phoneMatchIds[$customer->id]),
                'match_address' => isset($addressMatchIds[$customer->id]),
            ];
        })->all();

        $this->persistSearchState();

        Notification::make()
            ->title($customers->count() === 1 ? 'Cliente encontrado' : 'Varios clientes encontrados')
            ->body('Teléfono exacto o dirección parcial (independientes). Elige el cliente para crear la nota.')
            ->warning()
            ->persistent()
            ->send();
    }

    public function chooseCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            Notification::make()
                ->title('Cliente no encontrado')
                ->danger()
                ->send();

            return;
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $evaluation = $guard->evaluate(collect([$customer]));

        if (! $evaluation->allowed) {
            $this->notifyNoSePuedeLlamar($evaluation->message);

            return;
        }

        if ($evaluation->outcome === 'allowed_new') {
            $this->notifySePuedeLlamar($evaluation->message);
        } else {
            $this->notifyClienteExistePeroAntiguo($evaluation->message);
        }

        $this->redirectToCreate($customer->id, $this->searchedDigits);
    }

    public function createNoteForNewCustomerWithSamePhone(): void
    {
        $fecha = $this->fechaVentaRecienteDelTelefono();

        if ($fecha !== null) {
            $this->ventaRecienteFecha = $fecha;
            $this->mountAction('confirmarVentaReciente', ['fecha' => $fecha]);

            return;
        }

        $this->redirectToEmergencyCreate();
    }

    public function confirmarVentaRecienteAction(): Action
    {
        return Action::make('confirmarVentaReciente')
            ->modalHeading(function (array $arguments): string {
                $fecha = $arguments['fecha'] ?? $this->ventaRecienteFecha ?? '—';

                return "VENTA RECIENTE DE ESTE CLIENTE en fecha {$fecha}";
            })
            ->modalSubmitActionLabel('Entendido, pero voy a crear otra nota nueva al cliente')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('xl')
            ->action(fn () => $this->redirectToEmergencyCreate());
    }

    protected function fechaVentaRecienteDelTelefono(): ?string
    {
        if (blank($this->searchedDigits)) {
            return null;
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $recent = $guard->recentVentaNote($guard->resolveCustomersForPhone($this->searchedDigits));

        $fecha = $recent['fecha'] ?? null;

        return filled($fecha) ? $fecha : null;
    }

    protected function redirectToEmergencyCreate(): void
    {
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $this->searchedDigits,
            'emergency' => 1,
        ]));
    }

    public function buscarCliente(): void
    {
        $this->customerChoices = [];
        $this->searchedDigits = null;
        $this->searchedAddress = null;
        $this->phoneNotFound = false;
        $this->searchNotFound = false;

        $state = $this->form->getState();
        $phoneRaw = trim((string) ($state['phone_query'] ?? ''));
        $address = trim((string) ($state['address_query'] ?? ''));

        if ($phoneRaw === '' && $address === '') {
            Notification::make()
                ->title('Indica al menos un dato')
                ->body('Debes introducir teléfono o dirección (o ambos).')
                ->warning()
                ->send();

            return;
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $digits = null;
        $byPhone = collect();

        if ($phoneRaw !== '') {
            $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($phoneRaw);

            if ($digits === null) {
                Notification::make()
                    ->title('Teléfono no válido')
                    ->body('El teléfono debe tener exactamente 9 cifras.')
                    ->danger()
                    ->send();

                return;
            }

            $byPhone = $guard->resolveCustomersForPhone($digits);
        }

        $byAddress = collect();

        if ($address !== '') {
            $this->searchedAddress = $address;
            $byAddress = Customer::query()
                ->where(function ($query) use ($address) {
                    CustomerAddressSearch::constrainCustomerQuery($query, $address);
                })
                ->limit(100)
                ->get();
        }

        // Unión (OR): match exacto de teléfono y/o match parcial de dirección.
        // No se exige que coincidan ambos criterios a la vez.
        $customers = $byPhone->merge($byAddress)->unique('id')->values();
        $phoneMatchIds = $byPhone->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        $addressMatchIds = $byAddress->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        if ($customers->isNotEmpty()) {
            $this->presentCustomerChoices($customers, $digits, $phoneMatchIds, $addressMatchIds);

            return;
        }

        // Teléfono solo y sin resultados: flujo previo hacia notas por dirección.
        if ($digits !== null && $address === '') {
            $this->phoneNotFound = true;
            $this->searchedDigits = $digits;
            $this->persistSearchState();

            redirect()->to(NotasDireccionPage::getUrl([
                'phone' => $digits,
            ]));

            return;
        }

        $this->searchNotFound = true;
        $this->searchedDigits = $digits;
        $this->persistSearchState();
    }

    /** @deprecated Usa buscarCliente(); se mantiene por compatibilidad de llamadas previas. */
    public function buscarTelefono(): void
    {
        $this->buscarCliente();
    }

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}
