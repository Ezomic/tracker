<?php

namespace Database\Factories;

use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->randomElement(LabelColor::cases()),
        ];
    }
}
