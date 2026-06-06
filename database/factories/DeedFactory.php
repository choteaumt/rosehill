<?php

namespace Database\Factories;

use App\Models\Cemetery;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeedFactory extends Factory
{
    public function definition(): array
    {
        $lotNum   = $this->faker->numberBetween(1, 20);
        $blockNum = $this->faker->numberBetween(50, 150);

        return [
            'cemetery_id'  => Cemetery::factory(),
            'lot'          => 'LOT '.$lotNum,
            'block'        => 'BLK '.$blockNum,
            'grantor_name' => $this->faker->name(),
            'grantee_name' => $this->faker->name(),
            'deed_date'    => $this->faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'notes'        => null,
        ];
    }
}
