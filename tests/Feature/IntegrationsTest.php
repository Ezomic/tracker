<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\Integrations;
use Illuminate\Support\Facades\Http;

it('turns SSO on when the ID url, client id and client secret are all set', function () {
    configureSso();

    expect(app(Integrations::class)->sso())->toBeTrue();
});

it('turns SSO off when any ID setting is blank', function (string $key, ?string $value) {
    configureSso();
    config()->set("services.thijssensoftware.{$key}", $value);

    expect(app(Integrations::class)->sso())->toBeFalse();
})->with(['base_url', 'client_id', 'client_secret'])->with('blank');

it('shares which integrations are on with every page', function () {
    configureSso();

    $this->get('/login')
        ->assertInertia(fn ($page) => $page
            ->where('integrations', ['sso' => true, 'notion' => false]));
});

it('shares the integrations as off when they are not configured', function (?string $value) {
    configureSso();
    config()->set('services.thijssensoftware.client_id', $value);

    $this->get('/login')
        ->assertInertia(fn ($page) => $page
            ->where('integrations', ['sso' => false, 'notion' => false]));
})->with('blank');

it('leaves the portal switcher empty and calls nobody when ID is not configured', function (?string $value) {
    Http::fake();
    configureSso();
    config()->set('services.thijssensoftware.client_secret', $value);
    $project = Project::factory()->create();

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('integrations.sso', false)
            ->where('portalApps', [])
            ->where('portalCategories', []));

    Http::assertNothingSent();
})->with('blank');

it('turns Notion on only when the API key is set', function (?string $value, bool $expected) {
    config()->set('services.notion.api_key', $value);

    expect(app(Integrations::class)->notion())->toBe($expected);
})->with([
    'set' => ['secret_abc', true],
    'unset' => [null, false],
    'empty string' => ['', false],
    'whitespace only' => ['   ', false],
]);

it('shares Notion as on when the API key is set', function () {
    config()->set('services.notion.api_key', 'secret_abc');

    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('integrations.notion', true));
});
