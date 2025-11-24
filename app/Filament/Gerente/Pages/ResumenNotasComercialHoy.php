<?php

namespace App\Filament\Gerente\Pages;

use App\Models\User;
use App\Models\Note;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Carbon\Carbon;
use App\Enums\EstadoTerminal;

class ResumenNotasComercialHoy extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.gerente.pages.resumen-notas-comercial-hoy';

    public ?User $comercial = null;

    public function mount(): void
    {
        $comercialId = request()->query('comercial_id');
        $this->comercial = User::findOrFail($comercialId);
    }

    public function getTitle(): string
    {
        return 'Resumen diario - ' . trim(
            $this->comercial->name . ' ' . ($this->comercial->last_name ?? '')
        );
    }

    public function table(Table $table): Table
    {
        $hoy = Carbon::today();

        $query = Note::query()
            ->selectRaw('COALESCE(estado_terminal, "") as estado_terminal, COUNT(*) as total')
            ->where('comercial_id', $this->comercial->id)
            ->whereDate('created_at', $hoy)
            ->groupBy('estado_terminal')
            ->reorder('estado_terminal');  // 👈 reset ORDER BY y ordena por estado_terminal

        return $table
            ->query(fn() => $query)
            ->columns([
                Tables\Columns\TextColumn::make('estado_terminal')
                    ->label('Estado')
                    ->formatStateUsing(function (?string $state): string {
                        return match ($state) {
                            EstadoTerminal::VENTA->value => 'Ventas',
                            EstadoTerminal::NUL->value => 'Nulas',
                            EstadoTerminal::CONFIRMADO->value => 'Confirmadas',
                            EstadoTerminal::AUSENTE->value => 'Ausentes',
                            EstadoTerminal::SALA->value => 'Oficina',
                            '', null => 'Sin confirmar',
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Cantidad')
                    ->sortable(),
            ])
            ->paginated(false)
            ->striped();
    }


}
