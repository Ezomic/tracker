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

function fakeIdResponses(array $applications): void
{
    Http::fake([
        'id.example.test/oauth/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 900,
        ]),
        'id.example.test/api/portal/apps' => Http::response(['applications' => $applications]),
    ]);
}

it('returns the apps for a user and marks the current one', function () {
    fakeIdResponses([
        ['slug' => 'billr', 'name' => 'Billr', 'initials' => 'B', 'accent' => '#111', 'launch_url' => 'https://billr.test'],
        ['slug' => 'tracker', 'name' => 'Tracker', 'initials' => 'T', 'accent' => '#222', 'launch_url' => 'https://tracker.test'],
    ]);

    $user = User::factory()->create();

    $apps = app(IdPortalClient::class)->appsFor($user);

    expect($apps)->toHaveCount(2)
        ->and($apps[0]['slug'])->toBe('billr')
        ->and($apps[0]['current'])->toBeFalse()
        ->and($apps[1]['slug'])->toBe('tracker')
        ->and($apps[1]['current'])->toBeTrue();
});

it('returns an empty list when ID is unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));

    $user = User::factory()->create();

    expect(app(IdPortalClient::class)->appsFor($user))->toBe([]);
});

it('returns an empty list when the client is not configured', function () {
    config()->set('services.thijssensoftware.client_id', null);

    $user = User::factory()->create();

    expect(app(IdPortalClient::class)->appsFor($user))->toBe([]);

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
