<?php

namespace App\Filament\Gerente\Pages;

use Filament\Pages\Page;
use App\Models\User;

class NotasDeComercial extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = null;
    protected static ?string $title = 'Notas del Comercial';
    protected static ?string $slug = 'notas-de-comercial';
    protected static string $view = 'filament.gerente.pages.notas-de-comercial';
    protected static bool $shouldRegisterNavigation = false;

    public string|int|null $comercialId = null;
    public ?User $comercial = null;
    public bool $esReten = false;

    public function mount(): void
    {
        $raw = request()->query('comercial_id');

        if ($raw === 'reten') {
            // Modo RETEN
            $this->esReten = true;
            $this->comercialId = 'reten';
            return;
        }

        // Modo normal: comercial por ID
        $id = (int) $raw;
        abort_unless($id > 0, 404);

        $this->comercial = User::find($id);
        abort_unless($this->comercial !== null, 404);

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
