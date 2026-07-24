<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Support\Facades\Http;

it('opens the portal app switcher from the navbar', function () {
    config()->set('services.thijssensoftware', [
        'base_url' => 'https://id.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'slug' => 'tracker',
        'portal_cache_ttl' => 300,
    ]);

    Http::fake([
        'id.example.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 900]),
        'id.example.test/api/portal/apps' => Http::response(['applications' => [
            ['slug' => 'billr', 'name' => 'Billr', 'initials' => 'B', 'accent' => '#3B82F6', 'launch_url' => 'https://billr.test'],
            ['slug' => 'finance', 'name' => 'Finance', 'initials' => 'F', 'accent' => '#14B8A6', 'launch_url' => 'https://finance.test'],
            ['slug' => 'tracker', 'name' => 'Tracker', 'initials' => 'T', 'accent' => '#7C6BF0', 'launch_url' => 'https://tracker.test'],
            ['slug' => 'zero', 'name' => 'Zero', 'initials' => 'Z', 'accent' => '#3B82F6', 'launch_url' => 'https://zero.test'],
        ]]),
    ]);

    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    $user = member($project);

    $this->actingAs($user);

    $page = visit('/dashboard');

    $page->click('[aria-label="Apps"]')
        ->assertSee('Your apps')
        ->assertSee('Billr')
        ->assertSee('Finance')
        ->assertSee('Zero')
        ->assertSee('Current')
        ->screenshot(false, 'portal-switcher-modal')
        ->assertNoJavascriptErrors();
});
