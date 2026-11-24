<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateNoteAction;
use App\Actions\UpdateNoteAction;
use App\Http\Requests\FilterNotesRequest;
use App\Http\Requests\SaveNoteRequest;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public function index(FilterNotesRequest $request, Project $project): Response
    {
        $user = $this->currentUser($request);
        $this->authorize('view', $project);

        $term = $request->term();

        $notes = $project->notes()
            ->select(['id', 'project_id', 'user_id', 'title', 'updated_at'])
            ->with('author:id,name')
            ->when($term !== '', fn (Builder $query) => $query->matching($term))
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->map(fn (Note $note): array => [
                'id' => $note->id,
                'title' => $note->title,
                'author' => $note->author?->name,
                'updatedAt' => $note->updated_at->toIso8601String(),
            ]);

        return Inertia::render('notes/Index', [
            'project' => $this->projectProps($project),
            'notes' => $notes,
            'filters' => ['search' => $term],
            'can' => ['create' => $user->can('create', [Note::class, $project])],
        ]);
    }

    public function show(Request $request, Project $project, Note $note): Response
    {
        $user = $this->currentUser($request);
        $this->authorize('view', $note);

        return Inertia::render('notes/Show', [
            'project' => $this->projectProps($project),
            'note' => [
                'id' => $note->id,
                'title' => $note->title,
                'body' => $note->body,
                'author' => $note->author?->name,
                'createdAt' => $note->created_at->toIso8601String(),
                'updatedAt' => $note->updated_at->toIso8601String(),
            ],
            'can' => [
                'update' => $user->can('update', $note),
                'delete' => $user->can('delete', $note),
            ],
        ]);
    }

    public function store(SaveNoteRequest $request, Project $project, CreateNoteAction $action): RedirectResponse
    {
        $note = $action->handle($project, $this->currentUser($request), $request->noteTitle(), $request->noteBody());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note created.')]);

        return to_route('projects.notes.show', [$project, $note]);
    }

    public function update(SaveNoteRequest $request, Project $project, Note $note, UpdateNoteAction $action): RedirectResponse
    {
        $action->handle($note, $request->noteTitle(), $request->noteBody());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note updated.')]);

        return back();
    }

    public function destroy(Project $project, Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note deleted.')]);

        return to_route('projects.notes.index', $project);
    }

    /**
     * @return array{key: string, name: string}
     */
    private function projectProps(Project $project): array
    {
        return ['key' => $project->key, 'name' => $project->name];
    }
}
