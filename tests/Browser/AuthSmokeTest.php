<?php

declare(strict_types=1);

it('renders the sign-in options on the login page', function () {
    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertSee('Log in with a code emailed to you')
        ->assertNoJavascriptErrors();
});

it('renders the marketing landing page', function () {
    $page = visit('/');

    $page->assertSee('Your issues.')
        ->assertNoJavascriptErrors();
});
