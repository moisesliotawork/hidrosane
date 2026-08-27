<?php

namespace Database\Seeders;

use App\Enums\EstadoVenta;
use App\Enums\FuenteNotas;
use App\Enums\NoteStatus;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;

class PosGlobalCardsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $comercialId = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
            ->value('id') ?? User::query()->value('id');

        $userId = User::query()->value('id');

        if (! $comercialId || ! $userId) {
            $this->command?->warn('PosGlobalCardsDemoSeeder: no hay usuarios en BD.');

            return;
        }

        $demos = [
            [
                'dni' => 'DEMOPGC001',
                'first_names' => 'DEMO',
                'last_names' => 'CARD UNO',
                'phone' => '612111001',
                'secondary_phone' => '698111001',
                'phone1_commercial' => '655111001',
                'primary_address' => 'Calle Mayor 12',
                'nro_piso' => '3º B',
                'secondary_address' => null,
                'ciudad' => 'Madrid',
                'postal_code' => '28013',
                'provincia' => 'Madrid',
                'inhabilitado' => false,
                'notes' => [
                    ['nro' => '99101', 'gps' => true],
                    ['nro' => '99102', 'gps' => false],
                ],
                'ventas' => [
                    ['contrato' => '88001', 'cliente' => '77001'],
                ],
            ],
            [
                'dni' => 'DEMOPGC002',
                'first_names' => 'DEMO',
                'last_names' => 'CARD DOS',
                'phone' => '612111002',
                'secondary_phone' => null,
                'phone1_commercial' => null,
                'phone2_commercial' => '655111002',
                'primary_address' => 'Av. de la Constitución 45',
                'nro_piso' => 'Bajo',
                'secondary_address' => 'Urbanización Las Flores, portal 2',
                'ciudad' => 'Sevilla',
                'postal_code' => '41001',
                'provincia' => 'Sevilla',
                'inhabilitado' => false,
                'notes' => [
                    ['nro' => '99103', 'gps' => true],
                ],
                'ventas' => [
                    ['contrato' => '88002', 'cliente' => '77002'],
                    ['contrato' => '88003', 'cliente' => '77002'],
                ],
            ],
            [
                'dni' => 'DEMOPGC003',
                'first_names' => 'DEMO',
                'last_names' => 'CARD TRES',
                'phone' => '612111003',
                'secondary_phone' => '698111003',
                'phone1_commercial' => '655111003',
                'phone2_commercial' => '644111003',
                'primary_address' => 'Paseo de Gracia 88',
                'nro_piso' => '1º',
                'secondary_address' => null,
                'ciudad' => 'Barcelona',
                'postal_code' => '08008',
                'provincia' => 'Barcelona',
                'inhabilitado' => false,
                'notes' => [
                    ['nro' => '99104', 'gps' => false],
                    ['nro' => '99105', 'gps' => false],
                    ['nro' => '99106', 'gps' => true],
                ],
                'ventas' => [
                    ['contrato' => '88004', 'cliente' => '77003'],
                ],
            ],
            [
                'dni' => 'DEMOPGC004',
                'first_names' => 'DEMO',
                'last_names' => 'CARD CUATRO',
                'phone' => '612111004',
                'secondary_phone' => null,
                'phone1_commercial' => null,
                'phone2_commercial' => null,
                'primary_address' => 'Rúa do Franco 9',
                'nro_piso' => '2º',
                'secondary_address' => null,
                'ciudad' => 'Santiago de Compostela',
                'postal_code' => '15705',
                'provincia' => 'A Coruña',
                'inhabilitado' => true,
                'notes' => [
                    ['nro' => '99107', 'gps' => true],
                ],
                'ventas' => [
                    ['contrato' => '88005', 'cliente' => '77004'],
                    ['contrato' => '88006', 'cliente' => '77004'],
                ],
            ],
        ];

        foreach ($demos as $demo) {
            $customer = Customer::query()->updateOrCreate(
                ['dni' => $demo['dni']],
                collect($demo)->except(['notes', 'ventas'])->all(),
            );

            $noteIds = [];

            foreach ($demo['notes'] as $noteData) {
                $note = Note::query()->updateOrCreate(
                    ['nro_nota' => $noteData['nro']],
                    [
                        'user_id' => $userId,
                        'customer_id' => $customer->id,
                        'comercial_id' => $comercialId,
                        'fuente' => FuenteNotas::CALLE->value,
                        'status' => NoteStatus::CONTACTED->value,
                        'assignment_date' => now()->subDays(3),
                        'visit_date' => now()->subDay(),
                        'lat_dentro' => $noteData['gps'] ? '40.416775' : null,
                        'lng_dentro' => $noteData['gps'] ? '-3.703790' : null,
                    ],
                );

                $noteIds[] = $note->id;
            }

            foreach ($demo['ventas'] as $index => $ventaData) {
                $noteId = $noteIds[$index] ?? $noteIds[0];

                Venta::query()->updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'nro_contr_adm' => $ventaData['contrato'],
                    ],
                    [
                        'note_id' => $noteId,
                        'comercial_id' => $comercialId,
                        'nro_cliente_adm' => $ventaData['cliente'],
                        'fecha_venta' => now()->subDays(5 - $index),
                        'fecha_entrega' => now()->addDays(7)->toDateString(),
                        'horario_entrega' => 'Mañana',
                        'importe_total' => 850 + ($index * 120),
                        'modalidad_pago' => 'Financiado',
                        'forma_pago' => 'Transferencia',
                        'num_cuotas' => 12,
                        'cuota_mensual' => 75,
                        'accesorio_entregado' => 'Kit Hogar',
                        'interes_art' => false,
                        'status' => 'VALIDADA',
                        'estado_venta' => EstadoVenta::EN_REVISION->value,
                        'motivo_venta' => 'Demo PosGlobal.Cards',
                        'motivo_horario' => 'Demo horario',
                    ],
                );
            }
        }

        $this->command?->info('PosGlobalCardsDemoSeeder: 4 clientes DEMO creados. Busca "DEMO CARD" en PosGlobal.Cards.');
    }
}
