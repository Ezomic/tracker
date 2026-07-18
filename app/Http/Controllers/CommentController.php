<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Issue $issue): RedirectResponse
    {
        // Anyone who can see the issue can comment on it.
        $this->authorize('view', $issue);

        $issue->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return back();
    }

    public function destroy(Request $request, Issue $issue, Comment $comment): RedirectResponse
    {
        abort_unless($comment->issue_id === $issue->id, 404);

        // You can always remove your own comment; otherwise it takes project admin.
        if ($comment->user_id !== $request->user()->id) {
            $this->authorize('delete', $issue);
        } else {
            $this->authorize('view', $issue);
        }

        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment removed.')]);

        return back();
    }
}
