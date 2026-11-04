<?php

namespace App\Filament\SuperAdmin\Resources\FinancieraResource\Pages;

use App\Filament\SuperAdmin\Resources\FinancieraResource;
use App\Models\Financiera;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFinancieras extends ListRecords
{
    protected static string $resource = FinancieraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Agregar financiera')
                ->modalHeading('Agregar financiera')
                ->mutateFormDataUsing(function (array $data): array {
                    $nombre = trim((string) ($data['nombre'] ?? ''));
                    $data['nombre'] = $nombre;
                    $data['codigo'] = $nombre;
                    $data['fecha_inclusion'] = now()->toDateString();

                    return $data;
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('TODAS')
                ->badge(fn (): int => Financiera::query()->count())
                ->badgeColor('info'),
            'activas' => Tab::make('ACTIVAS')
                ->badge(fn (): int => Financiera::query()->activas()->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->activas()),
            'baja' => Tab::make('BAJA')
                ->badge(fn (): int => Financiera::query()->deBaja()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->deBaja()),
        ];
    }
}
