<?php

namespace Database\Factories;

use App\Models\IssueTemplate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueTemplate>
 */
class IssueTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->paragraph(),
            'type' => null,
            'priority' => null,
        ];
    }
}
