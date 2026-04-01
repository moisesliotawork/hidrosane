<?php

namespace App\Livewire\HeadOfRoom;

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

use App\Filament\HeadOfRoom\Resources\NoteResource;
use App\Filament\HeadOfRoom\Pages\NotasDireccionPage;

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

    /**
     * Reglas:
     * 1. Buscar TODOS los customers con ese teléfono.
     * 2. Si algún customer tiene cualquier nota impresa (printed=true) => bloquear SIEMPRE.
     * 3. Tomar la última nota priorizando assignment_date sobre visit_date.
     * 4. Si algún customer tiene ventas registradas O nota en estado VENTA:
     *    4.1 Si la última nota es reciente (>= día 1 del mes hace 4 meses) => bloquear.
     *    4.2 Si la última nota es antigua => se permite (cae al flujo normal).
     *    4.3 Si no tiene notas con fecha => bloquear por seguridad.
     * 5. Si NINGUNO tiene notas con fecha => permitir crear.
     * 6. Si AL MENOS UNA última nota es reciente => bloquear.
     * 7. Si TODAS las últimas notas son antiguas => permitir crear.
     *
     * Fecha de referencia: assignment_date (o visit_date si no tiene assignment_date).
     * Cutoff: primer día del mes de hace 4 meses (now()->startOfMonth()->subMonthsNoOverflow(4)).
     */
    protected function handleCustomersFound(Collection $customers, ?string $digits = null): void
    {
        // 0) Si algún customer tiene cualquier nota impresa → bloquear siempre
        foreach ($customers as $customer) {
            if ($customer->notes()->where('printed', true)->exists()) {
                $this->notifyNoSePuedeLlamar(
                    "BLOQUEADO: El cliente (ID: {$customer->id}) tiene una nota impresa. No se puede crear nueva nota."
                );
                redirect()->to(NoteResource::getUrl('index'));
                return;
            }
        }

        $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

        $customersWithLastNote = $customers->map(function (Customer $customer) {
            /** @var Note|null $lastNote */
            $lastNote = $customer->notes()
                ->where(function ($query) {
                    $query->whereNotNull('assignment_date')
                        ->orWhereNotNull('visit_date');
                })
                ->latest('assignment_date')
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

        // 0) Si algún cliente tiene ventas registradas o nota en estado VENTA → revisar fecha
        foreach ($customersWithLastNote as $item) {
            /** @var Customer $customer */
            $customer = $item['customer'];
            /** @var Note|null $lastNote */
            $lastNote = $item['last_note'];

            $hasVentaRecord = $customer->ventas()->exists();
            $hasVentaNote   = $customer->notes()->where('estado_terminal', EstadoTerminal::VENTA)->exists();

            if ($hasVentaRecord || $hasVentaNote) {
                if ($lastNote) {
                    $fechaReferencia = $lastNote->assignment_date ?? $lastNote->visit_date;
                    if ($fechaReferencia && $fechaReferencia->gte($cutoff)) {
                        $fechaRefStr = $fechaReferencia->format('d/m/Y');
                        $motivo = $hasVentaRecord ? "ventas registradas" : "una nota marcada como VENTA";
                        $this->notifyNoSePuedeLlamar(
                            "BLOQUEADO: El cliente (ID: {$customer->id}) tiene {$motivo} y actividad reciente ({$fechaRefStr})."
                        );
                        redirect()->to(NoteResource::getUrl('index'));
                        return;
                    }
                    // Nota antigua → se permite, continúa el flujo normal
                } elseif ($hasVentaRecord) {
                    // Sin nota con fecha → usar fecha_venta de la tabla ventas
                    $fechaVenta = $customer->ventas()->latest('fecha_venta')->value('fecha_venta');
                    if ($fechaVenta && \Carbon\Carbon::parse($fechaVenta)->gte($cutoff)) {
                        $fechaRefStr = \Carbon\Carbon::parse($fechaVenta)->format('d/m/Y');
                        $this->notifyNoSePuedeLlamar(
                            "BLOQUEADO: El cliente (ID: {$customer->id}) tiene ventas registradas con fecha reciente ({$fechaRefStr})."
                        );
                        redirect()->to(NoteResource::getUrl('index'));
                        return;
                    }
                    // Venta antigua y sin nota → se permite, continúa el flujo normal
                }
                // hasVentaNote sin nota con fecha → sin fecha determinable, se trata como antigua → continúa
            }
        }

        // 1) Ningún customer tiene notas con fecha válida → permitir
        if ($notesFound->isEmpty()) {
            $firstCustomer = $customers->first();

            $this->notifySePuedeLlamar(
                'Cliente existente sin notas previas. Se puede crear la primera nota.'
            );

            $this->redirectToCreate($firstCustomer?->id, $digits);
            return;
        }

        // 2) Si al menos una última nota tiene menos de 5 meses → bloquear
        $recentEntry = $customersWithLastNote->first(function (array $item) use ($cutoff) {
            /** @var Note|null $lastNote */
            $lastNote = $item['last_note'];
            if (!$lastNote) return false;

            $fechaReferencia = $lastNote->assignment_date ?? $lastNote->visit_date;

            return $fechaReferencia && $fechaReferencia->gte($cutoff);
        });

        if ($recentEntry) {
            /** @var \App\Models\Customer $blockedCustomer */
            $blockedCustomer = $recentEntry['customer'];

            /** @var \App\Models\Note $blockedNote */
            $blockedNote = $recentEntry['last_note'];

            $fechaRef = ($blockedNote->assignment_date ?? $blockedNote->visit_date)->format('d/m/Y');

            $this->notifyNoSePuedeLlamar(
                "BLOQUEADO: Existe un cliente duplicado con actividad reciente ({$fechaRef}). " .
                "Cliente ID: {$blockedCustomer->id}. Deben pasar 5 meses."
            );

            redirect()->to(NoteResource::getUrl('index'));
            return;
        }

        // 3) Todas las últimas notas son antiguas → permitir
        $ultimaNotaMasRecienteEntreAntiguas = $notesFound
            ->sortByDesc(fn(Note $note) => ($note->assignment_date ?? $note->visit_date)?->timestamp ?? 0)
            ->first();

        $fechaReferencia = optional($ultimaNotaMasRecienteEntreAntiguas->assignment_date ?? $ultimaNotaMasRecienteEntreAntiguas->visit_date)->format('d/m/Y') ?? 'Sin fecha';

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
                    ->orWhere('third_phone', $digits)
                    ->orWhere('phone1_commercial', $digits)
                    ->orWhere('phone2_commercial', $digits);
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
