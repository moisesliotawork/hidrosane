<?php

namespace App\Filament\HeadOfRoom\Pages;

use App\Models\Note;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

use App\Filament\HeadOfRoom\Resources\NoteResource;
use Filament\Actions\Action;
use App\Support\TeleoperatorCustomerNoteGuard;
use App\Support\CustomerAddressSearch;
use Filament\Notifications\Notification;

class NotasDireccionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Notas (Dirección)';
    protected static ?string $title = 'Notas (Dirección)';
    protected static ?string $slug = 'notas-direccion';
    protected static ?int $navigationSort = 20;
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.head-of-room.pages.notas-direccion';

    public ?string $phone = null;

    public ?string $createBlockedMessage = null;

    public function mount(): void
    {
        $this->phone = request()->get('phone');

        $digits = TeleoperatorCustomerNoteGuard::normalizePhoneDigits($this->phone);

        if ($digits === null) {
            return;
        }

        $evaluation = app(TeleoperatorCustomerNoteGuard::class)->evaluateForPhone($digits);

        if (! $evaluation->allowed) {
            $this->createBlockedMessage = $evaluation->message;

            Notification::make()
                ->title('NO SE PUEDE LLAMAR')
                ->body($evaluation->message)
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descartar')
                ->label('Descartar')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->url(fn() => NoteResource::getUrl('index')),

            Action::make('seguir')
                ->label('Seguir')
                ->icon('heroicon-o-arrow-right')
                ->color('warning')
                ->disabled(fn () => blank($this->phone) || filled($this->createBlockedMessage))
                ->action(function () {
                    if (filled($this->createBlockedMessage)) {
                        Notification::make()
                            ->title('NO SE PUEDE LLAMAR')
                            ->body($this->createBlockedMessage)
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    return redirect()->to(NoteResource::getUrl('create', [
                        'phone' => $this->phone,
                    ]));
                }),
        ];
    }

    /**
     * SOLO NOTAS NO CANDIDATAS:
     * - Rango: entre 1 y 4 meses atrás (por meses calendario)
     * - Estados terminal: NUL, VENTA, CONFIRMADO
     *
     * Ej: si hoy es Feb-2026:
     * - 1 a 4 meses atrás => Oct-2025, Nov-2025, Dic-2025, Ene-2026
     * - NO incluye Feb-2026 (0 meses) ni Sep-2025 (5 meses)
     */

    protected function getTableQuery(): Builder
    {
        $from     = now()->startOfMonth()->subMonthsNoOverflow(4);
        $todayEnd = now()->endOfDay();
        $tomorrow = now()->addDay()->startOfDay();

        return Note::query()
            ->with(['customer'])
            ->where(function (Builder $q) use ($from, $todayEnd, $tomorrow) {
                // (A) visit_date futura (cualquier estado)
                $q->where('visit_date', '>=', $tomorrow)

                // (B) visit_date en los últimos 4 meses (cualquier estado)
                ->orWhere(function (Builder $h) use ($from, $todayEnd) {
                    $h->whereNotNull('visit_date')
                        ->where('visit_date', '>=', $from)
                        ->where('visit_date', '<=', $todayEnd);
                })

                // (C) assignment_date en los últimos 4 meses (sin importar visit_date ni estado)
                ->orWhere(function (Builder $c) use ($from, $todayEnd) {
                    $c->whereNotNull('assignment_date')
                        ->where('assignment_date', '>=', $from)
                        ->where('assignment_date', '<=', $todayEnd);
                });
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('Nro Nota')
                    ->sortable()
                    ->badge()
                    ->color('pink')
                    ->alignCenter()
                    ->grow(false)
                    ->extraHeaderAttributes(['style' => 'width:5.5rem'])
                    ->extraCellAttributes(['style' => 'width:5.5rem;white-space:nowrap']),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente / Dirección')
                    ->html()
                    ->grow(false)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return CustomerAddressSearch::constrainHasCustomer($query, $search);
                    })
                    ->formatStateUsing(function ($state, Note $record): string {
                        $customer = $record->customer;
                        $name = e((string) ($customer?->name ?: '—'));
                        $address = trim(implode(', ', array_filter([
                            trim((string) ($customer?->primary_address ?? '')),
                            trim((string) ($customer?->nro_piso ?? '')),
                        ], fn ($part) => $part !== '')));
                        $addressHtml = e($address !== '' ? $address : '—');

                        return "<div style=\"line-height:1.1;font-weight:700;color:#1e3a8a;\">{$name}</div>"
                            ."<div style=\"font-weight:700;margin-top:0.05rem;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:22rem;\" title=\"{$addressHtml}\">{$addressHtml}</div>";
                    })
                    ->extraCellAttributes(['style' => 'width:22rem;max-width:22rem;padding-inline:0.35rem;padding-block:0.15rem']),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->grow(false)
                    ->extraHeaderAttributes(['style' => 'width:4.5rem'])
                    ->extraCellAttributes(['style' => 'width:4.5rem;white-space:nowrap;padding-inline:0.25rem']),

                Tables\Columns\TextColumn::make('customer.ciudad')
                    ->label('Ciudad')
                    ->badge()
                    ->color('warning')
                    ->grow(false)
                    ->extraCellAttributes(['style' => 'white-space:nowrap;padding-inline:0.25rem']),

                Tables\Columns\TextColumn::make('customer.provincia')
                    ->label('Provincia')
                    ->badge()
                    ->color('success')
                    ->grow(false)
                    ->extraCellAttributes(['style' => 'white-space:nowrap;padding-inline:0.25rem']),

                Tables\Columns\TextColumn::make('customer.nro_piso')
                    ->label('Nro Piso')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('primary_address')
                    ->label('Dirección')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Dirección primaria')
                            ->placeholder('Calle, número...')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            CustomerAddressSearch::constrainHasCustomer($query, (string) $data['value']);
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('postal_code')
                    ->label('CP')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Código Postal')
                            ->placeholder('Ej: 15551')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('customer', function (Builder $q) use ($data) {
                                $q->where('postal_code', 'like', '%' . $data['value'] . '%');
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('nro_piso')
                    ->label('Nro Piso')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('value')
                            ->label('Nro Piso')
                            ->placeholder('Ej: 3B / 2 / 1-A')
                            ->live(debounce: 300),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('customer', function (Builder $q) use ($data) {
                                $q->where('nro_piso', 'like', '%' . $data['value'] . '%');
                            });
                        }
                        return $query;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100]);
    }
}
