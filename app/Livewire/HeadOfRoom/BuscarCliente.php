<?php

namespace App\Livewire\HeadOfRoom;

use Livewire\Component;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Filament\HeadOfRoom\Pages\NotasDireccionPage;
use App\Support\TeleoperatorCustomerNoteGuard;

use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class BuscarCliente extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public bool $phoneNotFound = false;

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
        $customers = $guard->expandBySharedPhones($customers);
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

    public function buscarTelefono(): void
    {
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
        return view('livewire.teleoperator.buscar-cliente');
    }
}
