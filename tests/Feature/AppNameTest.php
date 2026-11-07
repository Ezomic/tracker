<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Invitation;
use App\Providers\AppServiceProvider;

function resolveAppName(?string $appName, ?string $mailFromName): void
{
    config()->set('app.name', $appName);
    config()->set('mail.from.name', $mailFromName);

    (new AppServiceProvider(app()))->register();
}

it('names the product Tracker when APP_NAME is blank', function (?string $value) {
    resolveAppName($value, $value);

    expect(config('app.name'))->toBe('Tracker')
        ->and(config('mail.from.name'))->toBe('Tracker');
})->with('blank');

it('names the product Tracker when APP_NAME is still the framework default', function () {
    resolveAppName('Laravel', 'Laravel');

    expect(config('app.name'))->toBe('Tracker')
        ->and(config('mail.from.name'))->toBe('Tracker');
});

it('uses the APP_NAME an operator chose for the product and the mail sender', function () {
    resolveAppName('Acme Issues', 'Acme Issues');

    expect(config('app.name'))->toBe('Acme Issues')
        ->and(config('mail.from.name'))->toBe('Acme Issues');
});

it('keeps a mail sender name the operator set on its own', function () {
    resolveAppName('Acme Issues', 'Acme Support');

    expect(config('mail.from.name'))->toBe('Acme Support');
});

it('shares the application name with the frontend', function () {
    config()->set('app.name', 'Acme Issues');

    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('name', 'Acme Issues'));
});

it('names the application in the invitation email', function () {
    config()->set('app.name', 'Acme Issues');
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $invitation = Invitation::query()->create([
        'organization_id' => $organization->id,
        'email' => 'new@example.com',
        'role' => OrganizationRole::Member->value,
        'token' => Invitation::hashToken('token'),
        'invited_by_id' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    (new OrganizationInvitationMail($invitation, 'https://example.com/accept'))
        ->assertSeeInHtml('on Acme Issues as');
});
