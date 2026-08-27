<?php

namespace App\Filament\Teleoperator\Resources\OficinaEditResource\Pages;

use App\Filament\Teleoperator\Resources\OficinaEditResource;
use App\Filament\Support\CustomerPhoneForm;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOficinaEdit extends ListRecords
{
    protected static string $resource = OficinaEditResource::class;

    public string $telefonoBusqueda = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buscarTlf')
                ->label('BUSCAR TLF')
                ->icon('heroicon-o-magnifying-glass')
                ->color('warning')
                ->modalHeading('Buscar nota por teléfono')
                ->modalSubmitActionLabel('Buscar')
                ->form([
                    CustomerPhoneForm::make('phone', 'Número de teléfono', required: true),
                ])
                ->action(function (array $data) {
                    $this->telefonoBusqueda = preg_replace('/\D+/', '', $data['phone']);
                    $this->resetPage();
                }),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->telefonoBusqueda) {
            $digits = $this->telefonoBusqueda;
            $query->whereHas('customer', fn($q) =>
                $q->where('phone', $digits)
                    ->orWhere('secondary_phone', $digits)
                    ->orWhere('third_phone', $digits)
            );
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
