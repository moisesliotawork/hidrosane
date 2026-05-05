<?php

namespace App\Filament\Teleoperator\Resources\OficinaEditResource\Pages;

use App\Filament\Teleoperator\Resources\OficinaEditResource;
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
                    Forms\Components\TextInput::make('phone')
                        ->label('Número de teléfono')
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
