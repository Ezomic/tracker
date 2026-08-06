<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

it('gives reads a higher budget than writes', function () {
    $read = RateLimiter::limiter('api-read');
    $write = RateLimiter::limiter('api-write');

    $user = User::factory()->create();
    $request = request();
    $request->setUserResolver(fn () => $user);

    /** @var Limit $readLimit */
    $readLimit = $read($request);
    /** @var Limit $writeLimit */
    $writeLimit = $write($request);

    expect($readLimit->maxAttempts)->toBe(300)
        ->and($writeLimit->maxAttempts)->toBe(60)
        ->and($readLimit->key)->toBe($writeLimit->key);
});

it('keys the limit per user so one caller cannot exhaust another', function () {
    $limiter = RateLimiter::limiter('api-read');

    $first = request();
    $first->setUserResolver(fn () => User::factory()->create());
    $second = request();
    $second->setUserResolver(fn () => User::factory()->create());

    expect($limiter($first)->key)->not->toBe($limiter($second)->key);
});

it('falls back to the ip when there is no authenticated user', function () {
    $limiter = RateLimiter::limiter('api-read');

    $request = request();
    $request->setUserResolver(fn () => null);

    expect($limiter($request)->key)->toStartWith('ip:');
});

it('throttles reads and writes on separate buckets', function () {
    $user = member(Project::factory()->create(['key' => 'THI']));

    $this->actingAs($user, 'sanctum')->getJson('/api/issues')->assertOk();

    // The write bucket is untouched by the read above, so a write still has its
    // full budget: the response is a validation error, never a 429.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues', [])
        ->assertStatus(422);
});
