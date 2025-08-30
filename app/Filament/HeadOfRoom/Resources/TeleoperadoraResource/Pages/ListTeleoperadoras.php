<?php

// App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages\ListTeleoperadoras.php
namespace App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;

use App\Enums\EstadoTerminal;
use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource;
use App\Models\Note;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTeleoperadoras extends ListRecords
{
    protected static string $resource = TeleoperadoraResource::class;

}
