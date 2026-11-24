<?php

declare(strict_types=1);

namespace App\Services\Drafting;

use App\Models\IssueTemplate;
use App\Models\Project;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Support\Collection;

#[Bind(ClaudeIssueDrafter::class)]
interface IssueDrafter
{
    /**
     * Draft an issue from pasted text. The fields come back as written, so
     * they still need checking before they reach the form.
     *
     * @param  Collection<int, IssueTemplate>  $templates
     * @return array<mixed>
     *
     * @throws IssueDraftFailed
     */
    public function draft(string $text, Project $project, Collection $templates, ?IssueTemplate $template): array;
}
