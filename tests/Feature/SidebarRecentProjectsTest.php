<?php

declare(strict_types=1);

use App\Actions\RecordProjectVisitAction;
use App\Enums\OrganizationRole;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function visits(User $user): int
{
    return DB::table('project_visits')->where('user_id', $user->id)->count();
}

it('records a visit when the user opens a project board', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->actingAs($user)->get('/THI/board')->assertOk();

    $this->assertDatabaseHas('project_visits', ['user_id' => $user->id, 'project_id' => $project->id]);
});

it('records a visit to the issue\'s project when the user opens an issue', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = Issue::factory()->for($project)->create();
    $user = member($project);

    $this->actingAs($user)->get("/issues/{$issue->identifier}")->assertOk();

    $this->assertDatabaseHas('project_visits', ['user_id' => $user->id, 'project_id' => $project->id]);
});

it('keeps one row per project and moves its visit time forward', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->travelTo(now()->subDay());
    $this->actingAs($user)->get('/THI/board')->assertOk();
    $this->travelBack();
    $this->actingAs($user)->get('/THI/board')->assertOk();
    $this->actingAs($user)->get('/THI/tickets')->assertOk();

    expect(visits($user))->toBe(1)
        ->and(DB::table('project_visits')->value('visited_at'))->toStartWith(now()->toDateString());
});

it('records nothing on a page without a current project', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->actingAs($user)->get('/dashboard')->assertOk();

    expect(visits($user))->toBe(0);
});

it('records nothing when the user is refused the project', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Member);
    Project::factory()->create(['key' => 'SEC', 'organization_id' => $organization->id]);

    $this->actingAs($user)->get('/SEC/board')->assertForbidden();

    expect(visits($user))->toBe(0);
});

it('records nothing for a service account', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $robot = joinProjects(User::factory()->create(['is_service' => true]), $project);

    $this->actingAs($robot)->get('/THI/board');

    expect(visits($robot))->toBe(0);
});

it('never records a visit for a service account, whatever the caller', function () {
    $project = Project::factory()->create();
    $robot = joinProjects(User::factory()->create(['is_service' => true]), $project);

    app(RecordProjectVisitAction::class)->handle($robot, $project->id);

    expect(visits($robot))->toBe(0);
});

it('records nothing for an api request', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->actingAs($user, 'sanctum')->getJson('/api/projects/THI/members');

    expect(visits($user))->toBe(0);
});

it('lists the five most recently visited projects, most recent first', function () {
    $projects = collect(['AAA', 'BBB', 'CCC', 'DDD', 'EEE', 'FFF'])
        ->map(fn (string $key) => Project::factory()->create(['key' => $key]));
    $user = member($projects->all());

    foreach ($projects as $index => $project) {
        $this->travelTo(now()->addMinutes($index + 1));
        $this->actingAs($user)->get("/{$project->key}/board")->assertOk();
    }

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarCategories.recent', 5)
            ->where('sidebarCategories.recent.0.key', 'FFF')
            ->where('sidebarCategories.recent.1.key', 'EEE')
            ->where('sidebarCategories.recent.4.key', 'BBB')
        );
});

it('puts the project being viewed first in recent', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $user = member([$thi, $cms]);

    $this->actingAs($user)->get('/THI/board');
    $this->travel(1)->minute();
    $this->actingAs($user)->get('/CMS/board');
    $this->travel(1)->minute();

    $this->actingAs($user)->get('/THI/board')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarCategories.recent', 2)
            ->where('sidebarCategories.recent.0.key', 'THI')
            ->where('sidebarCategories.recent.1.key', 'CMS')
        );
});

it('shows an inactive project being viewed for the first time in recent', function () {
    $project = Project::factory()->create(['key' => 'OLD']);
    Issue::factory()->for($project)->create(['updated_at' => now()->subMonth()]);
    $user = member($project);

    $this->actingAs($user)->get('/OLD/board')
        ->assertInertia(fn ($page) => $page
            ->where('sidebarCategories.recent.0.key', 'OLD')
            ->where('sidebarCategories.recent.0.active', false)
        );
});

it('shares the same project shape in recent as in the tree', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme']);
    $user = member($project);

    $this->actingAs($user)->get('/THI/board');

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('sidebarCategories.recent.0.id', $project->id)
            ->where('sidebarCategories.recent.0.name', 'Acme')
            ->has('sidebarCategories.recent.0.counts')
            ->has('sidebarCategories.recent.0.active')
        );
});

it('drops a visited project once it is archived', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->actingAs($user)->get('/THI/board');
    $project->forceFill(['archived_at' => now()])->save();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('sidebarCategories.recent', 0));
});

it('drops a visited project once the user loses membership', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $this->actingAs($user)->get('/THI/board');
    $project->members()->detach($user->id);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('sidebarCategories.recent', 0));
});

it('leaves out visited projects from another organization', function () {
    $alpha = Organization::factory()->create(['name' => 'Alpha']);
    $beta = Organization::factory()->create(['name' => 'Beta']);
    $user = User::factory()->create();
    $alpha->members()->attach($user->id, ['role' => OrganizationRole::Member->value]);
    $beta->members()->attach($user->id, ['role' => OrganizationRole::Member->value]);
    projectInOrganization($alpha, $user, ['key' => 'ALP']);
    projectInOrganization($beta, $user, ['key' => 'BET']);

    $this->actingAs($user)->get('/BET/board')->assertOk();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('currentOrganization.name', 'Alpha')
            ->has('sidebarCategories.recent', 0)
        );
});

it('never shows one user\'s visits to another user', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $visitor = member($project);
    $other = member($project);

    $this->actingAs($visitor)->get('/THI/board');

    $this->actingAs($other)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('sidebarCategories.recent', 0));
});
