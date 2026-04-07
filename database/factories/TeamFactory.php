<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'short_name' => strtoupper($this->faker->lexify('???')),
            'api_id' => $this->faker->unique()->numberBetween(1, 1000),
            'fbref_url' => null,
            'fbref_id' => null,
        ];
    }
}
