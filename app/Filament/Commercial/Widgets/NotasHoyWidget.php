<?php

namespace App\Filament\Commercial\Widgets;

use App\Filament\Commercial\Pages\Notas2;
use App\Models\Note;
use App\Models\Team;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class NotasHoyWidget extends Widget
{
    protected static string $view = 'filament.commercial.widgets.notas-hoy-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(['commercial', 'team_leader', 'sales_manager']);
    }

    public function getNotasHoyCount(): int
    {
        $user = Auth::user();

        if (! $user) {
            return 0;
        }

        $query = Note::query()
            ->whereDoesntHave('venta')
            ->notBlockedByRecentSale()
            ->commercialVisibleDateToday()
            ->where('reten', false)
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
            });

        if (! $user->hasRole('sales_manager')) {
            $query->whereIn('comercial_id', $this->allowedComercialIds());
        }

        return $query->count();
    }

    public function getNotasUrl(): string
    {
        return Notas2::getUrl();
    }

    /** @return list<int> */
    protected function allowedComercialIds(): array
    {
        $user = Auth::user();
        $ids = collect([$user->id]);

        if ($user->hasRole('team_leader')) {
            $team = Team::where('team_leader_id', $user->id)->first();

            if ($team) {
                $ids = $ids->merge(
                    $team->members()->pluck('users.id')
                )->unique();
            }
        }

        return $ids->values()->all();
    }
}
