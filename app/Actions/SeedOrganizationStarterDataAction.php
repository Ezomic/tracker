<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\LabelColor;
use App\Enums\StatusCategory;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class SeedOrganizationStarterDataAction
{
    /**
     * Area labels, deliberately orthogonal to the templates (which already
     * cover intent: bug, feature, chore, spike).
     *
     * @var array<string, LabelColor>
     */
    private const LABELS = [
        'backend' => LabelColor::Blue,
        'frontend' => LabelColor::Purple,
        'api' => LabelColor::Blue,
        'database' => LabelColor::Gray,
        'infra' => LabelColor::Gray,
        'ui' => LabelColor::Purple,
        'docs' => LabelColor::Green,
        'security' => LabelColor::Red,
        'performance' => LabelColor::Yellow,
        'tech-debt' => LabelColor::Yellow,
    ];

    /**
     * @var list<array{name: string, type: IssueType, priority: IssuePriority|null, description: string}>
     */
    private const TEMPLATES = [
        [
            'name' => 'Bug report',
            'type' => IssueType::Fix,
            'priority' => IssuePriority::High,
            'description' => "## Steps to reproduce\n1. \n\n## Expected\n\n## Actual\n\n## Environment\n",
        ],
        [
            'name' => 'Feature request',
            'type' => IssueType::Feature,
            'priority' => null,
            'description' => "## Problem\n\n## Proposed solution\n\n## Acceptance criteria\n- [ ] \n",
        ],
        [
            'name' => 'Chore',
            'type' => IssueType::Feature,
            'priority' => null,
            'description' => "## What\n\n## Why\n",
        ],
        [
            'name' => 'Spike',
            'type' => IssueType::Feature,
            'priority' => null,
            'description' => "## Question\n\n## Timebox\n\n## Findings\n",
        ],
    ];

    /**
     * The default project type's lanes, in order: [name, category, colour].
     *
     * @var list<array{0: string, 1: StatusCategory, 2: string}>
     */
    private const LANES = [
        ['Backlog', StatusCategory::Backlog, '#9ca3af'],
        ['In Progress', StatusCategory::Started, '#d85a30'],
        ['In Review', StatusCategory::Started, '#378add'],
        ['Done', StatusCategory::Completed, '#1d9e75'],
    ];

    /**
     * Give an organization the standard labels, issue templates and default
     * project type. Safe to run again: labels and templates are matched by
     * name and left alone when present, and the project type is only added
     * to an organization that has none.
     */
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $this->seedLabels($organization);
            $this->seedTemplates($organization);
            $this->seedProjectType($organization);
        });
    }

    private function seedLabels(Organization $organization): void
    {
        foreach (self::LABELS as $name => $color) {
            $organization->labels()->firstOrCreate(['name' => $name], ['color' => $color]);
        }
    }

    private function seedTemplates(Organization $organization): void
    {
        foreach (self::TEMPLATES as $template) {
            $organization->issueTemplates()->firstOrCreate(['name' => $template['name']], $template);
        }
    }

    private function seedProjectType(Organization $organization): void
    {
        if ($organization->projectTypes()->exists()) {
            return;
        }

        $type = $organization->projectTypes()->create([
            'name' => 'Engineering',
            'description' => 'Backlog, In Progress, In Review, Done.',
            'is_default' => true,
        ]);

        foreach (self::LANES as $position => [$name, $category, $color]) {
            $type->states()->create([
                'name' => $name,
                'category' => $category,
                'color' => $color,
                'position' => $position,
                'is_default' => $position === 0,
            ]);
        }
    }
}
