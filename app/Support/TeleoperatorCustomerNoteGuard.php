<?php

namespace App\Support;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Models\Customer;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reglas compartidas para teleoperadora / Head of Room al crear notas.
 * Evalúa todos los clientes vinculados por teléfono (incluidos duplicados con otro phone principal).
 */
class TeleoperatorCustomerNoteGuard
{
    /** @var list<string> */
    public const PHONE_COLUMNS = [
        'phone',
        'secondary_phone',
        'third_phone',
        'phone1_commercial',
        'phone2_commercial',
    ];

    public static function normalizePhoneDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return strlen($digits) === 9 ? $digits : null;
    }

    public function resolveCustomersForPhone(string $digits): Collection
    {
        $digits = self::normalizePhoneDigits($digits);

        if ($digits === null) {
            return collect();
        }

        $customers = $this->findCustomersByPhone($digits);

        return $this->expandBySharedPhones($customers);
    }

    public function resolveCustomersForPhones(iterable $phones): Collection
    {
        $customers = collect();

        foreach ($phones as $phone) {
            $digits = self::normalizePhoneDigits(is_string($phone) ? $phone : null);

            if ($digits === null) {
                continue;
            }

            $customers = $customers->merge($this->resolveCustomersForPhone($digits));
        }

        return $customers->unique('id')->values();
    }

    public function findCustomersByPhone(string $digits): Collection
    {
        $digits = self::normalizePhoneDigits($digits);

        if ($digits === null) {
            return collect();
        }

        return Customer::query()
            ->where(fn ($query) => $this->applyPhoneMatchToQuery($query, $digits))
            ->get();
    }

    /**
     * Incluye clientes que comparten cualquier teléfono con el grupo inicial (duplicados cruzados).
     */
    public function expandBySharedPhones(Collection $customers): Collection
    {
        if ($customers->isEmpty()) {
            return $customers;
        }

        $phones = $customers
            ->flatMap(fn (Customer $customer) => $this->phonesFromCustomer($customer))
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            return $customers->unique('id')->values();
        }

        $expanded = Customer::query()
            ->where(function ($query) use ($phones) {
                foreach ($phones as $digits) {
                    $query->orWhere(fn ($inner) => $this->applyPhoneMatchToQuery($inner, $digits));
                }
            })
            ->get();

        return $expanded->unique('id')->values();
    }

    public function evaluateForPhone(string $digits): TeleoperatorNoteCreationEvaluation
    {
        return $this->evaluate($this->resolveCustomersForPhone($digits));
    }

    public function evaluate(Collection $customers): TeleoperatorNoteCreationEvaluation
    {
        if ($customers->isEmpty()) {
            return new TeleoperatorNoteCreationEvaluation(
                allowed: true,
                outcome: 'allowed_new',
                message: 'Sin clientes previos para este teléfono.',
            );
        }

        $inhabilitado = $customers->first(fn (Customer $customer) => $customer->inhabilitado);

        if ($inhabilitado) {
            return new TeleoperatorNoteCreationEvaluation(
                allowed: false,
                outcome: 'blocked',
                message: 'Este cliente ya no puede ser contactado por la empresa. Está descartado.',
                customerId: $inhabilitado->id,
            );
        }

        foreach ($customers as $customer) {
            $printedNotes = $customer->notes()->where('printed', true)->get();

            foreach ($printedNotes as $printedNote) {
                if (
                    $printedNote->fuente === FuenteNotas::PTA_FRIA ||
                    $printedNote->fuente === FuenteNotas::VIP_EXT
                ) {
                    $fechaRef = $printedNote->assignment_date ?? $printedNote->created_at;
                    $permitidoDesde = Carbon::parse($fechaRef)->addMonths(4)->startOfMonth();

                    if (now()->lt($permitidoDesde)) {
                        return new TeleoperatorNoteCreationEvaluation(
                            allowed: false,
                            outcome: 'blocked',
                            message: "BLOQUEADO: El cliente tiene una nota impresa. Podrá crear nueva nota a partir del {$permitidoDesde->format('d/m/Y')}.",
                            customerId: $customer->id,
                            noteId: $printedNote->id,
                        );
                    }
                } else {
                    return new TeleoperatorNoteCreationEvaluation(
                        allowed: false,
                        outcome: 'blocked',
                        message: "BLOQUEADO: El cliente (ID: {$customer->id}) tiene una nota impresa. No se puede crear nueva nota.",
                        customerId: $customer->id,
                        noteId: $printedNote->id,
                    );
                }
            }
        }

        foreach ($customers as $customer) {
            $hasSalaNote = $customer->notes()
                ->where('estado_terminal', EstadoTerminal::SALA->value)
                ->exists();

            if ($hasSalaNote) {
                return new TeleoperatorNoteCreationEvaluation(
                    allowed: false,
                    outcome: 'blocked',
                    message: "BLOQUEADO: El cliente (ID: {$customer->id}) tiene una nota enviada a oficina. No se puede crear nueva nota.",
                    customerId: $customer->id,
                );
            }
        }

        $cutoff = $this->recentActivityCutoff();
        $customerIds = $customers->pluck('id')->all();
        $allNotes = Note::query()
            ->whereIn('customer_id', $customerIds)
            ->get();

        foreach ($customers as $customer) {
            $hasVentaRecord = $customer->ventas()->exists();
            $hasVentaNote = $allNotes
                ->where('customer_id', $customer->id)
                ->contains(fn (Note $note) => $note->estado_terminal === EstadoTerminal::VENTA);

            if (! $hasVentaRecord && ! $hasVentaNote) {
                continue;
            }

            $lastNote = $this->mostRecentNoteForCustomer($allNotes, $customer->id);

            if ($lastNote) {
                $fechaReferencia = $this->referenceDateForNote($lastNote);

                if ($fechaReferencia && $fechaReferencia->gte($cutoff)) {
                    $motivo = $hasVentaRecord ? 'ventas registradas' : 'una nota marcada como VENTA';

                    return new TeleoperatorNoteCreationEvaluation(
                        allowed: false,
                        outcome: 'blocked',
                        message: "BLOQUEADO: El cliente (ID: {$customer->id}) tiene {$motivo} y actividad reciente ({$fechaReferencia->format('d/m/Y')}).",
                        customerId: $customer->id,
                        noteId: $lastNote->id,
                    );
                }
            } elseif ($hasVentaRecord) {
                $fechaVenta = $customer->ventas()->latest('fecha_venta')->value('fecha_venta');

                if ($fechaVenta && Carbon::parse($fechaVenta)->gte($cutoff)) {
                    $fechaRefStr = Carbon::parse($fechaVenta)->format('d/m/Y');

                    return new TeleoperatorNoteCreationEvaluation(
                        allowed: false,
                        outcome: 'blocked',
                        message: "BLOQUEADO: El cliente (ID: {$customer->id}) tiene ventas registradas con fecha reciente ({$fechaRefStr}).",
                        customerId: $customer->id,
                    );
                }
            }
        }

        $recentNote = $this->mostRecentNoteAcrossCustomers($allNotes);

        if ($recentNote) {
            $fechaReferencia = $this->referenceDateForNote($recentNote);

            if ($fechaReferencia && $fechaReferencia->gte($cutoff)) {
                $blockedCustomerId = $recentNote->customer_id;
                $estado = $recentNote->estado_terminal instanceof EstadoTerminal
                    ? $recentNote->estado_terminal->value
                    : (string) $recentNote->estado_terminal;

                $detalleEstado = $estado === EstadoTerminal::CONFIRMADO->value
                    ? ' una nota confirmada'
                    : ' actividad reciente';

                return new TeleoperatorNoteCreationEvaluation(
                    allowed: false,
                    outcome: 'blocked',
                    message: "BLOQUEADO: Existe un cliente duplicado con{$detalleEstado} ({$fechaReferencia->format('d/m/Y')}). Cliente ID: {$blockedCustomerId}. Deben pasar 5 meses.",
                    customerId: $blockedCustomerId,
                    noteId: $recentNote->id,
                );
            }
        }

        if ($allNotes->isEmpty()) {
            return new TeleoperatorNoteCreationEvaluation(
                allowed: true,
                outcome: 'allowed_new',
                message: 'Cliente existente sin notas previas. Se puede crear la primera nota.',
                customerId: $customers->first()?->id,
            );
        }

        $ultimaNota = $this->mostRecentNoteAcrossCustomers($allNotes);
        $fechaReferencia = $ultimaNota ? $this->referenceDateForNote($ultimaNota)?->format('d/m/Y') : 'Sin fecha';

        return new TeleoperatorNoteCreationEvaluation(
            allowed: true,
            outcome: 'allowed_old',
            message: "Todos los clientes encontrados tienen notas antiguas. Última referencia: {$fechaReferencia}.",
            customerId: $customers->first()?->id,
        );
    }

    public function assertCanCreate(Collection $customers, string $field = 'phone'): void
    {
        $evaluation = $this->evaluate($customers);

        if ($evaluation->allowed) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $evaluation->message,
        ]);
    }

    public function recentActivityCutoff(): Carbon
    {
        return now()->startOfMonth()->subMonthsNoOverflow(4);
    }

    public function referenceDateForNote(Note $note): ?Carbon
    {
        $date = $note->assignment_date
            ?? $note->visit_date
            ?? $note->fecha_declaracion
            ?? $note->created_at;

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * @return list<string>
     */
    public function phonesFromCustomer(Customer $customer): array
    {
        $phones = [];

        foreach (self::PHONE_COLUMNS as $column) {
            $digits = self::normalizePhoneDigits($customer->{$column});

            if ($digits !== null) {
                $phones[] = $digits;
            }
        }

        return $phones;
    }

    protected function applyPhoneMatchToQuery($query, string $digits): void
    {
        $query->where(function ($inner) use ($digits) {
            foreach (self::PHONE_COLUMNS as $column) {
                $inner->orWhere($column, $digits)
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(COALESCE(`{$column}`, ''), ' ', ''), '-', ''), '.', '') = ?",
                        [$digits]
                    );
            }
        });
    }

    /**
     * @param  Collection<int, Note>  $notes
     */
    protected function mostRecentNoteForCustomer(Collection $notes, int $customerId): ?Note
    {
        return $this->mostRecentNoteAcrossCustomers(
            $notes->where('customer_id', $customerId)->values()
        );
    }

    /**
     * @param  Collection<int, Note>  $notes
     */
    protected function mostRecentNoteAcrossCustomers(Collection $notes): ?Note
    {
        return $notes
            ->sortByDesc(fn (Note $note) => $this->referenceDateForNote($note)?->timestamp ?? 0)
            ->first();
    }
}

final class TeleoperatorNoteCreationEvaluation
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $outcome,
        public readonly string $message,
        public readonly ?int $customerId = null,
        public readonly ?int $noteId = null,
    ) {}
}
