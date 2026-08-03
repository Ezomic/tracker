<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $user->tokens()
                ->latest()
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'createdAtDiff' => $token->created_at?->diffForHumans(),
                    'lastUsedAtDiff' => $token->last_used_at?->diffForHumans(),
                ])
                ->all(),
            // Flashed once by store(), through the redirect, then gone.
            'createdToken' => $request->session()->get('createdToken'),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        $token = $user->createToken($request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token created.')]);

        return to_route('api-tokens.index')->with('createdToken', [
            'name' => $token->accessToken->name,
            'plainText' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        // Scoped to the acting user's own tokens: another user's id deletes nothing.
        $this->currentUser($request)->tokens()->whereKey($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token revoked.')]);

        return to_route('api-tokens.index');
    }
}
