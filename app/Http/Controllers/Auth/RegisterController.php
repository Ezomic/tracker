<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegistrationRequest;
use App\Mail\LoginCodeMail;
use App\Models\User;
use App\Services\OneTimeCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private readonly OneTimeCodeService $codes) {}

    public function create(Request $request): Response
    {
        return Inertia::render('auth/Register', [
            // Prefilled when arriving from a project invitation link.
            'email' => $request->query('email'),
        ]);
    }

    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $email = Str::lower($request->validated('email'));

        // Enumeration-safe: create the account only if the email is new, but
        // always issue a code and land on the verify screen, so an existing
        // email simply logs in instead of leaking that it is taken.
        if (! User::query()->where('email', $email)->exists()) {
            User::create([
                'name' => $request->validated('name'),
                'email' => $email,
            ]);
        }

        $code = $this->codes->issue("login-code:{$email}");

        Mail::to($email)->send(new LoginCodeMail($code));

        $request->session()->put('login-code-email', $email);

        return to_route('login.code.verify');
    }
}
