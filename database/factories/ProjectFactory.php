<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->company(),
            'color' => $this->faker->randomElement([
                '#d85a30', '#1d9e75', '#378add', '#ef9f27', '#d4537e', '#7f77dd',
            ]),
            'next_number' => 0,
        ];
    }
}
