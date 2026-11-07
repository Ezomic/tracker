<?php

declare(strict_types=1);

use Laravel\Socialite\Facades\Socialite;

it('answers 404 on the SSO routes when the ID client is not configured', function (string $key, ?string $value) {
    configureSso();
    config()->set("services.thijssensoftware.{$key}", $value);
    Socialite::shouldReceive('driver')->never();

    $this->get('/auth/sso/redirect')->assertNotFound();
    $this->get('/auth/sso/callback')->assertNotFound();
    $this->assertGuest();
})->with(['base_url', 'client_id', 'client_secret'])->with('blank');

it('hides the SSO option on the login page when the ID client is not configured', function (?string $value) {
    configureSso();
    config()->set('services.thijssensoftware.client_id', $value);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->where('integrations.sso', false));
})->with('blank');

it('offers SSO on the login page when the ID client is configured', function () {
    configureSso();

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->where('integrations.sso', true));
});
