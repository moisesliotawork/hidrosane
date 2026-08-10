<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers;

use App\Models\VentaPdfDownload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabla "PDF Borrados": copias de PDF archivadas que se han eliminado (soft-delete)
 * desde "PDF Descargados". Solo visible en SuperAdmin; permite restaurarlas.
 */
class PdfBorradosRelationManager extends RelationManager
{
    protected static string $relationship = 'pdfDownloads';

    protected static ?string $title = 'PDF Borrados';

    protected static ?string $modelLabel = 'PDF borrado';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Borrado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha original')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user')
                    ->label('Usuario')
                    ->state(function (VentaPdfDownload $record): string {
                        $user = $record->user;

                        if (! $user) {
                            return '—';
                        }

                        return trim($user->name . ' ' . $user->last_name);
                    }),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'B' ? 'warning' : 'info')
                    ->formatStateUsing(fn (?string $state): string => $state === 'B' ? 'Contrato -B' : 'Contrato'),
                Tables\Columns\TextColumn::make('origen')
                    ->label('Vía')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'vista_previa' ? 'gray' : 'success')
                    ->formatStateUsing(fn (?string $state): string => $state === 'vista_previa' ? 'Vista previa' : 'Descarga'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\RestoreAction::make()
                    ->label('')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restaurar PDF')
                    ->modalDescription('Volverá a aparecer en "PDF Descargados".')
                    ->successNotificationTitle('PDF restaurado'),
            ])
            ->bulkActions([])
            ->defaultSort('deleted_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
