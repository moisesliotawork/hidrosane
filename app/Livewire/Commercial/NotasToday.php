<?php

namespace App\Livewire\Commercial;

use Livewire\Component;
use App\Models\Note;
use Filament\Notifications\Notification;
use App\Models\AnotacionVisita;
use App\Filament\Commercial\Resources\NoteResource;
use Carbon\Carbon;

class NotasToday extends Component
{

    public array $selectedNotes = [];

    protected $listeners = [
        'notaActualizada' => '$refresh',
        'guardarUbicacion' => 'guardarUbicacion',
        'guardarUbicacionDentro' => 'guardarUbicacionDentro',
        'avisarSinDentro' => 'avisarSinDentro',
    ];

    public function canAlwaysSeePhones(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['team_leader', 'sales_manager']);
    }


    public function avisarSinDentro($notaId): void
    {
        Notification::make()
            ->title('Sin ubicación en GPS')
            ->body("La nota #{$notaId} no tiene coordenadas de GPS guardadas.")
            ->danger()
            ->send();
    }

    public function guardarUbicacionDentro($notaId, $lat, $lng)
    {
        $note = Note::find($notaId);
        if (!$note) {
            return;
        }

        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'DENTRO',
            'cuerpo' => "Ubicación DENTRO: Latitud $lat, Longitud $lng",
        ]);

        Notification::make()
            ->title('Ubicación DENTRO capturada')
            ->success()
            ->body("Guardada para nota #$notaId: [$lat, $lng]")
            ->send();

        $this->dispatch('notaActualizada');
    }


    public ?float $ubicacionLat = null;
    public ?float $ubicacionLng = null;

    public function guardarUbicacion($notaId, $lat, $lng)
    {
        $note = Note::find($notaId);
        $note->lat = $lat;
        $note->lng = $lng;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $notaId,
            'author_id' => auth()->id(),
            'asunto' => 'GPS',
            'cuerpo' => "Ubicación capturada: Latitud $lat, Longitud $lng"
        ]);


        Notification::make()
            ->title('Ubicación capturada')
            ->success()
            ->body("Ubicación guardada para la nota #$notaId: [$lat, $lng]")
            ->send();

        // Aquí puedes opcionalmente guardar en BD si quieres
        // $note = Note::find($notaId);
        // $note->lat = $lat;
        // $note->lng = $lng;
        // $note->save();
    }

    public function toggleDeCamino($noteId)
    {
        $note = Note::find($noteId);

        if (!$note || $note->comercial_id !== auth()->id()) {
            Notification::make()
                ->title('Acceso denegado')
                ->danger()
                ->body('No tienes permiso para modificar esta nota.')
                ->send();

            return;
        }

        $nuevoEstado = !$note->de_camino;
        $note->de_camino = $nuevoEstado;
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $noteId,
            'author_id' => auth()->id(),
            'asunto' => 'DE CAMINO',
            'cuerpo' => $nuevoEstado ? "Va de camino" : "No va de camino"
        ]);

        if (!$nuevoEstado) {
            Notification::make()
                ->title('Estado actualizado')
                ->warning()
                ->body('La nota ha sido marcada como NO EN CAMINO')
                ->send();
        } else {
            Notification::make()
                ->title('Estado actualizado')
                ->success()
                ->body('La nota ha sido marcada como EN CAMINO')
                ->send();
        }

        $this->dispatch('notaActualizada');
    }

    public function redirigirAVenta(int $noteId)
    {

        $url = NoteResource::getUrl(
            'edit',
            ['record' => $noteId],
            panel: 'comercial'
        );

        return redirect()->to($url);
    }



    public function getNotesProperty()
    {
        return Note::with(['customer', 'comercial'])
            ->where('comercial_id', auth()->id())
            ->whereDate('assignment_date', today())
            ->where('reten', false)
            ->where(function ($q) {
                $q->whereNull('estado_terminal')
                    ->orWhere('estado_terminal', '')
                    ->orWhere('estado_terminal', 'ausente');
            })
            ->whereDoesntHave('venta')
            ->latest('assignment_date')
            ->get()
            ->map(function ($note) {

                $customer = $note->customer;

                // ========== MISMA LÓGICA DEL PDF ==========
    
                $primary = trim((string) ($customer->primary_address ?? ''));
                $nroPiso = trim((string) ($customer->nro_piso ?? ''));
                $postalCode = trim((string) ($customer->postal_code ?? ''));
                $city = trim((string) ($customer->ciudad ?? ''));
                $province = trim((string) ($customer->provincia ?? ''));
                $ayto = trim((string) ($customer->ayuntamiento ?? ''));

                $cpCity = trim(implode(' ', array_filter([$postalCode, $city])));

                // FIX letra huérfana tras CP (igual que en el PDF)
                $cpCity = preg_replace('/^(\d{4,5})\s+[A-ZÁÉÍÓÚÑ]\b\s+/u', '$1 ', $cpCity);

                $provinceFormatted = $province ? "($province)" : null;

                // Línea 1: solo dirección
                $dirL1 = $primary;

                // Línea 2: piso → CP+Ciudad → ayto
                $dirL2Parts = [];
                if ($nroPiso !== '') {
                    $dirL2Parts[] = $nroPiso;
                }
                if ($cpCity !== '') {
                    $dirL2Parts[] = $cpCity;
                }
                if ($ayto !== '') {
                    $dirL2Parts[] = $ayto;
                }

                $dirL2 = implode(' - ', $dirL2Parts);

                if ($provinceFormatted) {
                    $dirL2 = trim($dirL2 . ' ' . $provinceFormatted);
                }

                // TitleCase como en el PDF
                $toTitleCase = function (?string $text): string {
                    $t = trim((string) $text);
                    if ($t === '') {
                        return '';
                    }
                    $t = mb_strtolower($t, 'UTF-8');
                    return mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
                };

                $dirL1 = $toTitleCase($dirL1);
                $dirL2 = $toTitleCase($dirL2);

                // Dirección 1: una sola línea (igual que $dirOneLine en el PDF)
                $dirOneLine = trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        trim($dirL1 . ($dirL2 ? ' - ' . $dirL2 : ''))
                    ),
                    ' -'
                );

                $fullAddress = $dirOneLine !== '' ? $dirOneLine : 'Sin dirección';

                // Si aún quieres este info “simple”
                $postalCodeSimple = $customer->postal_code ?? null;
                $citySimple = $customer->ciudad ?? null;
                $addressInfo = $postalCodeSimple && $citySimple
                    ? "$postalCodeSimple, $citySimple"
                    : ($postalCodeSimple ?? $citySimple ?? 'Sin ubicación');

                // ==========================================
    
                return [
                    'id' => $note->id,
                    'nro_nota' => $note->nro_nota,
                    'customer' => $customer->name ?? 'Sin cliente',

                    // 👉 Dirección formateada igual que en el PDF
                    'full_address' => $fullAddress,

                    // Por si los sigues usando en otros lados
                    'primary_address' => $customer->primary_address ?? 'Sin dirección',
                    'address_info' => $addressInfo,

                    'comercial' => $note->comercial->empleado_id ?? 'Sin asignar',
                    'visit_date' => \Carbon\Carbon::parse($note->visit_date)->format('d/m/Y'),
                    'visit_schedule' => $note->visit_schedule ?? '--:--',
                    'observations' => $note->observations,
                    'fuente' => $note->fuente->value,
                    'fuente_label' => $note->fuente->getLabel(),
                    'fuente_puntaje' => $note->fuente->getPuntaje(),
                    'de_camino' => $note->de_camino,
                    'show_phone' => $note->show_phone,
                    'phone' => $customer->phone ?? null,
                    'secondary_phone' => $customer->secondary_phone ?? null,
                    'lat_dentro' => $note->lat_dentro,
                    'lng' => $note->lng,
                    'lat' => $note->lat,
                    'lng_dentro' => $note->lng_dentro,
                ];
            });
    }

    public function render()
    {
        return view('livewire.commercial.notas-today');
    }
}
