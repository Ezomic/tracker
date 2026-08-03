<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectType>
 */
class ProjectTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => null,
            'is_default' => false,
        ];
    }
}
