<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

class CreateServiceAccountAction
{
    /**
     * The abilities a service token ever gets. Narrow on purpose: an ingest
     * token that leaks must not be able to reshape the workspace.
     *
     * @var list<string>
     */
    public const ABILITIES = ['issues:create', 'issues:read', 'projects:read'];

    /**
     * Create the machine identity, grant it write on exactly the projects it
     * was asked for, and mint its token. Returns the token so the caller can
     * show the plain text once.
     *
     * @param  list<Project>  $projects
     */
    public function handle(Organization $organization, string $name, array $projects): NewAccessToken
    {
        return DB::transaction(function () use ($organization, $name, $projects): NewAccessToken {
            $account = new User;
            $account->forceFill([
                'name' => $name,
                // Never delivered to: the address exists only to satisfy the
                // unique column every user row needs.
                'email' => Str::lower(Str::slug($name)).'+'.Str::random(8).'@service.invalid',
                'is_service' => true,
                'email_verified_at' => now(),
                'locale' => config('app.locale'),
            ])->save();

            $organization->members()->syncWithoutDetaching([
                $account->id => ['role' => OrganizationRole::Guest->value],
            ]);

            foreach ($projects as $project) {
                $project->members()->syncWithoutDetaching([
                    $account->id => ['level' => ProjectLevel::Write->value],
                ]);
            }

            return $account->createToken($name, self::ABILITIES);
        });
    }
}
