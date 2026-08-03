<?php

namespace Database\Factories;

use App\Enums\StatusCategory;
use App\Models\ProjectType;
use App\Models\WorkflowState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowState>
 */
class WorkflowStateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_type_id' => ProjectType::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'category' => StatusCategory::Started,
            'color' => $this->faker->hexColor(),
            'position' => 0,
            'is_default' => false,
        ];
    }
}
