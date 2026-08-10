<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers;

use App\Models\VentaPdfDownload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tabla "PDF DESCARGADOS": auditoría de copias de PDF archivadas en segundo plano
 * cada vez que se descarga el contrato (desde Admin o SuperAdmin). Solo se
 * registra en el recurso Contratos de SuperAdmin; Admin no ve ni sabe de esto.
 */
class PdfDescargasRelationManager extends RelationManager
{
    protected static string $relationship = 'pdfDownloads';

    protected static ?string $title = 'PDF Descargados';

    protected static ?string $modelLabel = 'PDF descargado';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
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
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VentaPdfDownload $record) => route('pdf-descargas.ver', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
