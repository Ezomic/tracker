<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class Mentions
{
    /**
     * Resolve the project members referenced by @handles in a comment body.
     * A handle matches a member's email local-part, their name with spaces
     * removed, or their first name (all case-insensitive).
     *
     * @return Collection<int, User>
     */
    public static function membersIn(string $body, Project $project): Collection
    {
        preg_match_all('/@([\p{L}0-9._-]+)/u', $body, $matches);

        $handles = array_values(array_unique(array_map(
            fn (string $handle) => mb_strtolower($handle),
            $matches[1],
        )));

        if ($handles === []) {
            return collect();
        }

        return $project->members()
            ->get()
            ->filter(fn (User $member) => array_intersect($handles, self::handlesFor($member)) !== [])
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private static function handlesFor(User $member): array
    {
        $emailLocal = mb_strtolower((string) strtok($member->email, '@'));
        $name = mb_strtolower($member->name);

        return array_values(array_unique([
            $emailLocal,
            str_replace(' ', '', $name),
            (string) strtok($name, ' '),
        ]));
    }
}
