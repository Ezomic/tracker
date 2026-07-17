<?php

declare(strict_types=1);

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('renders the registration page', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Register'));
});

it('creates an unverified account and emails a code', function () {
    Mail::fake();

    $this->post('/register', ['name' => 'Ada Lovelace', 'email' => 'Ada@Example.com'])
        ->assertRedirect(route('login.code.verify'));

    $user = User::query()->where('email', 'ada@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email_verified_at)->toBeNull();

    Mail::assertSent(LoginCodeMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
    expect(session('login-code-email'))->toBe('ada@example.com');
});

it('does not create a duplicate account for an existing email but still emails a code', function () {
    Mail::fake();
    $existing = User::factory()->create(['email' => 'taken@example.com', 'name' => 'Original']);

    $this->post('/register', ['name' => 'Impersonator', 'email' => 'taken@example.com'])
        ->assertRedirect(route('login.code.verify'));

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Original');

    Mail::assertSent(LoginCodeMail::class, fn ($mail) => $mail->hasTo('taken@example.com'));
});

it('requires a name and a valid email', function () {
    $this->post('/register', ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);

    expect(User::query()->count())->toBe(0);
});

it('verifies the email and logs in a new registrant', function () {
    Mail::fake();
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com']);

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    Cache::put('login-code:ada@example.com', ['hash' => Hash::make('123456'), 'attempts' => 0], now()->addMinutes(10));

    $this->post('/login/code/verify', ['code' => '123456'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('lands a brand-new registrant on an empty dashboard with onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasProjects', false));
});

it('redirects an authenticated user away from registration', function () {
    $this->actingAs(User::factory()->create())
        ->get('/register')
        ->assertRedirect();
});
