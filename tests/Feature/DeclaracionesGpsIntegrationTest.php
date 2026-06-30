<?php

namespace Tests\Feature;

use App\Enums\EstadoTerminal;
use App\Models\AbsentHistory;
use App\Models\Customer;
use App\Models\Note;
use App\Models\NoteConfirmation;
use App\Models\NoteNullReason;
use App\Models\NoteSalaEvent;
use App\Models\User;
use App\Models\Venta;
use App\Support\ActionGps;
use App\Support\Filament\GpsActionForm;
use App\Support\NoteSalaActions;
use App\Support\SeguimientoRutaDisplay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Integración local (MySQL/XAMPP): simula declaraciones de comercial y jefe de equipo
 * usando la misma lógica que EditNote / NoteSalaActions / ActionGps.
 *
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Feature/DeclaracionesGpsIntegrationTest.php
 */
class DeclaracionesGpsIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const GPS = ['gps_lat' => '42.240598', 'gps_lng' => '-8.720726'];

    private User $commercial;

    private User $teamLeader;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commercial = User::findOrFail(9);
        $this->teamLeader = User::findOrFail(2);
        $this->gerente = User::findOrFail(11);
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

    private function assertCoord(string $expected, mixed $actual): void
    {
        $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.000001);
    }

    private function login(User $user): void
    {
        Auth::login($user);
    }

    private function declareAusente(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

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
            'observacion' => 'Test integración GPS',
            'autor_id' => $actor->id,
        ]);

        return $note->fresh(['ausencias']);
    }

    private function declareConfirmada(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteConfirmation::create([
            'note_id' => $note->id,
            'author_id' => $actor->id,
            'companion_id' => null,
            'dio_crema' => false,
            'observation' => 'Test GPS confirmada',
        ]);

        $note->estado_terminal = EstadoTerminal::CONFIRMADO;
        $note->lat_dentro = $lat;
        $note->lng_dentro = $lng;
        $note->reten = false;
        $note->save();

        return $note->fresh();
    }

    private function declareNulo(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteNullReason::create([
            'note_id' => $note->id,
            'comercial_id' => $actor->id,
            'companion_id' => null,
            'reason' => 'Test integración nulo GPS',
        ]);

        $note->estado_terminal = EstadoTerminal::NUL;
        $note->reten = false;
        $note->lat = $lat;
        $note->lng = $lng;
        $note->save();

        return $note->fresh();
    }

    private function declareOficina(Note $note, User $actor, array $gps): Note
    {
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve($gps, $actor);

        NoteSalaActions::sendIndividualToOffice(
            $note,
            $actor->id,
            'Test integración oficina GPS',
            $lat,
            $lng,
        );

        return $note->fresh(['salaEvents']);
    }

    private function declareVentaGps(Note $note, User $actor, array $gps): array
    {
        return ActionGps::coordsForVenta($note->lat, $note->lng, $gps, $actor);
    }

    /** @return array<string, array{0: User, 1: string}> */
    public static function rolesWithGpsProvider(): array
    {
        return [
            'comercial' => [9, 'commercial'],
            'jefe_equipo' => [2, 'team_leader'],
        ];
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_politica_gps_y_formulario_para_rol(int $userId, string $roleName): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $this->assertTrue($user->hasRole($roleName));
        $this->assertTrue(ActionGps::shouldRegisterGps($user));
        $this->assertCount(3, GpsActionForm::fields());
        $this->assertCount(3, GpsActionForm::ventaWizardFields());
        $this->assertFalse(GpsActionForm::gpsReadyOnForm([]));
        $this->assertTrue(GpsActionForm::gpsReadyOnForm(self::GPS));
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_ausente_guarda_gps_y_seguimiento_verde(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        $note = $this->declareAusente($note, $user, self::GPS);

        $this->assertSame(EstadoTerminal::AUSENTE->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertCoord(self::GPS['gps_lat'], $note->lat_dentro);
        $this->assertDatabaseHas('historial_ausentes', [
            'note_id' => $note->id,
            'latitud' => self::GPS['gps_lat'],
        ]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'ausente');
        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
        $this->assertNotNull($gps['gps_lng']);
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_confirmada_guarda_gps_y_seguimiento_verde(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        $note = $this->declareConfirmada($note, $user, self::GPS);

        $this->assertSame(EstadoTerminal::CONFIRMADO->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertCoord(self::GPS['gps_lat'], $note->lat_dentro);
        $this->assertDatabaseHas('note_confirmations', ['note_id' => $note->id]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'confirmado');
        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_oficina_individual_guarda_gps_en_evento_sala(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        $note = $this->declareOficina($note, $user, self::GPS);

        $this->assertSame(EstadoTerminal::SALA->value, $note->estado_terminal->value ?? $note->estado_terminal);

        $event = NoteSalaEvent::where('note_id', $note->id)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertCoord(self::GPS['gps_lat'], $event->lat);
        $this->assertCoord(self::GPS['gps_lng'], $event->lng);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note->fresh(['salaEvents']), Carbon::today(), 'sala');
        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_nulo_guarda_gps_en_nota(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        $note = $this->declareNulo($note, $user, self::GPS);

        $this->assertSame(EstadoTerminal::NUL->value, $note->estado_terminal->value ?? $note->estado_terminal);
        $this->assertCoord(self::GPS['gps_lat'], $note->lat);
        $this->assertDatabaseHas('note_null_reasons', ['note_id' => $note->id]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'nulo');
        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_bulk_oficina_guarda_gps_en_todos_los_eventos(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note1 = $this->makeAssignableNote($user);
        $note2 = $this->makeAssignableNote($user);
        $ids = [$note1->id, $note2->id];

        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve(self::GPS, $user);
        $result = NoteSalaActions::sendBulkToOffice($ids, $user->id, $lat, $lng, addMassObservation: false);

        $this->assertSame(2, $result['enviadas']);

        foreach ($ids as $id) {
            $event = NoteSalaEvent::where('note_id', $id)->where('via', 'masivo')->latest('id')->first();
            $this->assertNotNull($event, "Falta evento sala masivo note_id=$id");
            $this->assertCoord(self::GPS['gps_lat'], $event->lat);

            $note = Note::with('salaEvents')->find($id);
            $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'sala');
            $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
        }
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_venta_wizard_gps_se_resuelve_para_crear_contrato(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $note = $this->makeAssignableNote($user);
        $coords = $this->declareVentaGps($note, $user, self::GPS);

        $this->assertCoord(self::GPS['gps_lat'], $coords['lat']);
        $this->assertCoord(self::GPS['gps_lng'], $coords['lng']);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $note->customer_id,
            'comercial_id' => $user->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => now(),
            'importe_total' => 100,
            'importe_comercial' => 100,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'venta_normal',
        ]);

        $note->update(['estado_terminal' => EstadoTerminal::VENTA]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords(
            $note->fresh()->load('venta'),
            Carbon::today(),
            'venta',
        );

        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
        $this->assertDatabaseHas('ventas', ['id' => $venta->id, 'lat' => self::GPS['gps_lat']]);
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_sin_gps_no_puede_confirmar_formulario(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $livewire = new class
        {
            public array $mountedActionsData = [0 => []];
        };

        $this->assertFalse(GpsActionForm::gpsReadyOnLivewire($livewire));

        $livewire->mountedActionsData[0] = self::GPS;
        $this->assertTrue(GpsActionForm::gpsReadyOnLivewire($livewire));
    }

    /** @dataProvider rolesWithGpsProvider */
    public function test_puerta_fria_wizard_gps_y_seguimiento(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->login($user);

        $customer = Customer::factory()->create();
        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'user_id' => $user->id,
            'fuente' => \App\Enums\FuenteNotas::PTA_FRIA->value,
            'estado_terminal' => EstadoTerminal::VENTA,
        ]);

        $coords = ActionGps::coordsForVenta(null, null, self::GPS, $user);

        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $user->id,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'fecha_venta' => now(),
            'importe_total' => 150,
            'importe_comercial' => 150,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'origen_venta' => 'puerta_fria',
        ]);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords(
            $note->fresh()->load('venta'),
            Carbon::today(),
            'venta',
        );

        $this->assertCoord(self::GPS['gps_lat'], $coords['lat']);
        $this->assertCoord(self::GPS['gps_lat'], $gps['gps_lat']);
        $this->assertSame('puerta_fria', $venta->origen_venta->value ?? $venta->origen_venta);
    }

    public function test_gerente_no_registra_gps_en_ninguna_declaracion(): void
    {
        $this->login($this->gerente);

        $this->assertFalse(ActionGps::shouldRegisterGps($this->gerente));
        $this->assertSame([], GpsActionForm::fields());

        $note = $this->makeAssignableNote($this->gerente);
        ['lat' => $lat, 'lng' => $lng] = ActionGps::resolve(self::GPS, $this->gerente);

        $this->assertNull($lat);
        $this->assertNull($lng);

        $note = $this->declareAusente($note, $this->gerente, self::GPS);
        $this->assertNull($note->lat_dentro);
        $this->assertNull($note->fresh()->ausencias->last()?->latitud);

        $gps = SeguimientoRutaDisplay::declaredGpsCoords($note, Carbon::today(), 'ausente');
        $this->assertNull($gps['gps_lat']);
    }
}
