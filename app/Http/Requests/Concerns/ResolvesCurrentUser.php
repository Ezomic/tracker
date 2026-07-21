<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\User;

trait ResolvesCurrentUser
{
    /**
     * The authenticated user for a request behind the auth middleware.
     */
    protected function currentUser(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
