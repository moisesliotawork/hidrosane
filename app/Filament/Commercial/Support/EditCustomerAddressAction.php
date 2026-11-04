<?php

namespace App\Filament\Commercial\Support;

use App\Models\Customer;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Throwable;

class EditCustomerAddressAction
{
    /** @var list<string> */
    public const ADDRESS_FIELDS = [
        'primary_address',
        'secondary_address',
        'nro_piso',
        'postal_code',
        'ciudad',
        'provincia',
        'ayuntamiento',
        'parish',
    ];

    public static function make(): Action
    {
        return Action::make('editar_direccion')
            ->label('Edit')
            ->button()
            ->size(ActionSize::ExtraSmall)
            ->color('warning')
            ->icon('heroicon-m-pencil-square')
            ->modalHeading('Editar dirección')
            ->modalSubmitActionLabel('Guardar')
            ->modalWidth('lg')
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('primary_address')
                        ->label('Dirección principal')
                        ->required()
                        ->rows(3)
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('secondary_address')
                        ->label('Dirección secundaria (opcional)')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('nro_piso')
                        ->label('No. y Piso')
                        ->required()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Código postal')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('ciudad')
                        ->label('Ayuntamiento/Localidad')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('provincia')
                        ->label('Provincia')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('ayuntamiento')
                        ->label('Ayuntamiento')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('parish')
                        ->label('Parroquia (opcional)')
                        ->maxLength(255),
                ]),
            ])
            ->fillForm(function (?Note $record): array {
                return self::addressFormStateFromCustomer($record?->customer);
            })
            ->action(function (Note $record, array $data, $livewire): void {
                $customer = $record->customer;

                if ($customer === null) {
                    Notification::make()
                        ->title('Cliente no encontrado')
                        ->danger()
                        ->send();

                    return;
                }

                self::persistAddress($customer, $data);

                $record->unsetRelation('customer');
                $customer->refresh();

                self::refreshLivewireAddressFields($livewire, $customer);

                Notification::make()
                    ->title('Dirección actualizada')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistAddress(Customer $customer, array $data): void
    {
        $customer->update([
            'primary_address' => $data['primary_address'] ?? null,
            'secondary_address' => $data['secondary_address'] ?? null,
            'nro_piso' => $data['nro_piso'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'provincia' => $data['provincia'] ?? null,
            'ayuntamiento' => $data['ayuntamiento'] ?? null,
            'parish' => $data['parish'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function addressFormStateFromCustomer(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        $state = [];

        foreach (self::ADDRESS_FIELDS as $field) {
            $state[$field] = $customer->getAttribute($field);
        }

        return $state;
    }

    public static function refreshLivewireAddressFields(object $livewire, Customer $customer): void
    {
        try {
            // fillForm() en EditRecord es protected: no llamar desde fuera (provoca 500 vía Livewire __call).
            if (isset($livewire->form) && is_object($livewire->form) && method_exists($livewire->form, 'fill')) {
                $state = method_exists($livewire->form, 'getRawState')
                    ? (array) $livewire->form->getRawState()
                    : [];

                $livewire->form->fill(array_merge($state, self::addressFormStateFromCustomer($customer)));

                return;
            }

            if (property_exists($livewire, 'data') && is_array($livewire->data)) {
                foreach (self::addressFormStateFromCustomer($customer) as $field => $value) {
                    $livewire->data[$field] = $value;
                }
            }
        } catch (Throwable) {
            // La dirección ya está guardada; no tumbar la petición por un fallo al refrescar UI.
        }
    }
}
