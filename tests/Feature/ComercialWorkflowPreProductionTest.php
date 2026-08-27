<?php

namespace Tests\Feature;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Models\AbsentHistory;
use App\Models\AnotacionVisita;
use App\Models\Customer;
use App\Models\Note;
use App\Models\NoteConfirmation;
use App\Models\NoteNullReason;
use App\Models\NoteSalaEvent;
use App\Models\User;
use App\Models\Venta;
use App\Support\ActionGps;
use App\Support\NoteSalaActions;
use App\Support\SeguimientoRutaDisplay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Checklist pre-producción: flujo comercial + GPS para comercial y jefe de equipo.
 *
 * Cubre: DENTRO, Confirmada, Envío a oficina, Venta, Nulo.
 *
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Feature/ComercialWorkflowPreProductionTest.php
 */
class ComercialWorkflowPreProductionTest extends TestCase
{
    use DatabaseTransactions;

    private const GPS_GALICIA = ['gps_lat' => '42.240598', 'gps_lng' => '-8.720726'];

    private const GPS_MADRID = ['gps_lat' => '40.416800', 'gps_lng' => '-3.703800'];

    /** @return array<string, array{0: int, 1: string}> */
    public static function rolesWithGpsProvider(): array
    {
        return [
            'comercial' => [9, 'commercial'],
            'jefe_equipo' => [2, 'team_leader'],
        ];
    }

    private function login(User $user): void
    {
        Auth::login($user);
    }

    private function assertCoord(string $expected, mixed $actual): void
    {
        $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.000001);
    }

    private function makeAssignableNote(User $owner): Note
    {
        $customer = Customer::factory()->create();

        return Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $owner->id,
            'user_id' => $owner->id,
            'estado_terminal' => null,
            'reten' => false,
        ]);
    }

    /** Simula guardarUbicacionDentro (Livewire) con validación GPS. */
    private function declareDentro(Note $note, User $actor, array $gps): Note
    {
        $coords = ActionGps::validateOperatingCoords($gps['gps_lat'], $gps['gps_lng']);
        $this->assertNotNull($coords, 'El GPS debe ser válido dentro de España.');

        $note->lat_dentro = $coords['lat'];
        $note->lng_dentro = $coords['lng'];
        $note->save();

        AnotacionVisita::create([
            'nota_id' => $note->id,
            'author_id' => $actor->id,
            'asunto' => 'DENTRO',
            'cuerpo' => "Ubicación DENTRO: Latitud {$coords['lat']}, Longitud {$coords['lng']}",
        ]);

        return $note->fresh();
    }

    private function declareConfirmada(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteConfirmation::create([
            'note_id' => $note->id,
            'author_id' => $actor->id,
            'companion_id' => null,
            'dio_crema' => false,
            'observation' => 'Pre-producción: confirmada',
        ]);

        $note->estado_terminal = EstadoTerminal::CONFIRMADO;
        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->reten = false;
        $note->fecha_declaracion = now();
        $note->save();

        return $note->fresh();
    }

    private function declareOficina(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteSalaActions::sendIndividualToOffice(
            $note,
            $actor->id,
            'Pre-producción: envío a oficina',
            $lat,
            $lng,
        );

        return $note->fresh(['salaEvents']);
    }

    private function declareNulo(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteNullReason::create([
            'note_id' => $note->id,
            'comercial_id' => $actor->id,
            'companion_id' => null,
            'reason' => 'Pre-producción: nulo',
        ]);

        $note->estado_terminal = EstadoTerminal::NUL;
        $note->reten = false;
        $note->lat = $lat;
        $note->lng = $lng;
        $note->fecha_declaracion = now();
        $note->save();

        return $note->fresh();
    }

    private function declareVenta(Note $note, User $actor, array $gps): Venta
    {
        $coords = ActionGps::coordsForVenta($note->lat, $note->lng, $gps, $actor);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $note->customer_id,
            'comercial_id' => $actor->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => now(),
            'importe_total' => 250,
            'importe_comercial' => 250,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'venta_normal',
        ]);

        $note->update([
            'estado_terminal' => EstadoTerminal::VENTA,
            'fecha_declaracion' => now(),
            'reten' => false,
        ]);

        return $venta;
    }

    private function assertNoteStillAssignedTo(Note $note, User $owner): void
    {
        $fresh = $note->fresh();
        $this->assertSame($owner->id, $fresh->comercial_id);
        $this->assertFalse((bool) $fresh->reten);
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_flujo_comercial_integral_pre_produccion(int $userId, string $roleName): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $this->assertTrue($user->hasRole($roleName));
        $this->assertTrue(ActionGps::shouldRegisterGps($user));

        $today = Carbon::today();

        // 1) DENTRO — marca ubicación sin cambiar estado terminal
        $noteDentro = $this->makeAssignableNote($user);
        $noteDentro = $this->declareDentro($noteDentro, $user, self::GPS_GALICIA);

        $this->assertTrue(in_array($noteDentro->estado_terminal, [null, EstadoTerminal::SIN_ESTADO], true)
            || ($noteDentro->estado_terminal?->value ?? '') === '');
        $this->assertCoord(self::GPS_GALICIA['gps_lat'], $noteDentro->lat_dentro);
        $this->assertCoord(self::GPS_GALICIA['gps_lng'], $noteDentro->lng_dentro);
        $this->assertDatabaseHas('anotaciones_visitas', [
            'nota_id' => $noteDentro->id,
            'asunto' => 'DENTRO',
        ]);
        $this->assertNoteStillAssignedTo($noteDentro, $user);

        // 2) CONFIRMADA — estado terminal + GPS en lat_dentro
        $noteConfirmada = $this->makeAssignableNote($user);
        $noteConfirmada = $this->declareConfirmada($noteConfirmada, $user, self::GPS_GALICIA);

        $this->assertSame(EstadoTerminal::CONFIRMADO->value, $noteConfirmada->estado_terminal->value ?? $noteConfirmada->estado_terminal);
        $this->assertDatabaseHas('note_confirmations', ['note_id' => $noteConfirmada->id]);
        $this->assertNotNull($noteConfirmada->fecha_declaracion);
        $gpsConfirmada = SeguimientoRutaDisplay::declaredGpsCoords($noteConfirmada, $today, 'confirmado');
        $this->assertCoord(self::GPS_GALICIA['gps_lat'], $gpsConfirmada['gps_lat']);
        $this->assertNoteStillAssignedTo($noteConfirmada, $user);

        // 3) ENVÍO A OFICINA — estado SALA + evento con GPS
        $noteOficina = $this->makeAssignableNote($user);
        $noteOficina = $this->declareOficina($noteOficina, $user, self::GPS_MADRID);

        $this->assertSame(EstadoTerminal::SALA->value, $noteOficina->estado_terminal->value ?? $noteOficina->estado_terminal);
        $this->assertNotNull($noteOficina->sent_to_sala_at);
        $eventoOficina = NoteSalaEvent::where('note_id', $noteOficina->id)->latest('id')->first();
        $this->assertNotNull($eventoOficina);
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $eventoOficina->lat);
        $this->assertCoord(self::GPS_MADRID['gps_lng'], $eventoOficina->lng);
        $gpsOficina = SeguimientoRutaDisplay::declaredGpsCoords($noteOficina, $today, 'sala');
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $gpsOficina['gps_lat']);
        $this->assertNoteStillAssignedTo($noteOficina, $user);

        // 4) NULO — estado NUL + GPS en lat/lng de la nota
        $noteNulo = $this->makeAssignableNote($user);
        $noteNulo = $this->declareNulo($noteNulo, $user, self::GPS_MADRID);

        $this->assertSame(EstadoTerminal::NUL->value, $noteNulo->estado_terminal->value ?? $noteNulo->estado_terminal);
        $this->assertDatabaseHas('note_null_reasons', ['note_id' => $noteNulo->id]);
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $noteNulo->lat);
        $gpsNulo = SeguimientoRutaDisplay::declaredGpsCoords($noteNulo, $today, 'nulo');
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $gpsNulo['gps_lat']);
        $this->assertNoteStillAssignedTo($noteNulo, $user);

        // 5) VENTA — contrato con GPS y nota en estado VENTA
        $noteVenta = $this->makeAssignableNote($user);
        $venta = $this->declareVenta($noteVenta, $user, self::GPS_GALICIA);

        $noteVenta = $noteVenta->fresh()->load('venta');
        $this->assertSame(EstadoTerminal::VENTA->value, $noteVenta->estado_terminal->value ?? $noteVenta->estado_terminal);
        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'note_id' => $noteVenta->id,
            'comercial_id' => $user->id,
            'lat' => self::GPS_GALICIA['gps_lat'],
        ]);
        $gpsVenta = SeguimientoRutaDisplay::declaredGpsCoords($noteVenta, $today, 'venta');
        $this->assertCoord(self::GPS_GALICIA['gps_lat'], $gpsVenta['gps_lat']);
        $this->assertNoteStillAssignedTo($noteVenta, $user);
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_envio_masivo_a_oficina_mantiene_flujo(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note1 = $this->makeAssignableNote($user);
        $note2 = $this->makeAssignableNote($user);
        $ids = [$note1->id, $note2->id];

        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve(self::GPS_GALICIA, $user);
        $result = NoteSalaActions::sendBulkToOffice($ids, $user->id, $lat, $lng, addMassObservation: false);

        $this->assertSame(2, $result['enviadas']);

        foreach ($ids as $id) {
            $note = Note::find($id);
            $this->assertSame(EstadoTerminal::SALA->value, $note->estado_terminal->value ?? $note->estado_terminal);
            $this->assertFalse((bool) $note->reten);
            $this->assertSame($user->id, $note->comercial_id);

            $event = NoteSalaEvent::where('note_id', $id)->where('via', 'masivo')->latest('id')->first();
            $this->assertNotNull($event);
            $this->assertCoord(self::GPS_GALICIA['gps_lat'], $event->lat);
        }
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_dentro_rechaza_gps_fuera_de_espana(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $this->assertNull(ActionGps::validateOperatingCoords('10.4806', '-66.9036'));
        $this->assertNull(ActionGps::validateOperatingCoords('48.8566', '2.3522'));
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_ausente_sigue_flujo_comercial_con_gps(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve(self::GPS_GALICIA, $user);

        $note->estado_terminal = EstadoTerminal::AUSENTE;
        $note->reten = false;
        $note->fecha_declaracion = now();
        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->save();

        AbsentHistory::create([
            'note_id' => $note->id,
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i:s'),
            'latitud' => $lat,
            'longitud' => $lng,
            'observacion' => 'Pre-producción ausente',
            'autor_id' => $user->id,
        ]);

        $note = $note->fresh(['ausencias']);
        $this->assertSame(EstadoTerminal::AUSENTE->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertDatabaseHas('historial_ausentes', ['note_id' => $note->id, 'latitud' => $lat]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'ausente');
        $this->assertCoord(self::GPS_GALICIA['gps_lat'], $gps['gps_lat']);
        $this->assertNoteStillAssignedTo($note, $user);
    }

    /**
     * @dataProvider rolesWithGpsProvider
     */
    public function test_puerta_fria_venta_mantiene_flujo(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'user_id' => $user->id,
            'fuente' => FuenteNotas::PTA_FRIA->value,
            'estado_terminal' => EstadoTerminal::VENTA,
            'reten' => false,
        ]);

        $coords = ActionGps::coordsForVenta(null, null, self::GPS_MADRID, $user);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => now(),
            'importe_total' => 180,
            'importe_comercial' => 180,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'puerta_fria',
        ]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note->fresh()->load('venta'), Carbon::today(), 'venta');
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $coords['lat']);
        $this->assertCoord(self::GPS_MADRID['gps_lat'], $gps['gps_lat']);
        $this->assertSame('puerta_fria', $venta->origen_venta->value ?? $venta->origen_venta);
        $this->assertSame($user->id, $note->fresh()->comercial_id);
    }
}
