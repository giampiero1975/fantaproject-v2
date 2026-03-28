<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'role' => $this->faker->randomElement(['P', 'D', 'C', 'A']),
            'fanta_platform_id' => $this->faker->unique()->numberBetween(1, 10000),
            'team_id' => null, // Da assegnare nel test
            'team_name' => 'Default Team',
        ];
    }
}
