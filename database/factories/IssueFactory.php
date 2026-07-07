<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        $number = $this->faker->unique()->numberBetween(1, 100000);

        return [
            'team_id' => Team::factory(),
            'number' => $number,
            'identifier' => "TEST-{$number}",
            'title' => $title,
            'slug' => str($title)->slug(),
            'description' => $this->faker->paragraph(),
            'type' => IssueType::Feature,
            'status' => IssueStatus::Backlog,
            'branch_name' => 'feature/test-'.$number.'-'.str($title)->slug(),
        ];
    }
}
