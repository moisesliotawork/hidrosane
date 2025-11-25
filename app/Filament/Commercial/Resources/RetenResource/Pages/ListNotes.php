<?php

namespace App\Filament\Commercial\Resources\RetenResource\Pages;

use App\Filament\Commercial\Resources\RetenResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{Team, Note};

class ListNotes extends ListRecords
{
    protected static string $resource = RetenResource::class;

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user->hasRole('team_leader')) {
            return 'NOTAS RETEN';
        }

        return 'Notas';
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }


}
