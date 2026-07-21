<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Cast;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The authenticated user for a request behind the auth middleware.
     */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Normalise a validated array of scalars into a list of strings.
     *
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        return Cast::strings($value);
    }

    /**
     * Normalise a validated array of ids into a list of integers.
     *
     * @return list<int>
     */
    protected function intList(mixed $value): array
    {
        return Cast::ints($value);
    }
}
