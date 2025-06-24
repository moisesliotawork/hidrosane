<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'id' => 1,
                'uid' => '2bade630-6fc0-49b1-b158-f16c527911a9',
                'title' => 'España',
                'iso' => 'ES',
                'created_at' => '2025-04-12 09:01:13',
                'updated_at' => '2025-04-12 09:01:13'
            ]
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['id' => $countryData['id']],
                $countryData
            );
        }

        // Opcional: Mensaje de confirmación
        $this->command->info('Countries seeded successfully!');
    }
}