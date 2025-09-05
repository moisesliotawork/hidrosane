<?php

namespace App\Filament\HeadOfRoom\Resources\BusquedaCpResource\Pages;

use App\Filament\HeadOfRoom\Resources\BusquedaCpResource;
use App\Models\PostalCode;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\{Grid, TextInput, Placeholder};
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Actions\Action;


class BuscarCp extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = BusquedaCpResource::class;
    protected static string $view = 'filament.superAdmin.resources.busqueda-cp.buscar-cp';

    /** Estado del formulario */
    public ?array $data = [
        'cp' => null,
    ];

    /** Resultado de la búsqueda */
    public ?PostalCode $resultado = null;

    public function mount(): void
    {
        // Permite llegar con ?cp=15008
        $cp = request()->query('cp');
        if ($cp) {
            $this->data['cp'] = $cp;
            $this->buscar();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(['default' => 5, 'sm' => 5, 'lg' => 5])
                    ->schema([
                        TextInput::make('cp')
                            ->label('Código postal')
                            ->placeholder('15008')
                            ->required()
                            ->extraInputAttributes(['inputmode' => 'numeric'])
                            ->maxLength(10),
                    ]),
            ])
            ->statePath('data');
    }

    /** Acción de buscar en BD */
    public function buscar(): void
    {
        $cp = trim((string) ($this->data['cp'] ?? ''));
        $this->resultado = null;

        if ($cp !== '') {
            $this->resultado = PostalCode::query()
                ->with(['city.state.country'])
                ->where('code', $cp)
                ->first();
        }
    }

    /** Acciones de cabecera: botón Buscar */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('buscar')
                ->label('Buscar')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->action(fn() => $this->buscar()),
        ];
    }
}
