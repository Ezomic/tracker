<?php

namespace Database\Factories;

use App\Models\Commit;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commit>
 */
class CommitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sha = $this->faker->sha1();

        return [
            'issue_id' => Issue::factory(),
            'repository' => 'owner/repo',
            'sha' => $sha,
            'branch' => 'feature/THI-1-something',
            'message' => $this->faker->sentence(),
            'author_name' => $this->faker->name(),
            'url' => "https://github.com/owner/repo/commit/{$sha}",
            'committed_at' => $this->faker->dateTimeBetween('-1 week'),
        ];
    }
}
