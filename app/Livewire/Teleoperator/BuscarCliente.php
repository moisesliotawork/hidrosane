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

    /**
     * Reglas:
     *
     * 1. Buscar TODOS los customers con ese teléfono.
     * 2. Tomar la última nota de cada customer por visit_date.
     * 3. Si NINGUNO tiene notas => permitir crear.
     * 4. Si AL MENOS UNA última nota es de menos de 5 meses => bloquear.
     * 5. Si TODAS las últimas notas son de más de 5 meses:
     *    5.1 Si alguna está en SALA y printed = true => bloquear e indicar nro_nota.
     *    5.2 Si no => permitir crear.
     *
     * TODO se calcula por visit_date.
     */
    protected function handleCustomersFound(Collection $customers, ?string $digits = null): void
    {
        $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

        $customersWithLastNote = $customers->map(function (Customer $customer) {
            /** @var Note|null $lastNote */
            $lastNote = $customer->notes()
                ->whereNotNull('visit_date')
                ->latest('visit_date')
                ->first();

            return [
                'customer' => $customer,
                'last_note' => $lastNote,
            ];
        });

        $notesFound = $customersWithLastNote
            ->pluck('last_note')
            ->filter();

        // Ningún duplicado tiene notas con visit_date
        if ($notesFound->isEmpty()) {
            $firstCustomer = $customers->first();

            $this->notifySePuedeLlamar(
                'Cliente existente sin notas previas. Se puede crear la primera nota.'
            );

            $this->redirectToCreate($firstCustomer?->id, $digits);
            return;
        }

        // Si al menos una última nota tiene menos de 5 meses => bloquear
        $recentEntry = $customersWithLastNote->first(function (array $item) use ($cutoff) {
            $lastNote = $item['last_note'];

            return $lastNote
                && $lastNote->visit_date
                && $lastNote->visit_date->gte($cutoff);
        });

        if ($recentEntry) {
            /** @var \App\Models\Customer $blockedCustomer */
            $blockedCustomer = $recentEntry['customer'];

            /** @var \App\Models\Note $blockedNote */
            $blockedNote = $recentEntry['last_note'];

            $fechaUltimaVisita = optional($blockedNote->visit_date)->format('d/m/Y') ?? 'Sin fecha';

            $this->notifyNoSePuedeLlamar(
                "BLOQUEADO: Existe un cliente duplicado con nota reciente ({$fechaUltimaVisita}). " .
                "Cliente ID: {$blockedCustomer->id}. Deben pasar 5 meses."
            );

            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // Todas las últimas notas son antiguas => validar si alguna está en SALA y printed = true
        $printedSalaNote = $notesFound->first(function (Note $note) {
            return $note->estado_terminal === EstadoTerminal::SALA
                && (bool) $note->printed === true;
        });

        if ($printedSalaNote) {
            $fechaVisita = optional($printedSalaNote->visit_date)->format('d/m/Y') ?? 'Sin fecha';
            $nroNota = $printedSalaNote->nro_nota ?? 'S/N';

            $this->notifyNoSePuedeLlamar(
                "BLOQUEADO: La nota {$nroNota} corresponde a OFICINA y ya fue impresa. " .
                "Fecha de visita: {$fechaVisita}."
            );

            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // Todas las últimas notas son antiguas y ninguna está en SALA + printed => permitir
        $ultimaNotaMasRecienteEntreAntiguas = $notesFound
            ->sortByDesc(fn (Note $note) => $note->visit_date?->timestamp ?? 0)
            ->first();

        $fechaReferencia = optional($ultimaNotaMasRecienteEntreAntiguas?->visit_date)->format('d/m/Y') ?? 'Sin fecha';

        $firstCustomer = $customers->first();

        $this->notifyClienteExistePeroAntiguo(
            "Todos los clientes encontrados tienen notas antiguas. Última referencia: {$fechaReferencia}."
        );

        $this->redirectToCreate($firstCustomer?->id, $digits);
    }

    public function buscarTelefono(): void
    {
        $state = $this->form->getState();
        $digits = preg_replace('/\D+/', '', (string) ($state['phone_query'] ?? ''));

        if (strlen($digits) !== 9) {
            $this->phoneNotFound = false;
            return;
        }

        $customers = Customer::query()
            ->where(function ($query) use ($digits) {
                $query->where('phone', $digits)
                    ->orWhere('secondary_phone', $digits)
                    ->orWhere('third_phone', $digits);
            })
            ->get();

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