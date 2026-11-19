<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RemoveAttachmentAction
{
    /**
     * The row goes first: a file left behind is invisible, while a row left
     * without its file would show on the issue and fail to download.
     */
    public function handle(Attachment $attachment, User $remover): void
    {
        DB::transaction(function () use ($attachment, $remover): void {
            $attachment->delete();
            $attachment->issue->recordActivity('attachment_removed', ['name' => $attachment->name], $remover->id);
        });

        Storage::disk(Attachment::DISK)->delete($attachment->path);
    }
}
