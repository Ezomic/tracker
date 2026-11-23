<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\IssueTemplate;
use App\Models\Project;
use App\Services\Drafting\IssueDrafter;
use Illuminate\Support\Collection;

class DraftIssueFromTextAction
{
    private const int TITLE_MAX = 255;

    public function __construct(private readonly IssueDrafter $drafter) {}

    /**
     * Draft an issue and keep only what the create form would accept: a field
     * Claude got wrong comes back empty rather than failing the draft.
     *
     * @param  Collection<int, IssueTemplate>  $templates
     * @return array{template_id: int|null, title: string, type: string|null, priority: string|null, description: string}
     */
    public function handle(Project $project, string $text, Collection $templates, ?IssueTemplate $template): array
    {
        $draft = $this->drafter->draft($text, $project, $templates, $template);

        return [
            'template_id' => $template->id ?? $this->templateId($draft['template_id'] ?? null, $templates),
            'title' => mb_substr(trim($this->string($draft['title'] ?? null)), 0, self::TITLE_MAX),
            'type' => IssueType::tryFrom($this->string($draft['type'] ?? null))?->value,
            'priority' => IssuePriority::tryFrom($this->string($draft['priority'] ?? null))?->value,
            'description' => $this->string($draft['description'] ?? null),
        ];
    }

    /**
     * @param  Collection<int, IssueTemplate>  $templates
     */
    private function templateId(mixed $id, Collection $templates): ?int
    {
        return is_int($id) && $templates->contains('id', $id) ? $id : null;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
