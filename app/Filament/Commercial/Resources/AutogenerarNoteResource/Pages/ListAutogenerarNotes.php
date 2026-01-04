<?php

namespace App\Filament\Commercial\Resources\AutogenerarNoteResource\Pages;

use App\Filament\Commercial\Resources\AutogenerarNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Commercial\Pages\Notas2;

class ListAutogenerarNotes extends ListRecords
{
    protected static string $resource = AutogenerarNoteResource::class;

    public function mount(): void
    {
        parent::mount();

        // Redirige de una a la Page Notas2
        $url = Notas2::getUrl(panel: 'comercial'); // si te da error, quita panel: 'comercial'
        $this->redirect($url);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
