<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DraftIssueFromTextAction;
use App\Http\Requests\DraftIssueRequest;
use App\Models\IssueTemplate;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Drafting\IssueDraftFailed;
use App\Services\Integrations;
use App\Support\Cast;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class IssueDraftController extends Controller
{
    public function __invoke(DraftIssueRequest $request, DraftIssueFromTextAction $action, Integrations $integrations): JsonResponse
    {
        $project = Project::query()->with('organization')->findOrFail(Cast::int($request->validated('project_id')));

        $this->authorize('createIssue', $project);

        if (! $integrations->drafting($project->organization)) {
            return response()->json(['message' => __('Drafting from text is not available.')], 403);
        }

        $templates = $this->templates($request, $project->organization);
        $template = $templates->firstWhere('id', $request->validated('template_id'));

        try {
            return response()->json($action->handle($project, $request->string('text')->toString(), $templates, $template));
        } catch (IssueDraftFailed) {
            return response()->json(['message' => __('Claude could not draft this issue. Fill in the form yourself, or try again.')], 502);
        }
    }

    /**
     * The organization's templates, unless the user is a guest: guests cannot
     * see the template library, so Claude does not get to show it to them.
     *
     * @return Collection<int, IssueTemplate>
     */
    private function templates(DraftIssueRequest $request, ?Organization $organization): Collection
    {
        if ($organization === null || ! $this->currentUser($request)->can('viewLibrary', $organization)) {
            return new Collection;
        }

        return $organization->issueTemplates()->orderBy('name')->get()->collect();
    }
}
