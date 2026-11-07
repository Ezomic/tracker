<?php

declare(strict_types=1);

it('renders the sign-in options on the login page', function () {
    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertSee('Log in with a code emailed to you')
        ->assertNoJavascriptErrors();
});

it('shows no SSO sign-in when the ID integration is not configured', function () {
    config()->set('services.thijssensoftware.client_id', null);

    visit('/login')
        ->assertSee('Sign in with an email code or a passkey')
        ->assertDontSee('Sign in with Thijssensoftware')
        ->assertNotPresent('a[href$="/auth/sso/redirect"]')
        ->assertNoJavascriptErrors();

    visit('/')
        ->assertSee('Sign in with an email code or a passkey')
        ->assertDontSee('One sign-in for all of them')
        ->assertNoJavascriptErrors();
});

it('offers SSO sign-in when the ID integration is configured', function () {
    configureSso();

    visit('/login')
        ->assertSee('Sign in with Thijssensoftware')
        ->assertPresent('a[href$="/auth/sso/redirect"]')
        ->assertNoJavascriptErrors();

    visit('/')
        ->assertSee('One sign-in for all of them')
        ->assertNoJavascriptErrors();
});

it('renders the marketing landing page', function () {
    $page = visit('/');

    $page->assertSee('Your issues.')
        ->assertNoJavascriptErrors();
});
