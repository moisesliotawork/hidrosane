<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Page;
use App\Models\Note;
use App\Models\Team;

class Notas2 extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'NOTAS';
    protected static ?string $slug = 'notas';
    protected static string $view = 'filament.commercial.pages.notas2';

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['team_leader', 'sales_manager'])) {
            return 'Notas JE';
        }

        return 'Notas';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        $ids = collect([$user->id]);

        if ($user->hasRole('team_leader')) {
            $team = Team::where('team_leader_id', $user->id)->first();
            if ($team) {
                $ids = $ids->merge($team->members()->pluck('users.id'))->unique();
            }
        } elseif ($user->hasRole('sales_manager')) {
            return null;
        }

        $count = Note::query()
            ->whereIn('comercial_id', $ids->values()->all())
            ->whereDate('assignment_date', today())
            ->where('reten', false)
            ->whereDoesntHave('venta')
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
