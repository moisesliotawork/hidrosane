<?php

namespace App\Filament\Admin\Resources\HojaRutaResource\Pages;

use App\Filament\Admin\Resources\HojaRutaResource;
use App\Models\AnotacionVisita;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Carbon\CarbonInterval;
use Filament\Tables\Concerns\InteractsWithTable;

class HojaRuta extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = HojaRutaResource::class;
    protected static string $view = 'filament.admin.comerciales.hoja-ruta';

    public int $total = 0;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // total para el encabezado
        $this->total = AnotacionVisita::whereHas(
            'nota',
            fn($q) => $q->where('comercial_id', $this->record->id)
        )->count();
    }

    public function getTitle(): string
    {
        return "Hoja de Ruta - {$this->record->empleado_id} {$this->record->name} {$this->record->last_name}";
    }

    public function table(Table $table): Table
    {
        // ⚠️ Requiere MySQL 8+ (LAG / WINDOW)
        $query = AnotacionVisita::query()
            ->with(['nota:id,nro_nota', 'autor:id,name,last_name'])
            ->whereHas('nota', fn($q) => $q->where('comercial_id', $this->record->id))
            ->selectRaw("
                anotaciones_visitas.*,
                TIMESTAMPDIFF(
                    SECOND,
                    LAG(created_at) OVER (ORDER BY created_at),
                    created_at
                ) AS delta_seconds
            ")
            ->orderBy('created_at');

        return $table
            ->query($query)
            ->defaultSort('created_at')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('nota.nro_nota')
                    ->label('Nota')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state ? "#{$state}" : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('autor_name')
                    ->label('Autor')
                    ->state(fn($record) => trim(($record->autor->name ?? '') . ' ' . ($record->autor->last_name ?? '')))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('asunto')
                    ->label('Asunto')
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cuerpo')
                    ->label('Cuerpo')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn($record) => $record->cuerpo)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delta_seconds')
                    ->label('Δ desde anterior')
                    ->alignRight()
                    ->extraAttributes(['class' => 'font-mono tabular-nums'])
                    ->formatStateUsing(function ($state) {
                        if ($state === null)
                            return '—';
                        // humano corto: ej. "1h 12m 3s"
                        return CarbonInterval::seconds((int) $state)->cascade()->forHumans(short: true, parts: 3);
                    })
                    ->toggleable(),
            ])
            ->paginated([25, 50, 100])   // puedes dejar un valor fijo con ->paginated(false) para sin paginar
            ->striped()
            ->emptyStateHeading('No hay anotaciones de visita para este comercial.');
    }
}
