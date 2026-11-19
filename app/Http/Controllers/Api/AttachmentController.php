<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddAttachmentAction;
use App\Actions\RemoveAttachmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Responses\AttachmentDownload;
use App\Models\Attachment;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function index(Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $attachments = $issue->attachments()
            ->with('user:id,email')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Attachment $attachment): array => $this->payload($issue, $attachment));

        return response()->json($attachments);
    }

    public function store(StoreAttachmentRequest $request, Issue $issue, AddAttachmentAction $action): JsonResponse
    {
        $this->authorize('update', $issue);

        $attachment = $action->handle($issue, $this->currentUser($request), $request->upload());

        return response()->json($this->payload($issue, $attachment->load('user:id,email')), 201);
    }

    public function show(Issue $issue, Attachment $attachment): AttachmentDownload
    {
        $this->authorize('view', $issue);
        abort_unless($attachment->issue_id === $issue->id, 404);

        return new AttachmentDownload($attachment);
    }

    public function destroy(Request $request, Issue $issue, Attachment $attachment, RemoveAttachmentAction $action): JsonResponse
    {
        $this->authorize('view', $issue);
        abort_unless($attachment->issue_id === $issue->id, 404);
        $this->authorize('delete', $attachment);

        $action->handle($attachment, $this->currentUser($request));

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Issue $issue, Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'user' => $attachment->user?->email,
            'url' => url("/api/issues/{$issue->identifier}/attachments/{$attachment->id}"),
            'created_at' => $attachment->created_at->toIso8601String(),
        ];
    }
}
