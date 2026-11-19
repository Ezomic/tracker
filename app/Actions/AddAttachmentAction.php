<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Attachment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AddAttachmentAction
{
    private const int MAX_NAME_LENGTH = 255;

    public function handle(Issue $issue, User $uploader, UploadedFile $file): Attachment
    {
        $path = $file->store("attachments/{$issue->id}", Attachment::DISK);

        if ($path === false) {
            throw new RuntimeException("Could not store an attachment for {$issue->identifier}.");
        }

        try {
            return DB::transaction(fn (): Attachment => $this->record($issue, $uploader, $file, $path));
        } catch (Throwable $e) {
            Storage::disk(Attachment::DISK)->delete($path);

            throw $e;
        }
    }

    private function record(Issue $issue, User $uploader, UploadedFile $file, string $path): Attachment
    {
        $attachment = $issue->attachments()->create([
            'user_id' => $uploader->id,
            'name' => Str::limit($file->getClientOriginalName(), self::MAX_NAME_LENGTH, ''),
            'path' => $path,
            // Detected from the content, not the client's claim, because it
            // decides whether the file is served inline.
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        $issue->recordActivity('attachment_added', ['name' => $attachment->name], $uploader->id);

        return $attachment;
    }
}
