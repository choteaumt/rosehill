<?php

namespace Database\Factories;

use App\Models\Cemetery;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntermentFactory extends Factory
{
    public function definition(): array
    {
        $lotNum   = $this->faker->numberBetween(1, 20);
        $blockNum = $this->faker->numberBetween(50, 150);

        return [
            'cemetery_id'       => Cemetery::factory(),
            'last_name'         => $this->faker->lastName(),
            'first_name'        => $this->faker->firstName(),
            'age_at_death'      => $this->faker->numberBetween(20, 95),
            'age_raw'           => null,
            'interment_date'    => $this->faker->dateTimeBetween('-100 years', 'now')->format('Y-m-d'),
            'interment_date_raw'=> null,
            'lot'               => 'LOT '.$lotNum,
            'lot_number'        => $lotNum,
            'lot_qualifier'     => null,
            'block'             => 'BLK '.$blockNum,
            'block_number'      => $blockNum,
            'block_suffix'      => null,
            'is_veteran'        => false,
            'is_cremation'      => false,
            'cremation_placement' => null,
            'is_infant'         => false,
            'is_disinterment'   => false,
            'notes'             => null,
            'source_notes_raw'  => null,
            'import_source'     => null,
            'import_row'        => null,
            'deed_id'           => null,
            'plot_coordinates'  => null,
        ];
    }

    public function veteran(): static
    {
        return $this->state(['is_veteran' => true]);
    }

    public function cremated(string $placement = null): static
    {
        return $this->state([
            'is_cremation'       => true,
            'cremation_placement' => $placement,
        ]);
    }

    public function infant(): static
    {
        return $this->state([
            'is_infant'    => true,
            'age_at_death' => null,
            'age_raw'      => 'infant',
            'first_name'   => 'Baby',
        ]);
    }

    public function disinterment(): static
    {
        return $this->state(['is_disinterment' => true]);
    }

    public function withHalfLot(string $qualifier = 'N 1/2'): static
    {
        $lotNum = $this->faker->numberBetween(1, 20);
        return $this->state([
            'lot'           => 'LOT '.$lotNum.' '.$qualifier,
            'lot_number'    => $lotNum,
            'lot_qualifier' => $qualifier,
        ]);
    }

    public function withBlockSuffix(string $suffix = 'B'): static
    {
        $blockNum = $this->faker->numberBetween(50, 150);
        return $this->state([
            'block'        => 'BLK '.$blockNum.$suffix,
            'block_number' => $blockNum,
            'block_suffix' => $suffix,
        ]);
    }

    public function withCoordinates(): static
    {
        return $this->state([
            'plot_coordinates' => [
                'lat' => $this->faker->latitude(47.8, 47.9),
                'lng' => $this->faker->longitude(-112.2, -112.1),
            ],
        ]);
    }
}
