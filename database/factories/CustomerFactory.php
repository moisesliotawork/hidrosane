<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_names'       => $this->faker->firstName(),
            'last_names'        => $this->faker->lastName(),
            'phone'             => $this->faker->numerify('6########'),
            'secondary_phone'   => $this->faker->optional()->numerify('6########'),
            'email'             => $this->faker->unique()->safeEmail(),
            'age'               => $this->faker->numberBetween(18, 80),
            'postal_code_id'    => $this->faker->optional()->numerify('######'),
            'primary_address'   => $this->faker->streetAddress(),
            'secondary_address' => $this->faker->optional()->secondaryAddress(),
            'parish'            => $this->faker->citySuffix(),
            'dni'               => $this->faker->unique()->numerify('########X'),
            'fecha_nac'         => $this->faker->date('Y-m-d', '-18 years'),
            'iban'              => $this->faker->iban('ES'),
            'tipo_vivienda'     => $this->faker->randomElement(['Alquilada', 'Propia']),
            'estado_civil'      => $this->faker->randomElement(['Soltero', 'Casado', 'Divorciado']),
            'situacion_laboral' => $this->faker->randomElement(['Autónomo', 'Empleado', 'Desempleado']),
            'ingresos_rango'    => $this->faker->randomElement(['0-1200', '1201-2000', '2001-3000', '>3000']),
            'num_hab_casa'      => $this->faker->numberBetween(1, 5),
        ];
    }
}
