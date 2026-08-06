<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectWebhook>
 */
class ProjectWebhookFactory extends Factory
{
    protected $model = ProjectWebhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'url' => 'https://snag.test/webhooks/tracker',
            'secret' => Str::random(48),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }
}
