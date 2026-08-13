<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddCommentAction;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Issue $issue, AddCommentAction $action): RedirectResponse
    {
        // Anyone who can see the issue can comment on it.
        $this->authorize('view', $issue);

        $action->handle($issue, $this->currentUser($request), $request->string('body')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return back();
    }

    /**
     * Only the author may edit. Unlike destroy, a project admin does not get to
     * rewrite someone else's words: removing a comment is moderation, editing
     * one would be putting words in their mouth.
     */
    public function update(UpdateCommentRequest $request, Issue $issue, Comment $comment): RedirectResponse
    {
        abort_unless($comment->issue_id === $issue->id, 404);
        $this->authorize('view', $issue);
        abort_unless($comment->user_id === $this->currentUser($request)->id, 403);

        $comment->forceFill([
            'body' => $request->string('body')->toString(),
            'edited_at' => now(),
        ])->save();

        // Deliberately no notification and no mention scan. Re-notifying on an
        // edit would make editing a way to ping someone silently, minutes or
        // days after the fact.
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment updated.')]);

        return back();
    }

    public function destroy(Request $request, Issue $issue, Comment $comment): RedirectResponse
    {
        abort_unless($comment->issue_id === $issue->id, 404);

        // You can always remove your own comment; otherwise it takes project admin.
        if ($comment->user_id !== $this->currentUser($request)->id) {
            $this->authorize('delete', $issue);
        } else {
            $this->authorize('view', $issue);
        }

        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment removed.')]);

        return back();
    }
}
