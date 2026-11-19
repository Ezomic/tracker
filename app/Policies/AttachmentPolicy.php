<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * The uploader may take back their own file while they can still edit the
     * issue; anyone else's takes project admin, as with comments.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->user_id === $user->id
            ? $user->can('update', $attachment->issue)
            : $user->can('delete', $attachment->issue);
    }
}
