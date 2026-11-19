<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AddAttachmentAction;
use App\Actions\RemoveAttachmentAction;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Responses\AttachmentDownload;
use App\Models\Attachment;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Issue $issue, AddAttachmentAction $action): RedirectResponse
    {
        $this->authorize('update', $issue);

        $action->handle($issue, $this->currentUser($request), $request->upload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment added.')]);

        return back();
    }

    public function show(Issue $issue, Attachment $attachment): AttachmentDownload
    {
        $this->authorize('view', $issue);
        abort_unless($attachment->issue_id === $issue->id, 404);

        return new AttachmentDownload($attachment);
    }

    public function destroy(Request $request, Issue $issue, Attachment $attachment, RemoveAttachmentAction $action): RedirectResponse
    {
        $this->authorize('view', $issue);
        abort_unless($attachment->issue_id === $issue->id, 404);
        $this->authorize('delete', $attachment);

        $action->handle($attachment, $this->currentUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attachment removed.')]);

        return back();
    }
}
