<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function update(User $user, Category $category): bool
    {
        return $category->organization?->roleFor($user)?->manages() ?? false;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->organization?->roleFor($user)?->manages() ?? false;
    }
}
