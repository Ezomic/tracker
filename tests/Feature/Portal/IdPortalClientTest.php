<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Portal\IdPortalClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.thijssensoftware', [
        'base_url' => 'https://id.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'slug' => 'tracker',
        'portal_cache_ttl' => 300,
    ]);

    Cache::flush();
});

function fakeIdResponses(array $applications, array $categories = []): void
{
    Http::fake([
        'id.example.test/oauth/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 900,
        ]),
        'id.example.test/api/portal/apps' => Http::response([
            'applications' => $applications,
            'categories' => $categories,
        ]),
    ]);
}

it('returns the apps for a user and marks the current one', function () {
    fakeIdResponses([
        ['slug' => 'billr', 'name' => 'Billr', 'initials' => 'B', 'accent' => '#111', 'launch_url' => 'https://billr.test'],
        ['slug' => 'tracker', 'name' => 'Tracker', 'initials' => 'T', 'accent' => '#222', 'launch_url' => 'https://tracker.test'],
    ]);

    $user = User::factory()->create();

    $result = app(IdPortalClient::class)->appsFor($user);

    expect($result['apps'])->toHaveCount(2)
        ->and($result['categories'])->toBe([])
        ->and($result['apps'][0]['slug'])->toBe('billr')
        ->and($result['apps'][0]['current'])->toBeFalse()
        ->and($result['apps'][1]['slug'])->toBe('tracker')
        ->and($result['apps'][1]['current'])->toBeTrue();
});

it('returns categorized apps as their own groups', function () {
    fakeIdResponses(
        [
            ['slug' => 'billr', 'name' => 'Billr', 'initials' => 'B', 'accent' => '#111', 'launch_url' => 'https://billr.test'],
        ],
        [
            [
                'category' => 'Games',
                'apps' => [
                    ['slug' => 'chess', 'name' => 'Chess', 'initials' => 'C', 'accent' => null, 'launch_url' => 'https://chess.test'],
                ],
            ],
        ],
    );

    $user = User::factory()->create();

    $result = app(IdPortalClient::class)->appsFor($user);

    expect($result['apps'])->toHaveCount(1)
        ->and($result['categories'])->toHaveCount(1)
        ->and($result['categories'][0]['category'])->toBe('Games')
        ->and($result['categories'][0]['apps'])->toHaveCount(1)
        ->and($result['categories'][0]['apps'][0]['slug'])->toBe('chess')
        ->and($result['categories'][0]['apps'][0]['current'])->toBeFalse();
});

it('returns an empty result when ID is unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));

    $user = User::factory()->create();

    expect(app(IdPortalClient::class)->appsFor($user))->toBe(['apps' => [], 'categories' => []]);
});

it('returns an empty result when the client is not configured', function () {
    config()->set('services.thijssensoftware.client_id', null);

    $user = User::factory()->create();

    expect(app(IdPortalClient::class)->appsFor($user))->toBe(['apps' => [], 'categories' => []]);

    Http::assertNothingSent();
});

it('caches the result for a user', function () {
    fakeIdResponses([
        ['slug' => 'zero', 'name' => 'Zero', 'initials' => 'Z', 'accent' => null, 'launch_url' => 'https://zero.test'],
    ]);

    $user = User::factory()->create();
    $client = app(IdPortalClient::class);

    $client->appsFor($user);
    $client->appsFor($user);

    // One token call + one apps call, reused from cache on the second invocation.
    Http::assertSentCount(2);
});
