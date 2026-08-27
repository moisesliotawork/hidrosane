<?php

namespace App\Support\HeadOfRoom;

use App\Enums\EstadoTerminal;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Restricciones al asignar comercial desde Jefe de Sala.
 * Permite forzar la asignación vía modal "ASIGNAR DE TODOS MODOS".
 */
class NoteAssignRestriction
{
    public const SESSION_PENDING_SINGLE = 'hor_pending_force_assign_single';

    public const SESSION_PENDING_BULK = 'hor_pending_force_assign_bulk';

    /**
     * @return array{
     *     code: string,
     *     title: string,
     *     message: string,
     *     customer_name: string,
     *     fecha_venta: string,
     *     comercial_emp: string,
     *     nro_nota: string|int|null
     * }|null
     */
    public static function forNote(Note $note): ?array
    {
        $customer = $note->customer;

        if (! $customer) {
            return null;
        }

        $phones = collect([
            $customer->phone,
            $customer->secondary_phone,
            $customer->third_phone,
            $customer->phone1_commercial,
            $customer->phone2_commercial,
        ])->filter()->values();

        $nameWords = collect(
            preg_split('/\s+/u', mb_strtolower(trim(
                ($customer->first_names ?? '').' '.($customer->last_names ?? '')
            )))
        )->filter(fn ($w) => mb_strlen($w) > 2)->values();

        if ($phones->isEmpty() || $nameWords->isEmpty()) {
            return null;
        }

        $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

        $matchingCustomer = Customer::query()
            ->where(function ($q) use ($nameWords) {
                foreach ($nameWords as $word) {
                    $q->orWhere(DB::raw('LOWER(first_names)'), 'like', "%{$word}%")
                        ->orWhere(DB::raw('LOWER(last_names)'), 'like', "%{$word}%");
                }
            })
            ->where(function ($q) use ($phones) {
                foreach ($phones as $phone) {
                    $q->orWhere('phone', $phone)
                        ->orWhere('secondary_phone', $phone)
                        ->orWhere('third_phone', $phone)
                        ->orWhere('phone1_commercial', $phone)
                        ->orWhere('phone2_commercial', $phone);
                }
            })
            ->whereHas('ventas', fn ($q) => $q->where('fecha_venta', '>=', $cutoff))
            ->first();

        if (! $matchingCustomer) {
            return null;
        }

        $recentVenta = Venta::query()
            ->where('customer_id', $matchingCustomer->id)
            ->where('fecha_venta', '>=', $cutoff)
            ->with('comercial:id,empleado_id')
            ->latest('fecha_venta')
            ->first();

        $clientName = mb_strtoupper(trim(
            ($matchingCustomer->first_names ?? '').' '.($matchingCustomer->last_names ?? '')
        ));
        $fechaVenta = $recentVenta
            ? Carbon::parse($recentVenta->fecha_venta)->format('d/m/Y H:i')
            : '—';
        $comercialEmp = $recentVenta?->comercial?->empleado_id ?? '—';

        return [
            'code' => 'venta_reciente',
            'title' => 'Asignación restringida',
            'message' => "NO PUEDES REASIGNAR AL CLIENTE: {$clientName}, tiene una venta reciente con fecha {$fechaVenta}, declarada por: {$comercialEmp}",
            'customer_name' => $clientName,
            'fecha_venta' => $fechaVenta,
            'comercial_emp' => (string) $comercialEmp,
            'nro_nota' => $note->nro_nota,
        ];
    }

    /**
     * @param  iterable<int, Note>  $notes
     * @return Collection<int, array{
     *     note_id: int,
     *     nro_nota: mixed,
     *     customer_name: string,
     *     fecha_venta: string,
     *     comercial_emp: string,
     *     code: string,
     *     message: string
     * }>
     */
    public static function collectBlocked(iterable $notes): Collection
    {
        $blocked = collect();

        foreach ($notes as $note) {
            $restriction = self::forNote($note);

            if ($restriction === null) {
                continue;
            }

            $blocked->push([
                'note_id' => $note->id,
                'nro_nota' => $note->nro_nota,
                'customer_name' => $restriction['customer_name'],
                'fecha_venta' => $restriction['fecha_venta'],
                'comercial_emp' => $restriction['comercial_emp'],
                'code' => $restriction['code'],
                'message' => $restriction['message'],
            ]);
        }

        return $blocked;
    }

    public static function singleModalContent(?array $restriction): HtmlString
    {
        if ($restriction === null) {
            return new HtmlString('<p>No hay datos de la restricción.</p>');
        }

        $nro = e((string) ($restriction['nro_nota'] ?? '—'));
        $name = e($restriction['customer_name'] ?? '—');
        $fecha = e($restriction['fecha_venta'] ?? '—');
        $emp = e($restriction['comercial_emp'] ?? '—');
        $msg = e($restriction['message'] ?? '');

        $html = <<<HTML
<style>
@keyframes hor-restriccion-parpadeo {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.15; }
}
.hor-restriccion-venta-reciente {
  color: #dc2626 !important;
  font-weight: 800 !important;
  animation: hor-restriccion-parpadeo 1s step-start infinite;
}
</style>
<div style="font-size:14px;line-height:1.5;">
  <p style="margin:0 0 12px;font-weight:700;color:#b91c1c;">⛔ Restricción detectada</p>
  <p style="margin:0 0 12px;">{$msg}</p>
  <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
    <tr><td style="padding:4px 0;color:#6b7280;width:140px;">Nota</td><td style="padding:4px 0;font-weight:600;">#{$nro}</td></tr>
    <tr><td style="padding:4px 0;color:#6b7280;">Cliente</td><td style="padding:4px 0;font-weight:600;">{$name}</td></tr>
    <tr><td style="padding:4px 0;color:#6b7280;">Fecha venta</td><td style="padding:4px 0;font-weight:600;">{$fecha}</td></tr>
    <tr><td style="padding:4px 0;color:#6b7280;">Declarada por</td><td style="padding:4px 0;font-weight:600;">{$emp}</td></tr>
    <tr><td style="padding:4px 0;color:#6b7280;">Motivo</td><td style="padding:4px 0;"><span class="hor-restriccion-venta-reciente">Venta reciente (últimos 4 meses)</span></td></tr>
  </table>
</div>
HTML;

        return new HtmlString($html);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $blockedItems
     */
    public static function bulkModalContent(Collection $blockedItems): HtmlString
    {
        $rows = $blockedItems->map(function (array $b) {
            $nro = e((string) ($b['nro_nota'] ?? '—'));
            $name = e((string) ($b['customer_name'] ?? '—'));
            $fecha = e((string) ($b['fecha_venta'] ?? '—'));
            $emp = e((string) ($b['comercial_emp'] ?? '—'));

            return "<tr style='border-bottom:1px solid #e5e7eb;'>"
                ."<td style='padding:6px 8px;font-weight:700;'>{$nro}</td>"
                ."<td style='padding:6px 8px;font-weight:600;'>{$name}</td>"
                ."<td style='padding:6px 8px;'>{$fecha}</td>"
                ."<td style='padding:6px 8px;'>ID: {$emp}</td>"
                .'</tr>';
        })->join('');

        $count = $blockedItems->count();

        $html = <<<HTML
<style>
@keyframes hor-restriccion-parpadeo {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.15; }
}
.hor-restriccion-venta-reciente {
  color: #dc2626 !important;
  font-weight: 800 !important;
  animation: hor-restriccion-parpadeo 1s step-start infinite;
}
</style>
<div style="font-size:13px;line-height:1.45;">
  <p style="margin:0 0 10px;font-weight:700;"><span class="hor-restriccion-venta-reciente">⛔ {$count} nota(s) con restricción (venta reciente)</span></p>
  <p style="margin:0 0 10px;">Clientes con <span class="hor-restriccion-venta-reciente">venta en los últimos 4 meses</span>. No se asignaron automáticamente:</p>
  <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;">Nota</th>
        <th style="padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;">Cliente</th>
        <th style="padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;">Fecha venta</th>
        <th style="padding:6px 8px;text-align:left;font-size:11px;text-transform:uppercase;color:#6b7280;">Declarada por</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;

        return new HtmlString($html);
    }

    public static function applyAssignment(Note $note, array $data, bool $sendToReten = false): void
    {
        if (($data['comercial_id'] ?? null) === '__RETEN__') {
            $assignmentDate = Note::normalizeCommercialAssignmentDate($data['assignment_date'] ?? null);

            $note->update([
                'reten' => true,
                'assignment_date' => $assignmentDate,
            ]);

            return;
        }

        $comercialId = $data['comercial_id'] ?? null;

        if (! empty($comercialId)) {
            $isValid = User::query()
                ->where('id', $comercialId)
                ->whereNull('baja')
                ->whereHas(
                    'roles',
                    fn ($r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager'])
                )
                ->exists();

            if (! $isValid) {
                throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
            }
        }

        $assignmentDate = ! empty($comercialId)
            ? Note::normalizeCommercialAssignmentDate($data['assignment_date'] ?? null)
            : null;

        $updates = [
            'comercial_id' => $comercialId ?: null,
            'assignment_date' => $assignmentDate,
            // Al asignar a un comercial desde HOR, la nota debe salir de retén
            // para que aparezca en Commercial → NOTAS (misma regla de siempre).
            'reten' => $sendToReten ? true : false,
            'assigned_by_user_id' => ! empty($comercialId) ? auth()->id() : null,
        ];

        if (! empty($comercialId) && $assignmentDate) {
            $updates['visit_date'] = $assignmentDate;
        }

        if ($note->estado_terminal === EstadoTerminal::SALA) {
            $updates['estado_terminal'] = EstadoTerminal::SIN_ESTADO->value;
            $updates['sent_to_sala_at'] = null;
        }

        $note->update($updates);
    }

    /**
     * @param  array<int>  $noteIds
     */
    public static function applyBulk(array $noteIds, array $data, bool $sendToReten = false): array
    {
        $comercialId = $data['comercial_id'] ?? null;

        if (! empty($comercialId)) {
            $isValid = User::query()
                ->where('id', $comercialId)
                ->whereNull('baja')
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
                ->exists();

            if (! $isValid) {
                throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
            }
        }

        $assignmentDate = ! empty($comercialId)
            ? Note::normalizeCommercialAssignmentDate($data['assignment_date'] ?? null)
            : null;

        $bulkUpdates = [
            'comercial_id' => (! empty($comercialId) ? $comercialId : null),
            'assignment_date' => $assignmentDate,
            'reten' => $sendToReten,
            'assigned_by_user_id' => ! empty($comercialId) ? auth()->id() : null,
        ];

        if (! empty($comercialId) && $assignmentDate) {
            $bulkUpdates['visit_date'] = $assignmentDate;
        }

        Note::whereIn('id', $noteIds)->update($bulkUpdates);

        $toResetIds = Note::whereIn('id', $noteIds)
            ->where('estado_terminal', EstadoTerminal::SALA->value)
            ->pluck('id')
            ->all();

        if (! empty($toResetIds)) {
            Note::whereIn('id', $toResetIds)->update([
                'estado_terminal' => EstadoTerminal::SIN_ESTADO->value,
                'sent_to_sala_at' => null,
            ]);
        }

        return $toResetIds;
    }
}
