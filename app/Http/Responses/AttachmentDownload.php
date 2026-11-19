<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Attachment;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownload implements Responsable
{
    public function __construct(private readonly Attachment $attachment) {}

    /**
     * nosniff and a sandbox CSP keep a download from ever running as a page
     * on the tracker's origin, even if a browser decided to render it.
     */
    public function toResponse($request): StreamedResponse
    {
        return Storage::disk(Attachment::DISK)->response(
            $this->attachment->path,
            $this->attachment->name,
            [
                'Content-Type' => $this->attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ],
            $this->attachment->isImage() ? 'inline' : 'attachment',
        );
    }
}
