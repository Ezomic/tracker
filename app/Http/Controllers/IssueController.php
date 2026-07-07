<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Requests\StoreIssueWebRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\Issue;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('issues/Index', [
            'issues' => Issue::query()
                ->notArchived()
                ->with('team')
                ->latest()
                ->get()
                ->map($this->serialize(...)),
            'teams' => Team::query()->orderBy('key')->get(['id', 'key', 'name']),
        ]);
    }

    public function store(StoreIssueWebRequest $request, CreateIssueAction $action): RedirectResponse
    {
        $team = Team::where('id', $request->validated('team_id'))->firstOrFail();

        $issue = $action->handle(
            team: $team,
            title: $request->validated('title'),
            type: IssueType::from($request->validated('type')),
            description: $request->validated('description'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue created.')]);

        return to_route('issues.show', $issue);
    }

    public function show(Issue $issue): Response
    {
        $issue->load('team');

        return Inertia::render('issues/Show', [
            'issue' => $this->serialize($issue),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $issue->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue updated.')]);

        return to_route('issues.show', $issue);
    }

    public function board(): Response
    {
        return Inertia::render('issues/Board', [
            'issues' => Issue::query()
                ->notArchived()
                ->with('team')
                ->latest()
                ->get()
                ->map($this->serialize(...)),
        ]);
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): RedirectResponse
    {
        $status = IssueStatus::from($request->validated('status'));

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'status' => $issue->status->value,
            'branchName' => $issue->branch_name,
            'githubPrUrl' => $issue->github_pr_url,
            'team' => [
                'key' => $issue->team->key,
                'name' => $issue->team->name,
            ],
            'createdAt' => $issue->created_at?->toIso8601String(),
            'archivedAt' => $issue->archived_at?->toIso8601String(),
        ];
    }
}
