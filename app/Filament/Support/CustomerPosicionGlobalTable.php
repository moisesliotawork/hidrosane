<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class CustomerPosicionGlobalTable
{
    public static function applyEagerLoads(Builder $query): Builder
    {
        return $query->with(['latestNoteWithDentroGps']);
    }

    public static function gpsDentroColumn(): TextColumn
    {
        return TextColumn::make('dentro_gps')
            ->label('GPS')
            ->state(fn ($record): ?string => $record->hasDentroGps() ? 'IR gps' : null)
            ->placeholder('')
            ->url(fn ($record): ?string => $record->dentroGpsMapsUrl())
            ->openUrlInNewTab()
            ->badge()
            ->color('success')
            ->alignCenter();
    }
}
