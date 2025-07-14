<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use App\Models\Customer;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->value('id') ?? 1,
            // Se sobre-escribirá en el seeder
            'customer_id' => Customer::inRandomOrder()->value('id') ?? 1,
            'comercial_id' => null,                       // ← Sin comercial
            'fuente' => FuenteNotas::CALLE->value,  // ← Siempre “CALLE”
            'status' => Arr::random([
                NoteStatus::CONTACTED,
                NoteStatus::NULL,
            ])->value,
            'observations' => $this->faker->sentence(),
            'visit_date' => $this->faker->dateTimeBetween('-1 month', '+15 days'),
            'de_camino' => $this->faker->boolean(10),
            'visit_schedule' => $this->faker->randomElement(['Mañana', 'Tarde', 'Noche']),
            'assignment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'lat' => $this->faker->latitude(28.0, 29.0),
            'lng' => $this->faker->longitude(-16.0, -15.0),
            'show_phone' => $this->faker->boolean(30),
            // estado_terminal queda NULL por la lógica del modelo
        ];
    }

    /** Estado terminal NULL + sin comercial_id (por claridad) */
    public function sinTerminalNiComercial(): self
    {
        return $this->state(fn() => [
            'comercial_id' => null,
            'estado_terminal' => null,
        ]);
    }
}
