<?php

namespace App\Filament\Commercial\Pages;

use App\Models\User;
use App\Models\Team;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class ComercialesVerNotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Reasignar Visitas';
    protected static ?string $title = 'Reasignar Visitas';
    protected static ?string $slug = 'comerciales-ver-notas';
    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.commercial.pages.comerciales-ver-notas';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['team_leader', 'sales_manager']);
    }

    // Bloquear acceso directo por URL a otros roles
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['team_leader', 'sales_manager']);
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['team_leader', 'sales_manager']), 403);
    }

    /* ====== Tabla ====== */

    // App\Filament\Commercial\Pages\ComercialesVerNotas.php

    public function table(Table $table): Table
    {
        $user = auth()->user();

        // ✅ Team leader y Sales manager ven a TODOS
        if ($user->hasAnyRole(['team_leader', 'sales_manager']) || in_array($user->id, [17, 18], true)) {

            $query = User::query()
                ->role(['commercial', 'team_leader', 'sales_manager'])
                ->whereNull('baja')
                ->where('id', '!=', 991);

        } else {
            abort(403);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn($record) => trim($record->name . ' ' . ($record->last_name ?? '')))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_notas')
                    ->label('Ver notas')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    ->url(fn($record) => \App\Filament\Commercial\Pages\NotasDeComercial::getUrl(
                        ['comercial_id' => $record->id],
                        panel: 'comercial'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->headerActions([
                Tables\Actions\Action::make('ver_reten')
                    ->label('RETEN')
                    ->button()
                    ->color('orange')
                    ->icon('heroicon-o-document-text')
                    ->url(fn() => \App\Filament\Commercial\Pages\NotasDeComercial::getUrl(
                        ['comercial_id' => 'reten'],
                        panel: 'comercial'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }

}
