<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'minutes' => $this->faker->numberBetween(15, 480),
            'spent_on' => $this->faker->dateTimeBetween('-1 month')->format('Y-m-d'),
            'note' => $this->faker->boolean() ? $this->faker->sentence() : null,
        ];
    }
}
