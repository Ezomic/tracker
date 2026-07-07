<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreLoginCodeRequest;
use App\Http\Requests\Auth\VerifyLoginCodeRequest;
use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailLoginCodeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): Response
    {
        return Inertia::render('auth/EmailCode');
    }

    public function store(StoreLoginCodeRequest $request): RedirectResponse
    {
        $email = Str::lower($request->validated('email'));

        if (User::query()->where('email', $email)->exists()) {
            $code = (string) random_int(100000, 999999);

            Cache::put($this->cacheKey($email), [
                'hash' => Hash::make($code),
                'attempts' => 0,
            ], now()->addMinutes(10));

            Mail::to($email)->send(new LoginCodeMail($code));
        }

        $request->session()->put('login-code-email', $email);

        return to_route('login.code.verify');
    }

    public function verify(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login-code-email')) {
            return to_route('login.code.create');
        }

        return Inertia::render('auth/EmailCodeVerify', [
            'email' => $request->session()->get('login-code-email'),
        ]);
    }

    public function authenticate(VerifyLoginCodeRequest $request): RedirectResponse
    {
        $email = $request->session()->get('login-code-email');

        if ($email === null) {
            return to_route('login.code.create');
        }

        $entry = Cache::get($this->cacheKey($email));

        if ($entry === null || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->cacheKey($email));

            return back()->withErrors(['code' => 'This code has expired. Request a new one.']);
        }

        if (! Hash::check($request->validated('code'), $entry['hash'])) {
            Cache::put($this->cacheKey($email), [
                ...$entry,
                'attempts' => $entry['attempts'] + 1,
            ], now()->addMinutes(10));

            return back()->withErrors(['code' => 'That code is incorrect.']);
        }

        $user = User::query()->where('email', $email)->firstOrFail();

        Cache::forget($this->cacheKey($email));
        $request->session()->forget('login-code-email');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function cacheKey(string $email): string
    {
        return "login-code:{$email}";
    }
}
