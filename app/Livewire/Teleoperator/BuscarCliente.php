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
use App\Models\Note;
use App\Enums\EstadoTerminal;

use App\Filament\Teleoperator\Resources\NoteResource;
use App\Filament\Teleoperator\Pages\NotasDireccionPage;

use Filament\Notifications\Notification;

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

    /**
     * Reglas:
     * Caso 2: cliente existe
     * 2.1 buscar última nota
     * 2.2 si última nota es "hace más de 5 meses" (por meses calendario) => permitir crear + notificar
     * 2.3 si última nota es "hace menos de 5 meses" => validar estado terminal:
     *      2.3.1 si terminal es OFICINA (SALA), AUSENTE o SIN_ESTADO (incluye null, '', EMPTY) => permitir + notificar
     *      2.3.2 si no => bloquear + notificar
     */
    
protected function handleCustomerFound(Customer $customer, ?string $digits = null): void
{
    /** @var Note|null $lastNote */
    $lastNote = $customer->notes()->latest('visit_date')->first();

    // 1. Si no tiene notas nunca, permitir crear la primera
    if (!$lastNote) {
        redirect()->to(NoteResource::getUrl('create', [
            'customer_id' => $customer->id,
            'phone' => $digits ?: null,
        ]));
        return;
    }

    // 2. Cálculo del corte de 5 meses (calendario)
    $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);
    $esReciente = $lastNote->created_at && $lastNote->created_at->gte($cutoff);

    $fechaUltimaCreacion = optional($lastNote->created_at)->format('d/m/Y') ?? 'Sin fecha';
    $terminalLabel = $lastNote->estado_terminal 
        ? (method_exists($lastNote->estado_terminal, 'label') ? $lastNote->estado_terminal->label() : (string) ($lastNote->estado_terminal->value ?? $lastNote->estado_terminal))
        : 'Sin estado';

    // 3. REGLA ESTRICTA: Bloqueo total si es reciente
    if ($esReciente) {
        $this->notifyNoSePuedeLlamar(
            "BLOQUEADO: Contacto demasiado reciente ({$fechaUltimaCreacion}). " .
            "Estado: {$terminalLabel}. Deben pasar 5 meses."
        );

        redirect()->to(NoteResource::getUrl('index'));
        return;
    }

    // 4. Si es antigua, permitir
    $this->notifyClienteExistePeroAntiguo("Cliente antiguo encontrado. Última nota: {$fechaUltimaCreacion}.");

    redirect()->to(NoteResource::getUrl('create', [
        'customer_id' => $customer->id,
        'phone' => $digits ?: null,
    ]));
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

        // Caso 2: existe cliente
        if ($customer) {
            $this->phoneNotFound = false;
            $this->handleCustomerFound($customer, $digits);
            return;
        }

        // Caso 1: no existe cliente => redirigir a NotasDireccionPage
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
