<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config(['id-client.logout_secret' => 'test-logout-secret']);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn((new SocialiteUser)->setRaw([
        'sub' => '42',
        'name' => 'Robbin Thijssen',
        'email' => 'robbin@example.com',
        'applications' => ['tracker'],
    ])->map([
        'id' => '42',
        'name' => 'Robbin Thijssen',
        'email' => 'robbin@example.com',
    ]));

    Socialite::shouldReceive('driver')->with('thijssensoftware')->andReturn($provider);
});

function rememberCookieFromSsoSignIn(): string
{
    $cookie = test()->get(route('sso.callback'))->getCookie(Auth::guard('web')->getRecallerName());

    expect($cookie)->not->toBeNull();

    return (string) $cookie->getValue();
}

/**
 * The same browser after its session has idled out: the remember-me cookie is
 * all it still has.
 */
function returnWithOnlyRememberCookie(string $cookie): TestResponse
{
    test()->flushSession();
    Auth::forgetGuards();

    return test()->withCookie(Auth::guard('web')->getRecallerName(), $cookie)->get(route('dashboard'));
}

it('signs the user back in from the remember-me cookie alone', function () {
    $cookie = rememberCookieFromSsoSignIn();

    returnWithOnlyRememberCookie($cookie)->assertOk();

    $this->assertAuthenticatedAs(User::where('idp_id', '42')->sole());
});

it('refuses the remember-me cookie once ID signs the user out', function () {
    $cookie = rememberCookieFromSsoSignIn();

    $body = json_encode(['event' => 'logout', 'sub' => '42', 'issued_at' => Carbon::now()->getTimestamp()], JSON_THROW_ON_ERROR);

    $this->call('POST', route('sso.logout'), server: [
        'HTTP_X_ID_SIGNATURE' => hash_hmac('sha256', $body, 'test-logout-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], content: $body)->assertOk();

    returnWithOnlyRememberCookie($cookie)->assertRedirect(route('login'));

    $this->assertGuest();
});
