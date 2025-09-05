<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;
use App\Models\User;

class NotasDeComercial extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = null; // no mostrar en menú
    protected static ?string $title = 'Notas del Comercial';
    protected static ?string $slug = 'notas-de-comercial';
    protected static string $view = 'filament.commercial.pages.notas-de-comercial';
    protected static bool $shouldRegisterNavigation = false;

    public ?int $comercialId = null;
    public ?User $comercial = null;

    public function mount(): void
    {
        $this->comercialId = (int) request()->query('comercial_id');
        abort_unless($this->comercialId > 0, 404);

        $this->comercial = User::find($this->comercialId);
        abort_unless($this->comercial, 404);
    }

    public function getTitle(): string
    {
        $nombre = trim(($this->comercial->name ?? '') . ' ' . ($this->comercial->last_name ?? ''));
        $empleado = $this->comercial->empleado_id ?: 'SIN-ID';

        return "Notas del Comercial - {$nombre} - {$empleado}";
    }
}
