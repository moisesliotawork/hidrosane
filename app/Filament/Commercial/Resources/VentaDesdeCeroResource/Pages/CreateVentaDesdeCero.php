<?php

namespace App\Filament\Commercial\Resources\VentaDesdeCeroResource\Pages;

use App\Filament\Commercial\Resources\VentaDesdeCeroResource;
use App\Support\ActionGps;
use App\Support\ContractsCommercialUser;
use App\Support\PuertaFriaCustomerSearch;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Forms\Components\Wizard\Step;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\{Venta, Note, Customer, User};
use App\Enums\{NoteStatus, EstadoTerminal};
use App\Filament\Commercial\Pages\NotasHoy;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Events\VentaCreada;
use App\Support\VentaFechaVenta;
use App\Support\VentaOrigenResolver;
use App\Support\Filament\GpsActionForm;
use App\Filament\Commercial\Concerns\HandlesGpsVentaWizard;
use Filament\Actions\Action;
use App\Enums\OrigenVenta;
use Filament\Notifications\Notification;

class CreateVentaDesdeCero extends CreateRecord
{
    use HasWizard;
    use HandlesGpsVentaWizard;

    protected static string $resource = VentaDesdeCeroResource::class;

    protected static string $view = 'filament.commercial.pages.create-venta-desde-cero';

    public string $lookupPhone = '';

    public string $lookupName = '';

    public ?string $lookupMessage = null;

    public ?string $lookupMessageStatus = null;

    public bool $lookupSearched = false;

    /** @var list<array{id: int, name: string, dni: ?string, phone: ?string}> */
    public array $lookupResults = [];

    public ?string $lookupSelectedChoice = null;

    public bool $puertaFriaLookupCompleted = false;

    public ?string $puertaFriaLookupToken = null;

    public function getTitle(): string
    {
        return 'Puerta Fria';
    }

    public function shouldShowPuertaFriaLookupModal(): bool
    {
        return $this->requiresPuertaFriaLookup() && ! $this->puertaFriaLookupCompleted;
    }

    public function isPuertaFriaLookupBlockingForm(): bool
    {
        return $this->requiresPuertaFriaLookup() && ! $this->puertaFriaLookupCompleted;
    }

    public function requiresPuertaFriaLookup(): bool
    {
        return ContractsCommercialUser::matches();
    }

    public function canShowPuertaFriaLookupContinue(): bool
    {
        if (! $this->lookupSearched) {
            return false;
        }

        return $this->lookupResults !== [] || $this->lookupMessageStatus === 'not_found';
    }

    private function sessionKey(): string
    {
        return 'pf_draft_commercial_' . auth()->id();
    }

    private function puertaFriaLookupTokenKey(): string
    {
        return 'pf_lookup_token_commercial_' . auth()->id();
    }

    private function puertaFriaLookupVerifiedKey(): string
    {
        return 'pf_lookup_verified_commercial_' . auth()->id();
    }

    private function fileFields(): array
    {
        return [
            'precontractual', 'foto_sorteo', 'dni_anverso', 'dni_reverso',
            'documento_titularidad', 'nomina', 'pension', 'otros_documentos',
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if ($this->requiresPuertaFriaLookup()) {
            $this->puertaFriaLookupToken = (string) Str::uuid();
            $this->puertaFriaLookupCompleted = false;
            $this->lookupSearched = false;
            $this->lookupResults = [];
            $this->lookupSelectedChoice = null;
            $this->lookupMessage = null;
            $this->lookupMessageStatus = null;

            session()->put($this->puertaFriaLookupTokenKey(), $this->puertaFriaLookupToken);
            session()->forget($this->puertaFriaLookupVerifiedKey());
            session()->forget($this->sessionKey());

            $this->form->fill([]);

            $this->dispatch('open-puerta-fria-lookup-modal');

            return;
        }

        $key = $this->sessionKey();
        if (session()->has($key)) {
            $saved = session($key);
            foreach ($this->fileFields() as $field) {
                unset($saved[$field]);
            }
            $this->form->fill(array_merge($this->data, $saved));
        }
    }

    public function cancelPuertaFriaLookup(): void
    {
        $this->dispatch('close-puerta-fria-lookup-modal');
    }

    public function openPuertaFriaLookupModal(): void
    {
        $this->dispatch('open-puerta-fria-lookup-modal');
    }

    public function clearPuertaFriaLookupSearch(): void
    {
        $this->lookupSearched = false;
        $this->lookupMessage = null;
        $this->lookupMessageStatus = null;
        $this->lookupResults = [];
        $this->lookupSelectedChoice = null;
    }

    public function create(bool $another = false): void
    {
        $this->assertPuertaFriaLookupCompleted();

        parent::create($another);
    }

    protected function assertPuertaFriaLookupCompleted(): void
    {
        if (! $this->requiresPuertaFriaLookup()) {
            return;
        }

        $expected = session($this->puertaFriaLookupTokenKey());
        $verified = session($this->puertaFriaLookupVerifiedKey());

        if (
            ! $this->puertaFriaLookupCompleted
            || blank($expected)
            || $verified !== $expected
        ) {
            $this->dispatch('open-puerta-fria-lookup-modal');

            Notification::make()
                ->title('Búsqueda obligatoria')
                ->body('Debes buscar el cliente en el modal y pulsar Continuar antes de crear el contrato.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'first_names' => 'Completa la búsqueda de cliente en el modal antes de guardar.',
            ]);
        }
    }

    public function searchPuertaFriaCustomers(): void
    {
        if (trim($this->lookupPhone) === '' || trim($this->lookupName) === '') {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Introduce el teléfono y el nombre del cliente antes de buscar.')
                ->warning()
                ->send();

            return;
        }

        $search = PuertaFriaCustomerSearch::search($this->lookupPhone, $this->lookupName);
        $this->lookupSearched = true;
        $this->lookupMessage = $search['message'];
        $this->lookupMessageStatus = $search['status'];
        $this->lookupSelectedChoice = null;

        $this->lookupResults = $search['customers']
            ->map(function (Customer $customer) use ($search): array {
                $phoneDigits = PuertaFriaCustomerSearch::primaryPhoneDigits($customer) ?? $search['phone_digits'];

                return [
                    'id' => $customer->id,
                    'name' => PuertaFriaCustomerSearch::displayName($customer),
                    'dni' => $customer->dni,
                    'phone' => $phoneDigits,
                ];
            })
            ->values()
            ->all();
    }

    public function continuePuertaFriaLookup(): void
    {
        if (! $this->lookupSearched) {
            Notification::make()
                ->title('Busca primero el cliente')
                ->warning()
                ->send();

            return;
        }

        if (blank($this->lookupSelectedChoice)) {
            Notification::make()
                ->title('Selecciona un cliente o crea uno nuevo')
                ->warning()
                ->send();

            return;
        }

        $prefill = [];

        if ($this->lookupSelectedChoice !== '__new__') {
            $customer = Customer::query()->find((int) $this->lookupSelectedChoice);

            if (! $customer) {
                Notification::make()
                    ->title('Cliente no encontrado')
                    ->danger()
                    ->send();

                return;
            }

            $prefill = PuertaFriaCustomerSearch::customerToFormData($customer);
        } else {
            ['first_names' => $firstNames, 'last_names' => $lastNames] = PuertaFriaCustomerSearch::splitLookupName($this->lookupName);
            $digits = preg_replace('/\D+/', '', $this->lookupPhone);
            $formattedPhone = strlen($digits) === 9
                ? implode(' ', str_split($digits, 3))
                : $this->lookupPhone;

            $prefill = [
                'pf_existing_customer_id' => null,
                'first_names' => $firstNames,
                'last_names' => $lastNames,
                'phone1_commercial' => $formattedPhone,
            ];
        }

        $this->form->fill(array_merge($this->data, $prefill));
        session()->put($this->puertaFriaLookupVerifiedKey(), $this->puertaFriaLookupToken);
        $this->puertaFriaLookupCompleted = true;
        $this->dispatch('close-puerta-fria-lookup-modal');

        Notification::make()
            ->title($this->lookupSelectedChoice === '__new__'
                ? 'Puedes continuar creando el cliente nuevo'
                : 'Cliente seleccionado. Revisa los datos y continúa con la venta.')
            ->success()
            ->send();
    }

    public function dehydrate(): void
    {
        if ($this->isPuertaFriaLookupBlockingForm()) {
            return;
        }

        $toSave = $this->data;
        foreach ($this->fileFields() as $field) {
            unset($toSave[$field]);
        }
        session()->put($this->sessionKey(), $toSave);
        session()->save();
    }

    protected function afterCreate(): void
    {
        session()->forget($this->sessionKey());
        session()->forget($this->puertaFriaLookupTokenKey());
        session()->forget($this->puertaFriaLookupVerifiedKey());
    }

    protected function getSubmitFormAction(): Action
    {
        return parent::getSubmitFormAction()
            ->disabled(fn (): bool => $this->isSubmitBlocked())
            ->tooltip(fn (): ?string => $this->submitBlockedTooltip());
    }

    protected function isSubmitBlocked(): bool
    {
        if ($this->requiresPuertaFriaLookup() && ! $this->puertaFriaLookupCompleted) {
            return true;
        }

        if (ActionGps::shouldRegisterGps() && ! GpsActionForm::gpsReadyOnForm($this->data ?? [])) {
            return true;
        }

        return false;
    }

    protected function submitBlockedTooltip(): ?string
    {
        if ($this->requiresPuertaFriaLookup() && ! $this->puertaFriaLookupCompleted) {
            return 'Debes completar la búsqueda de cliente en el modal.';
        }

        if (ActionGps::shouldRegisterGps() && ! GpsActionForm::gpsReadyOnForm($this->data ?? [])) {
            return 'Esperando ubicación GPS…';
        }

        return null;
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Datos del contrato')
                ->icon('heroicon-o-document-text')
                ->description('Información del cliente y de la venta')
                ->schema(VentaDesdeCeroResource::step1Schema()),

            Step::make('Documentos y Fotos')
                ->icon('heroicon-o-camera')
                ->description('Sube los documentos requeridos')
                ->schema(VentaDesdeCeroResource::step2Schema()),
        ];
    }

    protected function handleRecordCreation(array $data): Venta
    {
        $this->assertPuertaFriaLookupCompleted();

        return DB::transaction(function () use ($data) {

            if (($data['companion_id'] ?? null) === '__NONE__') {
                $data['companion_id'] = null;
            }

            if (! blank($data['companion_id']) && ! User::where('id', $data['companion_id'])->exists()) {
                $data['companion_id'] = null;
            }

            $fechaVenta = ! empty($data['manual_created_at'])
                ? VentaFechaVenta::normalizeOnSave(
                    $data['manual_created_at'] instanceof Carbon
                        ? $data['manual_created_at']
                        : Carbon::parse($data['manual_created_at'], VentaFechaVenta::timezone()),
                )
                : VentaFechaVenta::normalizeOnCreate();

            unset($data['age']);

            if (($data['interes_art'] ?? false) && blank($data['interes_art_detalle'] ?? null)) {
                throw ValidationException::withMessages([
                    'interes_art_detalle' => 'Especifica los artículos de interés.',
                ]);
            }

            $customerFillable = (new Customer)->getFillable();
            $customerPayload = array_intersect_key($data, array_flip($customerFillable));

            if (! empty($data['pf_existing_customer_id'])) {
                /** @var Customer $customer */
                $customer = Customer::query()->findOrFail((int) $data['pf_existing_customer_id']);
                $toUpdate = array_filter($customerPayload, fn ($value) => $value !== null && $value !== '');

                if ($toUpdate !== []) {
                    $customer->fill($toUpdate)->save();
                }
            } else {
                $normalizedFirst = Str::slug(Str::lower($data['first_names'] ?? ''), '');
                $normalizedLast = Str::slug(Str::lower($data['last_names'] ?? ''), '');

                /** @var Customer|null $customer */
                $customer = Customer::query()
                    ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirst])
                    ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLast])
                    ->where('phone1_commercial', $data['phone1_commercial'] ?? null)
                    ->first();

                if (! $customer && filled($data['phone1_commercial'] ?? null)) {
                    $customer = Customer::query()
                        ->where('phone1_commercial', $data['phone1_commercial'])
                        ->first();
                }

                if ($customer) {
                    $toUpdate = array_filter($customerPayload, fn ($value) => $value !== null && $value !== '');

                    if ($toUpdate !== []) {
                        $customer->fill($toUpdate)->save();
                    }
                } else {
                    $customer = Customer::create($customerPayload);
                }
            }

            $comercialId = $data['nota_comercial_id'] ?? auth()->id();
            $existingNote = VentaOrigenResolver::findReusableAssignedNote($customer);
            $origenVenta = OrigenVenta::PUERTA_FRIA;

            if ($existingNote) {
                $existingNote->update([
                    'estado_terminal' => EstadoTerminal::VENTA,
                    'comercial_id' => $comercialId,
                    'reten' => false,
                ]);
                $note = $existingNote->fresh();
                VentaOrigenResolver::repairMislabeledFuente($note);
                $note = $note->fresh();
                $origenVenta = OrigenVenta::VENTA_NORMAL;
            } else {
                $noteFillable = (new Note)->getFillable();

                $notaBase = [
                    'user_id' => auth()->id(),
                    'customer_id' => $customer->id,
                    'comercial_id' => $comercialId,
                    'status' => $data['nota_status'] ?? NoteStatus::CONTACTED->value,
                    'visit_date' => $data['nota_visit_date'] ?? null,
                    'visit_schedule' => $data['nota_visit_schedule'] ?? null,
                    'assignment_date' => $comercialId ? now() : null,
                    'show_phone' => $data['nota_show_phone'] ?? true,
                    'de_camino' => $data['nota_de_camino'] ?? false,
                    'ayuntamiento' => $data['nota_ayuntamiento'] ?? null,
                    'created_at' => $fechaVenta,
                    'updated_at' => $fechaVenta,
                    'estado_terminal' => EstadoTerminal::VENTA,
                    'fuente' => \App\Enums\FuenteNotas::PTA_FRIA->value,
                ];

                $notaPayload = array_intersect_key($notaBase, array_flip($noteFillable));
                /** @var Note $note */
                $note = Note::create($notaPayload);
            }

            $notaPayload = ['comercial_id' => $note->comercial_id];

            if (($data['modalidad_pago'] ?? 'Financiado') === 'Contado') {
                $data['num_cuotas'] = 1;
            }
            $cuotas = (int) ($data['num_cuotas'] ?? 0);
            $cuotaMensual = $cuotas > 0
                ? round(((float) ($data['importe_total'] ?? 0)) / $cuotas, 2)
                : null;

            ['lat' => $ventaLat, 'lng' => $ventaLng] = ActionGps::assertCoordsForVentaOrFail(
                $note->lat,
                $note->lng,
                $data,
                auth()->user(),
            );

            $venta = Venta::create([
                'note_id' => $note->id,
                'customer_id' => $customer->id,
                'comercial_id' => $notaPayload['comercial_id'] ?? auth()->id(),
                'companion_id' => $data['companion_id'],
                'lat' => $ventaLat,
                'lng' => $ventaLng,

                'fecha_venta' => $fechaVenta,
                'created_at' => $fechaVenta,
                'updated_at' => $fechaVenta,

                'importe_total' => $data['importe_total'] ?? 0,
                'modalidad_pago' => $data['modalidad_pago'] ?? 'Financiado',
                'forma_pago' => ($data['modalidad_pago'] ?? null) === 'Contado' ? ($data['forma_pago'] ?? null) : null,
                'num_cuotas' => $data['num_cuotas'] ?? null,
                'cuota_mensual' => $cuotaMensual,
                'accesorio_entregado' => $data['accesorio_entregado'] ?? null,
                'crema' => $data['crema'] ?? null,
                'motivo_venta' => $data['motivo_venta'] ?? null,
                'motivo_horario' => $data['motivo_horario'] ?? null,
                'interes_art' => $data['interes_art'] ?? false,
                'interes_art_detalle' => ($data['interes_art'] ?? false) ? ($data['interes_art_detalle'] ?? null) : null,
                'observaciones_repartidor' => $data['observaciones_repartidor'] ?? null,
                'productos_externos' => collect($data['productos_externos'] ?? [])->filter()->values()->all(),
                'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'horario_entrega' => $data['horario_entrega'] ?? null,
                'precontractual' => $data['precontractual'] ?? null,
                'dni_anverso' => $data['dni_anverso'] ?? null,
                'dni_reverso' => $data['dni_reverso'] ?? null,
                'documento_titularidad' => $data['documento_titularidad'] ?? null,
                'nomina' => $data['nomina'] ?? null,
                'pension' => $data['pension'] ?? null,
                'otros_documentos' => $data['otros_documentos'] ?? null,
                'foto_sorteo' => $data['foto_sorteo'] ?? null,

                'origen_venta' => $origenVenta,
            ]);

            $this->form->model($venta)->saveRelationships();
            $venta->recomputarImportesDesdeOfertas();
            $venta->calcularComisiones(true);
            $venta->recomputarVtasRepYEsp()->recalcularVtasAcumuladas(true);
            $venta->calcularPas(true);

            $entrada = (float) ($venta->entrada ?? 0);
            $montoExtra = (float) ($venta->monto_extra ?? 0);
            $venta->total_final = round(((float) $venta->importe_total - $entrada) + $montoExtra, 2);

            $venta->cuota_final = (int) $venta->num_cuotas > 0
                ? round($venta->total_final / (int) $venta->num_cuotas, 2)
                : null;

            if (empty($venta->nro_contr_adm) && ! empty($venta->nro_contrato)) {
                $venta->nro_contr_adm = $venta->nro_contrato;
            }
            if (empty($venta->nro_cliente_adm) && ! empty($customer->nro_cliente)) {
                $venta->nro_cliente_adm = $customer->nro_cliente;
            }

            $venta->refreshEstadoEntrega();
            $venta->save();

            $venta->timestamps = false;
            $venta->created_at = $fechaVenta;
            $venta->updated_at = $fechaVenta;
            $venta->save();
            $venta->timestamps = true;

            unset($data['pf_existing_customer_id']);

            DB::afterCommit(function () use ($venta) {
                event(new VentaCreada($venta));
            });

            return $venta;
        });
    }

    protected function getRedirectUrl(): string
    {
        return NotasHoy::getUrl();
    }
}
