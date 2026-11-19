<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $issue_id
 * @property int|null $user_id
 * @property string $name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property Carbon $created_at
 * @property-read Issue $issue
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'name', 'path', 'mime_type', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    public const string DISK = 'local';

    public const int MAX_KILOBYTES = 20 * 1024;

    /**
     * Only raster images render in the browser. Anything else, SVG and HTML
     * included, could run script on the tracker's origin, so it downloads.
     */
    private const array INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::INLINE_TYPES, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
