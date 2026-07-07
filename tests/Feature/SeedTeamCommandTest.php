<?php

declare(strict_types=1);

use App\Models\Team;

it('creates a new team with the given next_number', function () {
    $this->artisan('teams:seed', ['key' => 'thi', 'name' => 'Thijssen Software', 'next_number' => 276])
        ->assertSuccessful();

    $team = Team::query()->where('key', 'THI')->first();
    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('Thijssen Software')
        ->and($team->next_number)->toBe(276);
});

it('raises an existing counter when the requested number is higher', function () {
    Team::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software', 'next_number' => 100]);

    $this->artisan('teams:seed', ['key' => 'THI', 'name' => 'Thijssen Software', 'next_number' => 276])
        ->assertSuccessful();

    expect(Team::query()->where('key', 'THI')->first()->next_number)->toBe(276);
});

it('never lowers an existing counter', function () {
    Team::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software', 'next_number' => 300]);

    $this->artisan('teams:seed', ['key' => 'THI', 'name' => 'Thijssen Software', 'next_number' => 50])
        ->assertSuccessful();

    expect(Team::query()->where('key', 'THI')->first()->next_number)->toBe(300);
});

it('defaults next_number to 0 when omitted', function () {
    $this->artisan('teams:seed', ['key' => 'BILLR', 'name' => 'Billr'])
        ->assertSuccessful();

    expect(Team::query()->where('key', 'BILLR')->first()->next_number)->toBe(0);
});
