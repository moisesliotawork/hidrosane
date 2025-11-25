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

    /** Puede ser ID numérico o la cadena 'reten' */
    public string|int|null $comercialId = null;
    public ?User $comercial = null;
    public bool $esReten = false;

    public function mount(): void
    {
        $raw = request()->query('comercial_id');

        // Caso RETEN
        if ($raw === 'reten') {
            $this->esReten = true;
            $this->comercialId = 'reten';
            // No buscamos usuario
            return;
        }

        // Caso normal: comercial por ID
        $id = (int) $raw;
        abort_unless($id > 0, 404);

        $this->comercial = User::find($id);
        abort_unless($this->comercial, 404);

        $this->comercialId = $this->comercial->id;
    }

    public function getTitle(): string
    {
        if ($this->esReten) {
            return 'Notas RETEN';
        }

        $nombre = trim(($this->comercial->name ?? '') . ' ' . ($this->comercial->last_name ?? ''));
        $empleado = $this->comercial->empleado_id ?: 'SIN-ID';

        return "Notas del Comercial - {$nombre} - {$empleado}";
    }
}
