<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\CodeVerification;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEmailConfirmed;
use App\Http\Requests\Settings\ConfirmEmailRequest;
use App\Mail\LoginCodeMail;
use App\Services\OneTimeCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfirmationController extends Controller
{
    public function __construct(private readonly OneTimeCodeService $codes) {}

    public function create(Request $request): Response
    {
        $user = $this->currentUser($request);

        $code = $this->codes->issue($this->cacheKey($user->id));

        Mail::to($user->email)->send(new LoginCodeMail($code));

        return Inertia::render('settings/ConfirmEmail', [
            'email' => $user->email,
        ]);
    }

    public function store(ConfirmEmailRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        $result = $this->codes->verify($this->cacheKey($user->id), $request->string('code')->toString());

        if ($result === CodeVerification::Expired) {
            return back()->withErrors(['code' => 'This code has expired. Request a new one.']);
        }

        if ($result === CodeVerification::Incorrect) {
            return back()->withErrors(['code' => 'That code is incorrect.']);
        }

        $request->session()->put(EnsureEmailConfirmed::SESSION_KEY, time());

        return redirect()->intended(route('security.edit'));
    }

    private function cacheKey(int $userId): string
    {
        return "email-confirm:{$userId}";
    }
}
