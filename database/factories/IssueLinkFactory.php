<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IssueRelation;
use App\Models\Issue;
use App\Models\IssueLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueLink>
 */
class IssueLinkFactory extends Factory
{
    protected $model = IssueLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'related_issue_id' => Issue::factory(),
            'relation' => IssueRelation::RelatesTo,
        ];
    }
}
