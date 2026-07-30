<?php

namespace App\Support\Filament;

use Closure;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

final class BorradosRestoreColumn
{
    public static function make(
        string $modalHeading,
        string $modalDescription,
        string $successNotificationTitle,
        Closure $using,
    ): TextColumn {
        return TextColumn::make('restaurar')
            ->label('Restaurar')
            ->state('Restaurar')
            ->badge()
            ->color('success')
            ->alignCenter()
            ->action(
                Action::make('restaurar')
                    ->label('Restaurar')
                    ->requiresConfirmation()
                    ->modalHeading($modalHeading)
                    ->modalDescription($modalDescription)
                    ->modalSubmitActionLabel('Sí, restaurar')
                    ->successNotificationTitle($successNotificationTitle)
                    ->action(function (Model $record) use ($using): void {
                        $using($record);
                    }),
            );
    }
}
