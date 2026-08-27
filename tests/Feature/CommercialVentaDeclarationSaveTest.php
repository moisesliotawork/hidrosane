<?php

namespace Tests\Feature;

use App\Enums\EstadoTerminal;
use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Enums\OrigenVenta;
use App\Filament\Commercial\Resources\VentaResource;
use App\Filament\Commercial\Resources\VentaResource\Pages\CreateVenta;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use App\Support\ActionGps;
use App\Support\NoteVentaDeclarationGuard;
use App\Support\VentaFechaVenta;
use App\Support\VentaOrigenResolver;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprueba que un comercial puede grabar venta normal (desde nota) y Puerta Fría.
 *
 * Ejecutar:
 * DB_CONNECTION=mysql DB_DATABASE=ohanaplus DB_USERNAME=root DB_PASSWORD= php artisan test tests/Feature/CommercialVentaDeclarationSaveTest.php
 */
class CommercialVentaDeclarationSaveTest extends TestCase
{
    use DatabaseTransactions;

    private const GPS = ['gps_lat' => '42.240598', 'gps_lng' => '-8.720726'];

    private function commercial(): User
    {
        Role::findOrCreate('commercial');

        $user = User::factory()->create([
            'name' => 'TestCom',
            'last_name' => 'Declaracion',
            'email' => 'com-decl-'.Str::random(8).'@test.local',
            'empleado_id' => (string) random_int(200, 899),
        ]);
        $user->assignRole('commercial');

        return $user->fresh();
    }

    public function test_formulario_comercial_no_exige_note_id_oculto(): void
    {
        $hidden = $this->findNamedComponent(VentaResource::step1Schema(), 'note_id');

        $this->assertInstanceOf(Hidden::class, $hidden);

        $src = file_get_contents(app_path('Filament/Commercial/Resources/VentaResource.php'));
        $this->assertMatchesRegularExpression("/Hidden::make\\('note_id'\\)\\s*->dehydrated\\(\\)\\s*->nullable\\(\\)/", $src);
        $this->assertStringNotContainsString("Hidden::make('note_id')->required()", $src);
    }

    public function test_create_venta_inyecta_note_id_desde_la_url(): void
    {
        $page = new CreateVenta;
        $page->noteId = 14604;

        $method = new ReflectionMethod(CreateVenta::class, 'mutateFormDataBeforeCreate');
        $injected = $method->invoke($page, ['note_id' => null, 'importe_total' => 100]);

        $this->assertSame(14604, $injected['note_id']);
    }

    public function test_comercial_graba_venta_normal_y_puerta_fria(): void
    {
        $actor = $this->commercial();
        $this->actingAs($actor);

        $this->assertTrue(ActionGps::shouldRegisterGps($actor));

        $normal = $this->declareVentaNormal($actor);
        $fria = $this->declarePuertaFria($actor);

        $this->assertNotSame($normal['venta']->id, $fria['venta']->id);
        $this->assertNotSame('01000', $normal['venta']->nro_contr_adm);
        $this->assertNotSame('01000', $fria['venta']->nro_contr_adm);
        $this->assertNotSame($normal['venta']->nro_contr_adm, $fria['venta']->nro_contr_adm);

        $this->assertNotNull(NoteVentaDeclarationGuard::blockReasonForStartingVentaFromNote($normal['note']));
    }

    /** @return array{note: Note, venta: Venta} */
    private function declareVentaNormal(User $actor): array
    {
        $customer = Customer::factory()->create([
            'first_names' => 'Ana',
            'last_names' => 'Garcia Lopez',
        ]);

        $note = Note::factory()->create([
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'user_id' => $actor->id,
            'fuente' => FuenteNotas::CALLE->value,
            'estado_terminal' => null,
            'reten' => false,
            'lat' => null,
            'lng' => null,
        ]);

        $this->assertNull(NoteVentaDeclarationGuard::blockReasonForStartingVentaFromNote($note));

        ['lat' => $lat, 'lng' => $lng] = ActionGps::assertCoordsForVentaOrFail(
            null,
            null,
            self::GPS,
            $actor,
        );

        $note->update(['lat' => $lat, 'lng' => $lng]);

        $importe = 1899;
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'lat' => $lat,
            'lng' => $lng,
            'fecha_venta' => VentaFechaVenta::normalizeOnCreate(),
            'importe_total' => $importe,
            'importe_comercial' => $importe,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 12,
            'cuota_mensual' => round($importe / 12, 2),
            'origen_venta' => OrigenVenta::VENTA_NORMAL,
        ]);

        $note->update([
            'estado_terminal' => EstadoTerminal::VENTA,
            'reten' => false,
            'comercial_id' => $actor->id,
        ]);
        VentaOrigenResolver::repairMislabeledFuente($note->fresh());

        $venta = $venta->fresh();
        $note = $note->fresh()->load('venta');

        $this->assertSame($note->id, $venta->note_id);
        $this->assertSame(OrigenVenta::VENTA_NORMAL, $venta->origen_venta);
        $this->assertSame(EstadoTerminal::VENTA, $note->estado_terminal);
        $this->assertNotEmpty($venta->nro_contr_adm);
        $this->assertEqualsWithDelta((float) self::GPS['gps_lat'], (float) $venta->lat, 0.000001);
        $this->assertSame($venta->id, $note->venta?->id);

        return ['note' => $note, 'venta' => $venta];
    }

    /** @return array{note: Note, venta: Venta} */
    private function declarePuertaFria(User $actor): array
    {
        $customer = Customer::factory()->create([
            'first_names' => 'Luis',
            'last_names' => 'Perez Diaz',
            'phone' => '6'.str_pad((string) random_int(10000000, 99999999), 8, '0'),
        ]);

        ['lat' => $lat, 'lng' => $lng] = ActionGps::assertCoordsForVentaOrFail(
            null,
            null,
            self::GPS,
            $actor,
        );

        $fechaVenta = VentaFechaVenta::normalizeOnCreate();
        $note = Note::factory()->create([
            'user_id' => $actor->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'status' => NoteStatus::CONTACTED->value,
            'estado_terminal' => EstadoTerminal::VENTA,
            'fuente' => FuenteNotas::PTA_FRIA->value,
            'reten' => false,
            'lat' => $lat,
            'lng' => $lng,
            'created_at' => $fechaVenta,
            'updated_at' => $fechaVenta,
        ]);

        $importe = 2499;
        $venta = Venta::create([
            'note_id' => $note->id,
            'customer_id' => $customer->id,
            'comercial_id' => $actor->id,
            'lat' => $lat,
            'lng' => $lng,
            'fecha_venta' => $fechaVenta,
            'importe_total' => $importe,
            'importe_comercial' => $importe,
            'modalidad_pago' => 'Financiado',
            'num_cuotas' => 24,
            'cuota_mensual' => round($importe / 24, 2),
            'origen_venta' => OrigenVenta::PUERTA_FRIA,
        ]);

        $venta = $venta->fresh();
        $note = $note->fresh()->load('venta');

        $this->assertSame($note->id, $venta->note_id);
        $this->assertSame(OrigenVenta::PUERTA_FRIA, $venta->origen_venta);
        $this->assertSame(FuenteNotas::PTA_FRIA, $note->fuente);
        $this->assertNotEmpty($venta->nro_contr_adm);
        $this->assertEqualsWithDelta((float) self::GPS['gps_lat'], (float) $venta->lat, 0.000001);

        return ['note' => $note, 'venta' => $venta];
    }

    /** @param  array<int, Component>  $components */
    private function findNamedComponent(array $components, string $name): ?Component
    {
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            if (method_exists($component, 'getName') && $component->getName() === $name) {
                return $component;
            }

            if (method_exists($component, 'getChildComponents')) {
                $found = $this->findNamedComponent($component->getChildComponents(), $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
