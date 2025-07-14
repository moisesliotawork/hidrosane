<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Note;
use App\Models\Customer;

class NoteSeeder extends Seeder
{
    public function run(): void
    {
        // Garantizamos que existan clientes
        if (Customer::count() === 0) {
            $this->call(CustomerSeeder::class);
        }

        $customerIds = Customer::pluck('id');

        Note::factory()
            ->count(70)
            ->sinTerminalNiComercial()
            ->sequence(fn() => [
                'customer_id' => $customerIds->random(),
            ])
            ->create();
    }
}
