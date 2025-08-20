<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\HistoricoContratosResource\Pages;
use App\Models\Venta;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\EstadoEntrega;      // por si quieres formatear labels de entrega
use function Filament\Facades\filament;

class HistoricoContratosResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Histórico de Contratos';
    protected static ?string $pluralModelLabel = 'Histórico de Contratos';
    protected static ?string $slug = 'historico-contratos';

    /** Solo lectura */
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return Venta::query()
                    ->with(['note.customer.postalCode.city', 'comercial', 'reparto'])
                    ->where('comercial_id', auth()->id())
                    ->latest('id');
            })
            ->columns([
                // ID / Nº de Nota
                TextColumn::make('note.nro_nota')
                    ->label('ID Nota')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? "Nota {$state}" : '-')
                    ->searchable(),

                // Fecha de declaración
                TextColumn::make('fecha_venta')
                    ->label('Fecha declaración')
                    ->dateTime('Y-m-d H:i'),

                // Cliente (nombre completo)
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->state(function (Venta $record) {
                        $c = $record->note?->customer;
                        return $c?->full_name ?? trim(($c->first_names ?? '') . ' ' . ($c->last_names ?? ''));
                    })
                    ->searchable(),

                // Estado venta (enum con label/color)
                TextColumn::make('estado_venta')
                    ->label('Estado venta')
                    ->badge()
                    ->formatStateUsing(fn(Venta $r) => $r->estado_venta?->label() ?? '')
                    ->color(fn(Venta $r) => $r->estado_venta?->color()),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('docs')
                    ->label('+DOCS')
                    ->icon('heroicon-o-document-plus')
                    ->url(function (Venta $record) {
                        
                        $panelId = 'comercial';

                        return static::getUrl(
                            'docs',
                            ['record' => $record],
                            panel: $panelId   // ⚠️ aquí debe ser 'comercial' (no 'commercial')
                        );
                    })
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistoricoContratos::route('/'),
            'docs' => Pages\GestionDocumentos::route('/{record}/docs'),
        ];
    }
}
