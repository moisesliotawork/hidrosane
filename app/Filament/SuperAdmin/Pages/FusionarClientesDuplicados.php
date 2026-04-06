<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Customer;
use App\Services\CustomerMergeService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FusionarClientesDuplicados extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Fusionar duplicados';
    protected static ?string $title = 'Fusionar clientes duplicados';
    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.super-admin.pages.fusionar-clientes-duplicados';

    public ?array $data = [];

    public array $results = [];

    public bool $searched = false;

    public function mount(): void
    {
        $this->form->fill([
            'phone_query' => null,
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Buscar coincidencias por teléfono')
                    ->schema([
                        TextInput::make('phone_query')
                            ->label('Teléfono')
                            ->placeholder('Ej: 612345678')
                            ->required()
                            ->maxLength(30)
                            ->helperText('Busca coincidencia exacta en phone, secondary_phone, third_phone, phone1_commercial y phone2_commercial.')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('buscar')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->action(fn() => $this->buscar())
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    public function buscar(): void
    {
        $rawPhone = (string) ($this->data['phone_query'] ?? '');
        $phone = $this->normalizePhone($rawPhone);

        $this->searched = true;
        $this->results = [];

        if (blank($phone)) {
            Notification::make()
                ->title('Debes escribir un teléfono válido.')
                ->warning()
                ->send();

            return;
        }

        $customers = Customer::query()
            ->whereNull('merged_into_id')
            ->where(function ($query) use ($phone) {
                $query->where('phone', $phone)
                    ->orWhere('secondary_phone', $phone)
                    ->orWhere('third_phone', $phone)
                    ->orWhere('phone1_commercial', $phone)
                    ->orWhere('phone2_commercial', $phone);
            })
            ->withCount(['notes', 'ventas'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($customers->count() < 2) {
            Notification::make()
                ->title('No se encontraron duplicados para fusionar.')
                ->body('Debe haber al menos 2 customers activos con coincidencia exacta en ese teléfono.')
                ->warning()
                ->send();

            return;
        }

        $oldest = $customers
            ->sortBy(fn(Customer $c) => [
                optional($c->created_at)->timestamp ?? PHP_INT_MAX,
                $c->id,
            ])
            ->first();

        $latestUpdated = $customers
            ->sortByDesc(fn(Customer $c) => [
                optional($c->updated_at)->timestamp ?? 0,
                $c->id,
            ])
            ->first();

        $this->results = $customers
            ->map(function (Customer $customer) use ($oldest, $latestUpdated) {
                return [
                    'id' => $customer->id,
                    'name' => trim(($customer->first_names ?? '') . ' ' . ($customer->last_names ?? '')),
                    'phone' => $customer->phone,
                    'secondary_phone' => $customer->secondary_phone,
                    'third_phone' => $customer->third_phone,
                    'phone1_commercial' => $customer->phone1_commercial,
                    'phone2_commercial' => $customer->phone2_commercial,
                    'email' => $customer->email,
                    'dni' => $customer->dni,
                    'created_at' => optional($customer->created_at)?->format('d/m/Y H:i'),
                    'updated_at' => optional($customer->updated_at)?->format('d/m/Y H:i'),
                    'notes_count' => $customer->notes_count,
                    'ventas_count' => $customer->ventas_count,
                    'is_oldest' => $oldest?->id === $customer->id,
                    'is_latest_updated' => $latestUpdated?->id === $customer->id,
                ];
            })
            ->values()
            ->all();

        Notification::make()
            ->title('Coincidencias encontradas')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fusionar')
                ->label('Fusionar')
                ->icon('heroicon-o-arrows-right-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Fusionar clientes duplicados')
                ->modalDescription('Se moverán todas las notas y ventas al customer más antiguo. Los demás quedarán marcados como fusionados.')
                ->visible(fn() => count($this->results) >= 2)
                ->action(function (CustomerMergeService $mergeService) {
                    $rawPhone = (string) ($this->data['phone_query'] ?? '');
                    $phone = $this->normalizePhone($rawPhone);

                    if (blank($phone)) {
                        Notification::make()
                            ->title('Teléfono inválido.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $result = $mergeService->mergeByPhone($phone, auth()->id());

                        $this->buscar();

                        Notification::make()
                            ->title('Fusión completada')
                            ->body(
                                "Customer principal: #{$result['keeper_id']} | " .
                                "Notas movidas: {$result['notes_updated']} | " .
                                "Ventas movidas: {$result['ventas_updated']}"
                            )
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error al fusionar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        return filled($phone) ? $phone : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('app_support');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('app_support');
    }
}