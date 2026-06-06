<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CemeteryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'    => $this->faker->city().' Municipal Cemetery',
            'slug'    => $this->faker->unique()->slug(1),
            'city'    => $this->faker->city(),
            'county'  => $this->faker->lastName().' County',
            'state'   => $this->faker->stateAbbr(),
            'address' => $this->faker->streetAddress(),
            'notes'   => null,
        ];
    }

    public function choteau(): static
    {
        return $this->state([
            'name'    => 'Choteau Municipal Cemetery',
            'slug'    => 'choteau',
            'city'    => 'Choteau',
            'county'  => 'Teton',
            'state'   => 'MT',
            'address' => '401 N. Main St., Choteau, MT 59422',
        ]);
    }
}
