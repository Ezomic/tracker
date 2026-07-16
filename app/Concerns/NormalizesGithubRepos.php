<?php

declare(strict_types=1);

namespace App\Concerns;

trait NormalizesGithubRepos
{
    /**
     * Drop blank repo rows before validation so empty inputs don't count.
     */
    protected function prepareForValidation(): void
    {
        $repos = $this->input('github_repos');

        if (is_array($repos)) {
            $this->merge([
                'github_repos' => array_values(array_filter(
                    $repos,
                    fn (mixed $repo): bool => is_string($repo) && trim($repo) !== '',
                )),
            ]);
        }
    }
}
