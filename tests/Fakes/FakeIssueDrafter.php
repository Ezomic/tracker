<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Models\IssueTemplate;
use App\Models\Project;
use App\Services\Drafting\IssueDrafter;
use App\Services\Drafting\IssueDraftFailed;
use Illuminate\Support\Collection;

final class FakeIssueDrafter implements IssueDrafter
{
    /** @var list<array{text: string, project: Project, templates: Collection<int, IssueTemplate>, template: ?IssueTemplate}> */
    public array $calls = [];

    /**
     * @param  array<string, mixed>|IssueDraftFailed  $result
     */
    public function __construct(private readonly array|IssueDraftFailed $result = []) {}

    public function draft(string $text, Project $project, Collection $templates, ?IssueTemplate $template): array
    {
        $this->calls[] = ['text' => $text, 'project' => $project, 'templates' => $templates, 'template' => $template];

        if ($this->result instanceof IssueDraftFailed) {
            throw $this->result;
        }

        return $this->result;
    }
}
