<?php

declare(strict_types=1);

use App\Models\User;

it('redirects guests from the api tokens page', function () {
    $this->get(route('api-tokens.index'))->assertRedirect(route('login'));
});

it('lists the users tokens without exposing the secret', function () {
    $user = User::factory()->create();
    $user->createToken('existing');

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'existing')
            ->missing('tokens.0.token')
            ->where('createdToken', null)
        );
});

it('creates a token and reveals the plaintext exactly once', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('api-tokens.store'), ['name' => 'CI pipeline']);

    $response->assertRedirect(route('api-tokens.index'));

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('CI pipeline');

    // The redirect target reveals the plaintext once.
    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn ($page) => $page
            ->where('createdToken.name', 'CI pipeline')
            ->where('createdToken.plainText', fn (string $value): bool => str_contains($value, '|')));

    // A subsequent load no longer carries the secret.
    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn ($page) => $page->where('createdToken', null));
});

it('requires a token name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(0);
});

it('rejects a duplicate token name for the same user', function () {
    $user = User::factory()->create();
    $user->createToken('CI');

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'CI'])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(1);
});

it('allows the same token name for different users', function () {
    $first = User::factory()->create();
    $first->createToken('CI');
    $second = User::factory()->create();

    $this->actingAs($second)
        ->post(route('api-tokens.store'), ['name' => 'CI'])
        ->assertSessionHasNoErrors();

    expect($second->tokens()->count())->toBe(1);
});

it('revokes the users own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('CI')->accessToken;

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', $token->getKey()))
        ->assertRedirect(route('api-tokens.index'));

    expect($user->tokens()->count())->toBe(0);
});

it('cannot revoke another users token', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherToken = $other->createToken('theirs')->accessToken;

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', $otherToken->getKey()))
        ->assertRedirect(route('api-tokens.index'));

    expect($other->tokens()->count())->toBe(1);
});
