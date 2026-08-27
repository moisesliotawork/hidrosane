<?php

namespace App\Livewire\HeadOfRoom;

use App\Filament\HeadOfRoom\Pages\NotasDireccionPage;
use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Filament\Support\CustomerPhoneForm;
use App\Models\Customer;
use App\Support\TeleoperatorCustomerNoteGuard;
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

    public ?array $data = [];

    public bool $phoneNotFound = false;

    /** @var list<array{id:int,label:string,phones:string}> */
    public array $customerChoices = [];

    public ?string $searchedDigits = null;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,
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
                            ->rule(CustomerPhoneForm::nineDigitValidationRule()),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('buscarTelefono')
                                ->label('Buscar')
                                ->color('warning')
                                ->action(fn () => $this->buscarTelefono()),
                        ]),

                        Forms\Components\Placeholder::make('no_encontrado')
                            ->content('NO SE ENCONTRO TELÉFONO')
                            ->visible(fn () => $this->phoneNotFound),
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

    protected function handleCustomersFound(Collection $customers, ?string $digits = null): void
    {
        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $customers = $guard->expandBySharedPhones($customers)->unique('id')->values();

        // Varios clientes con el mismo teléfono: HOR debe elegir a cuál crear la nota.
        if ($customers->count() > 1) {
            $this->searchedDigits = $digits;
            $this->customerChoices = $customers->map(function (Customer $customer) {
                $name = trim(($customer->first_names ?? '').' '.($customer->last_names ?? ''));
                $phones = collect([
                    $customer->phone,
                    $customer->secondary_phone,
                    $customer->third_phone,
                ])->filter()->unique()->implode(' / ');

                return [
                    'id' => $customer->id,
                    'label' => trim($name) !== '' ? $name : ('Cliente #'.$customer->id),
                    'phones' => $phones,
                ];
            })->all();

            Notification::make()
                ->title('Varios clientes con este teléfono')
                ->body('Elige el cliente correcto para crear la nota. Un teléfono puede pertenecer a más de un cliente.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $evaluation = $guard->evaluate($customers);

        if (! $evaluation->allowed) {
            $this->notifyNoSePuedeLlamar($evaluation->message);
            redirect()->to(NoteResource::getUrl('index'));

            return;
        }

        if ($evaluation->outcome === 'allowed_new') {
            $this->notifySePuedeLlamar($evaluation->message);
        } else {
            $this->notifyClienteExistePeroAntiguo($evaluation->message);
        }

        $this->redirectToCreate($evaluation->customerId ?? $customers->first()?->id, $digits);
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
        redirect()->to(NoteResource::getUrl('create', [
            'phone' => $this->searchedDigits,
            'emergency' => 1,
        ]));
    }

    public function buscarTelefono(): void
    {
        $this->customerChoices = [];
        $this->searchedDigits = null;

        $state = $this->form->getState();
        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($state['phone_query'] ?? '');

        if ($digits === null) {
            $this->phoneNotFound = false;

            return;
        }

        $guard = app(TeleoperatorCustomerNoteGuard::class);
        $customers = $guard->resolveCustomersForPhone($digits);

        if ($customers->isNotEmpty()) {
            $this->phoneNotFound = false;

            $this->handleCustomersFound($customers, $digits);

            return;
        }

        $this->phoneNotFound = true;

        redirect()->to(NotasDireccionPage::getUrl([
            'phone' => $digits,
        ]));
    }

    public function render()
    {
        return view('livewire.head-of-room.buscar-cliente');
    }
}
