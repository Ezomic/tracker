<?php

declare(strict_types=1);

it('pins the application name to Tracker regardless of the environment', function () {
    expect(config('app.name'))->toBe('Tracker')
        ->and(config('mail.from.name'))->toBe('Tracker');
});

it('shares the Tracker name to the frontend', function () {
    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('name', 'Tracker'));
});
