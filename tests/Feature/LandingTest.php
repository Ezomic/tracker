<?php

declare(strict_types=1);

it('brands the landing page with the app name and links no sign-in suite when SSO is off', function (?string $value) {
    config()->set('app.operator_name', $value);
    config()->set('services.thijssensoftware.base_url', $value);

    $this->get('https://issues.example.com/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Landing')
            ->where('operatorName', 'Tracker')
            ->where('host', 'issues.example.com')
            ->where('identityUrl', null));
})->with('blank');

it('brands the landing page with the operator and links the sign-in suite when SSO is on', function () {
    configureSso();
    config()->set('app.operator_name', 'Acme Hosting');

    $this->get('https://issues.example.com/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Landing')
            ->where('operatorName', 'Acme Hosting')
            ->where('host', 'issues.example.com')
            ->where('identityUrl', 'https://id.example.test'));
});
