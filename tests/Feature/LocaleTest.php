<?php

declare(strict_types=1);

use App\Models\User;

it('persists a supported locale and rejects an unsupported one', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->patch('/settings/locale', ['locale' => 'nl'])
        ->assertRedirect();
    expect($user->fresh()->locale)->toBe('nl');

    $this->actingAs($user)->patch('/settings/locale', ['locale' => 'de'])
        ->assertSessionHasErrors('locale');
    expect($user->fresh()->locale)->toBe('nl');
});

it('applies the user locale to the request via the SetLocale middleware', function () {
    $user = User::factory()->create(['locale' => 'nl']);

    $this->actingAs($user)->get('/settings/profile')->assertOk();

    expect(app()->getLocale())->toBe('nl');
});

it('shares the locale as an Inertia prop', function () {
    $user = User::factory()->create(['locale' => 'nl']);

    $this->actingAs($user)->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('locale', 'nl'));
});

it('translates server messages to Dutch', function () {
    app()->setLocale('nl');

    expect(__('Project created.'))->toBe('Project aangemaakt.')
        ->and(__('Issue archived.'))->toBe('Ticket gearchiveerd.');
});

it('keeps the en and nl frontend catalogs in key parity', function () {
    $flatten = function (array $data, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $keys = is_array($value)
                ? [...$keys, ...$flatten($value, $path)]
                : [...$keys, $path];
        }

        return $keys;
    };

    $en = $flatten(json_decode((string) file_get_contents(resource_path('js/lang/en.json')), true));
    $nl = $flatten(json_decode((string) file_get_contents(resource_path('js/lang/nl.json')), true));

    sort($en);
    sort($nl);

    expect($nl)->toBe($en);
});
