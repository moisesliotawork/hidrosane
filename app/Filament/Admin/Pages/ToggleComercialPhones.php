<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Models\User;
use App\Models\Note;

class ToggleComercialPhones extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Teléfonos por comercial';
    protected static ?string $slug = 'telefonos-comercial';
    protected static ?string $title = 'Mostrar / ocultar teléfonos';

    protected static string $view = 'filament.admin.pages.toggle-comercial-phones';

    /** Datos del formulario */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['show_phone' => false]);
    }

    /** --- ESQUEMA DEL FORMULARIO --- */
    protected function getFormSchema(): array
    {
        return [
            Select::make('comercial_id')
                ->label('Comercial')
                ->options(
                    // Solo usuarios con rol “comercial”
                    User::role('commercial')->orderBy('name')->pluck('name', 'id')
                )
                ->searchable()
                ->required(),

            Toggle::make('show_phone')
                ->label('Mostrar teléfonos en sus notas')
                ->helperText('Activa = se mostrarán los números al comercial'),
        ];
    }

    /** --- ACCIÓN AL PULSAR “Actualizar” --- */
    public function submit(): void
    {
        $state = $this->form->getState();

        $total = Note::where('comercial_id', $state['comercial_id'])
            ->update(['show_phone' => $state['show_phone']]);

        Notification::make()
            ->title($state['show_phone'] ? 'Teléfonos ACTIVADOS' : 'Teléfonos DESACTIVADOS')
            ->body("Se actualizaron {$total} notas.")
            ->success()
            ->send();

        // Limpia el toggle para evitar confusiones
        $this->form->fill(['show_phone' => false]);
    }
    protected function getFormStatePath(): string
    {
        return 'data';
    }
}
