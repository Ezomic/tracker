<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\PersonalOrganization;
use Illuminate\Support\Facades\Mail;

function orgWithOwner(string $name = 'Acme'): array
{
    $organization = Organization::factory()->create(['name' => $name]);
    $user = User::factory()->create();
    $organization->members()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

    return [$organization, $user];
}

it('shares the current organization and the list to switch between', function () {
    [$acme, $user] = orgWithOwner('Acme');
    $other = Organization::factory()->create(['name' => 'Beta']);
    $other->members()->attach($user->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('currentOrganization.name', 'Acme')
            ->has('organizations', 2)
        );
});

it('switches the current organization and remembers it', function () {
    [$acme, $user] = orgWithOwner('Acme');
    $beta = Organization::factory()->create(['name' => 'Beta']);
    $beta->members()->attach($user->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($user)->put("/organizations/{$beta->slug}/switch")
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currentOrganization.name', 'Beta'));
});

it('forbids switching to an organization you do not belong to', function () {
    [, $user] = orgWithOwner();
    $theirs = Organization::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)->put("/organizations/{$theirs->slug}/switch")
        ->assertForbidden();
});

it('falls back to another organization when the remembered one is gone', function () {
    [$acme, $user] = orgWithOwner('Acme');

    $this->actingAs($user)->withSession(['current_organization_id' => 9999])
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currentOrganization.name', 'Acme'));
});

it('only lists projects belonging to the current organization', function () {
    [$acme, $user] = orgWithOwner('Acme');
    $beta = Organization::factory()->create(['name' => 'Beta']);
    $beta->members()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

    $inAcme = Project::factory()->create(['key' => 'AAA', 'organization_id' => $acme->id]);
    $inBeta = Project::factory()->create(['key' => 'BBB', 'organization_id' => $beta->id]);
    joinProjects($user, [$inAcme, $inBeta]);

    $this->actingAs($user)->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.key', 'AAA')
        );

    $this->actingAs($user)->put("/organizations/{$beta->slug}/switch");

    $this->actingAs($user)->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.key', 'BBB')
        );
});

it('files a new project under the current organization', function () {
    [$acme, $user] = orgWithOwner('Acme');

    $this->actingAs($user)->post('/projects', ['key' => 'NEW', 'name' => 'New']);

    expect(Project::query()->where('key', 'NEW')->first()->organization_id)->toBe($acme->id);
});

it('gives a new registrant a personal organization', function () {
    Mail::fake();

    $this->post('/register', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $organization = $user->organizations()->firstOrFail();

    expect($organization->name)->toBe('Ada Lovelace')
        ->and($organization->roleFor($user))->toBe(OrganizationRole::Owner);
});

it('creates a personal organization with a unique slug', function () {
    $service = app(PersonalOrganization::class);
    $first = $service->create(User::factory()->create(['name' => 'Same Name']));
    $second = $service->create(User::factory()->create(['name' => 'Same Name']));

    expect($first->slug)->not->toBe($second->slug);
});

it('leaves listings unfiltered for a user with no organization', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    // Nothing to scope by, so membership alone decides — the pre-organization
    // behaviour, rather than accidentally matching unfiled rows.
    $this->actingAs($user)->get('/projects')
        ->assertInertia(fn ($page) => $page->has('projects', 1));
});
