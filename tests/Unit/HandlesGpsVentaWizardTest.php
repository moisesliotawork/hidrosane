<?php

namespace Tests\Unit;

use App\Filament\Commercial\Concerns\HandlesGpsVentaWizard;
use App\Models\User;
use App\Support\ActionGps;
use App\Support\Filament\GpsActionForm;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandlesGpsVentaWizardTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockCommercialUser(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 99;
        $user->empleado_id = '100';
        $user->email = 'comercial@test.com';
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $user->shouldReceive('hasRole')->with('commercial')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        return $user;
    }

    #[Test]
    public function test_set_gps_para_venta_wizard_patches_only_gps_fields(): void
    {
        $this->actingAs($this->mockCommercialUser());

        $component = new class extends Component implements HasForms
        {
            use HandlesGpsVentaWizard;
            use InteractsWithForms;

            public ?array $data = [];

            public function mount(): void
            {
                $this->form->fill([
                    'gps_lat' => null,
                    'gps_lng' => null,
                    'precontractual' => 'ventas/existing.png',
                    'foto_sorteo' => 'ventas/sorteo.png',
                ]);
            }

            public function form(Form $form): Form
            {
                return $form
                    ->schema([
                        ...GpsActionForm::ventaWizardFields(),
                        TextInput::make('precontractual'),
                        TextInput::make('foto_sorteo'),
                    ])
                    ->statePath('data');
            }
        };

        $component->mount();
        $component->setGpsParaVentaWizard('40.1234', '-3.5678');

        $state = $component->form->getRawState();

        $this->assertTrue(ActionGps::shouldRegisterGps());
        $this->assertSame('40.1234', $state['gps_lat'] ?? null);
        $this->assertSame('-3.5678', $state['gps_lng'] ?? null);
        $this->assertSame('ventas/existing.png', $state['precontractual'] ?? null);
        $this->assertSame('ventas/sorteo.png', $state['foto_sorteo'] ?? null);
    }

    #[Test]
    public function test_set_gps_para_venta_wizard_is_noop_when_gps_exempt(): void
    {
        $exempt = Mockery::mock(User::class)->makePartial();
        $exempt->id = 38;
        $exempt->empleado_id = '911';
        $exempt->email = 'contratos@gmail.com';
        $exempt->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $exempt->shouldReceive('hasRole')->with('commercial')->andReturn(true);
        $exempt->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        $this->actingAs($exempt);

        $component = new class extends Component implements HasForms
        {
            use HandlesGpsVentaWizard;
            use InteractsWithForms;

            public ?array $data = [];

            public function mount(): void
            {
                $this->form->fill([
                    'gps_lat' => null,
                    'gps_lng' => null,
                ]);
            }

            public function form(Form $form): Form
            {
                return $form
                    ->schema(GpsActionForm::ventaWizardFields())
                    ->statePath('data');
            }
        };

        $component->mount();
        $component->setGpsParaVentaWizard('40.1234', '-3.5678');

        $state = $component->form->getRawState();

        $this->assertFalse(ActionGps::shouldRegisterGps());
        $this->assertNull($state['gps_lat'] ?? null);
        $this->assertNull($state['gps_lng'] ?? null);
    }
}
