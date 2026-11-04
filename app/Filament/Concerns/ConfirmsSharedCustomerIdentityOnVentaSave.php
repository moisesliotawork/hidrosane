<?php

namespace App\Filament\Concerns;

use App\Services\VentaCustomerIdentityService;
use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Support\HtmlString;

/**
 * Antes de guardar un contrato: si el cliente está en más contratos y cambian
 * nombre/DNI (legacy), pedir confirmación. En Admin/Gerente nombre/DNI están fijos
 * y el cambio va por «Cambio/nombre»; el modo inplace (aplicar a todos) ya no se ofrece.
 */
trait ConfirmsSharedCustomerIdentityOnVentaSave
{
    public ?string $customerIdentitySaveMode = null;

    protected function customerIdentityConfirmationAction(): Action
    {
        return Action::make('confirmCustomerIdentityChange')
            ->modalHeading('Cambio de nombre / DNI con cliente compartido')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Solo este contrato')
            ->modalCancelActionLabel('Cancelar (recomendado si no estás segura)')
            ->form(fn (): array => $this->customerIdentityConfirmationFormSchema())
            ->action(function (): void {
                // Solo fork: nunca inplace (renombrar la ficha compartida).
                $this->customerIdentitySaveMode = VentaCustomerIdentityService::IDENTITY_MODE_FORK;
                $this->save();
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function customerIdentityConfirmationFormSchema(): array
    {
        $summary = $this->buildCustomerIdentityChangeSummary();

        return [
            Forms\Components\Placeholder::make('resumen_cambio')
                ->label('Resumen')
                ->content(new HtmlString($summary['html'])),

            Forms\Components\Placeholder::make('modo_fork')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm" style="line-height:1.45;">'
                    .'Si continúas, este contrato se desvinculará de la ficha compartida '
                    .'(<strong>solo este contrato</strong>). '
                    .'Para reasignar de forma guiada cierra y usa el botón amarillo <strong>Cambio/nombre</strong>.'
                    .'</p>'
                )),

            Forms\Components\Placeholder::make('aviso_soporte')
                ->label('')
                ->content(new HtmlString(
                    '<div style="margin-top:8px;padding:14px 16px;border:2px solid #f59e0b;border-radius:10px;background:#fffbeb;">'
                    .'<div style="font-weight:800;color:#92400e;font-size:15px;letter-spacing:.02em;">'
                    .'¿NO ESTÁS SEGURA?</div>'
                    .'<div style="margin-top:6px;color:#78350f;font-size:14px;line-height:1.45;">'
                    .'Dale a <strong>Cancelar</strong> y consulta a <strong>SOPORTE (Rafa)</strong> '
                    .'antes de tocar nombre o DNI. Así evitamos errores como el de familiares '
                    .'que compartían ficha y desaparecían de la búsqueda.</div>'
                    .'</div>'
                )),
        ];
    }

    /**
     * @return array{html: string, related_count: int}
     */
    protected function buildCustomerIdentityChangeSummary(): array
    {
        $customer = $this->record?->customer;
        $new = is_array($this->data['customer'] ?? null) ? $this->data['customer'] : [];

        $oldName = trim(($customer?->first_names ?? '') . ' ' . ($customer?->last_names ?? ''));
        $newName = trim(($new['first_names'] ?? '') . ' ' . ($new['last_names'] ?? ''));
        $oldDni = filled($customer?->dni) ? (string) $customer->dni : '—';
        $newDni = filled($new['dni'] ?? null) ? (string) $new['dni'] : '—';

        $related = $customer
            ? VentaCustomerIdentityService::relatedContractLabels((int) $customer->id, (int) $this->record->id)
            : [];
        $total = $customer
            ? VentaCustomerIdentityService::relatedVentasCount((int) $customer->id)
            : 0;

        $otros = $related === []
            ? '<em>ninguno listado</em>'
            : e(implode(', ', $related));

        $html = '<div style="font-size:14px;line-height:1.5;color:#111827;">'
            .'<p><strong>Nombre:</strong> ' . e($oldName ?: '—') . ' → <strong>' . e($newName ?: '—') . '</strong></p>'
            .'<p><strong>DNI:</strong> ' . e($oldDni) . ' → <strong>' . e($newDni) . '</strong></p>'
            .'<p>Este cliente tiene <strong>' . (int) $total . ' contrato(s)</strong>.'
            .($total > 1
                ? ' Otros nºs (además de este): ' . $otros . '.'
                : '')
            .'</p>'
            .'</div>';

        return ['html' => $html, 'related_count' => $total];
    }

    protected function shouldConfirmSharedCustomerIdentity(): bool
    {
        if ($this->customerIdentitySaveMode !== null) {
            return false;
        }

        $customerData = $this->data['customer'] ?? null;

        if (! is_array($customerData) || ! $this->record?->customer_id) {
            return false;
        }

        $this->record->loadMissing(['customer', 'asociadas', 'asociadasConmigo']);
        $customer = $this->record->customer;

        if (! $customer) {
            return false;
        }

        if (! VentaCustomerIdentityService::identityChanged($customer, $customerData)) {
            return false;
        }

        return VentaCustomerIdentityService::customerIsShared($this->record);
    }

    protected function interceptSaveForSharedCustomerIdentity(
        bool $shouldRedirect = true,
        bool $shouldSendSavedNotification = true,
    ): bool {
        if (! $this->shouldConfirmSharedCustomerIdentity()) {
            return false;
        }

        $this->mountAction('confirmCustomerIdentityChange');

        return true;
    }

    protected function resetCustomerIdentitySaveMode(): void
    {
        $this->customerIdentitySaveMode = null;
    }
}
