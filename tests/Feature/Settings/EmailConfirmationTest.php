<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureEmailConfirmed;
use App\Mail\LoginCodeMail;
use App\Models\User;
use App\Services\OneTimeCodeService;
use Illuminate\Support\Facades\Mail;

it('gates passkey registration options behind email confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/user/passkeys/options')
        ->assertStatus(423);
});

it('allows passkey registration options once email is confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([EnsureEmailConfirmed::SESSION_KEY => time()])
        ->getJson('/user/passkeys/options')
        ->assertStatus(200);
});

it('sends a confirmation code and renders the confirm page', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/security/confirm')
        ->assertInertia(fn ($page) => $page->component('settings/ConfirmEmail')
            ->where('email', $user->email));

    Mail::assertSent(LoginCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('confirms with the correct code and sets the session marker', function () {
    $user = User::factory()->create();
    $code = app(OneTimeCodeService::class)->issue("email-confirm:{$user->id}");

    $this->actingAs($user)
        ->post('/settings/security/confirm', ['code' => $code])
        ->assertRedirect(route('security.edit'));

    expect(session(EnsureEmailConfirmed::SESSION_KEY))->not->toBeNull();
});

it('rejects an incorrect confirmation code', function () {
    $user = User::factory()->create();
    app(OneTimeCodeService::class)->issue("email-confirm:{$user->id}");

    $this->actingAs($user)
        ->post('/settings/security/confirm', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(session(EnsureEmailConfirmed::SESSION_KEY))->toBeNull();
});

it('exposes needsEmailConfirmation on the security page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/security')
        ->assertInertia(fn ($page) => $page->where('needsEmailConfirmation', true));

    $this->actingAs($user)
        ->withSession([EnsureEmailConfirmed::SESSION_KEY => time()])
        ->get('/settings/security')
        ->assertInertia(fn ($page) => $page->where('needsEmailConfirmation', false));
});
