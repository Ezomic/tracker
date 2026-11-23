<?php

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Webhooks\HostResolver;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
| How an optional env var reaches config when an operator leaves it out:
| unset gives null, `KEY=` gives an empty string, `KEY="   "` gives spaces.
| Every one of them must read as "this integration is off".
*/
dataset('blank', [
    'unset' => [null],
    'empty string' => [''],
    'whitespace only' => ['   '],
]);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function configureSso(): void
{
    config()->set('services.thijssensoftware.base_url', 'https://id.example.test');
    config()->set('services.thijssensoftware.client_id', 'client-id');
    config()->set('services.thijssensoftware.client_secret', 'client-secret');
}

/**
 * Answer webhook host lookups from a fixed map instead of DNS. A host missing
 * from the map does not resolve.
 *
 * @param  array<string, list<string>>  $map
 */
function resolveHosts(array $map): void
{
    app()->instance(HostResolver::class, new class($map) implements HostResolver
    {
        /** @param array<string, list<string>> $map */
        public function __construct(private array $map) {}

        public function resolve(string $host): array
        {
            return $this->map[$host] ?? [];
        }
    });
}

/**
 * Create a user who is a member of the given projects (Admin by default).
 */
function member(Project|array $projects, ProjectLevel $level = ProjectLevel::Admin): User
{
    return joinProjects(User::factory()->create(), $projects, $level);
}

/**
 * Grant an existing user the given level on the given project(s).
 *
 * @param  Project|array<int, Project>  $projects
 */
function joinProjects(User $user, Project|array $projects, ProjectLevel $level = ProjectLevel::Admin): User
{
    foreach (is_array($projects) ? $projects : [$projects] as $project) {
        $project->members()->syncWithoutDetaching([
            $user->id => ['level' => $level->value],
        ]);
    }

    return $user;
}

/**
 * Create an organization with a member in the given role, returning both.
 *
 * @return array{0: Organization, 1: User}
 */
function organizationWith(OrganizationRole $role = OrganizationRole::Owner): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->members()->attach($user->id, ['role' => $role->value]);

    return [$organization, $user];
}

/**
 * A project inside an organization, with the given user as a project member.
 *
 * @param  array<string, mixed>  $attributes
 */
function projectInOrganization(Organization $organization, User $member, array $attributes = []): Project
{
    $project = Project::factory()->create([...$attributes, 'organization_id' => $organization->id]);
    joinProjects($member, $project);

    return $project;
}

function configureNotion(): void
{
    config()->set('services.notion.api_key', 'secret_test_token');
}

/**
 * Answer Notion requests by "METHOD /path" (fnmatch wildcards allowed). Falls
 * back to a one-data-source database with just a title column, and pages that
 * are created with a fixed id. Anything in $routes wins, as a Response or an
 * array body.
 *
 * @param  array<string, Response|PromiseInterface|array<string, mixed>>  $routes
 */
function fakeNotion(array $routes = []): void
{
    $defaults = [
        'GET /v1/databases/*' => ['object' => 'database', 'data_sources' => [['id' => 'ds-1', 'name' => 'Tasks']]],
        'GET /v1/data_sources/*' => ['object' => 'data_source', 'properties' => ['Name' => ['name' => 'Name', 'type' => 'title']]],
        'PATCH /v1/data_sources/*' => ['object' => 'data_source'],
        'POST /v1/pages' => ['object' => 'page', 'id' => 'page-1'],
        'PATCH /v1/pages/*' => ['object' => 'page', 'id' => 'page-1'],
    ];

    Http::swap(new HttpFactory);

    Http::fake(function (Request $request) use ($routes, $defaults) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        foreach ($routes + $defaults as $pattern => $response) {
            if (fnmatch($pattern, $key)) {
                return $response instanceof PromiseInterface || $response instanceof Response
                    ? $response
                    : Http::response($response);
            }
        }

        return Http::response(['object' => 'error', 'code' => 'object_not_found', 'message' => 'unexpected '.$key], 404);
    });
}

/**
 * Link a project to the fake Notion database, with the integration on.
 */
function linkToNotion(Project $project): Project
{
    configureNotion();

    $project->forceFill([
        'notion_database_id' => '0123456789abcdef0123456789abcdef',
        'notion_data_source_id' => 'ds-1',
        'notion_title_property' => 'Name',
    ])->save();

    return $project;
}

/**
 * A new project already linked to the fake Notion database.
 *
 * @param  array<string, mixed>  $attributes
 */
function notionProject(array $attributes = []): Project
{
    return linkToNotion(Project::factory()->create(['key' => 'MIR', ...$attributes]));
}
