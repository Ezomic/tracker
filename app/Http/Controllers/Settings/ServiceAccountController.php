<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\CreateServiceAccountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreServiceAccountRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ServiceAccountController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): Response
    {
        $user = $this->currentUser($request);
        $organization = $current->require($user);

        $this->authorize('manageMembers', $organization);

        return Inertia::render('settings/ServiceAccounts', [
            'accounts' => $this->accountsFor($organization),
            'projects' => Project::query()
                ->visibleTo($user)
                ->where('organization_id', $organization->id)
                ->orderBy('key')
                ->get(['id', 'key', 'name'])
                ->map(fn (Project $project): array => [
                    'key' => $project->key,
                    'name' => $project->name,
                ])
                ->all(),
            'abilities' => CreateServiceAccountAction::ABILITIES,
            // Flashed once by store(), through the redirect, then gone.
            'createdToken' => $request->session()->get('createdToken'),
        ]);
    }

    public function store(StoreServiceAccountRequest $request, CurrentOrganization $current, CreateServiceAccountAction $action): RedirectResponse
    {
        $user = $this->currentUser($request);
        $organization = $current->require($user);

        $this->authorize('manageMembers', $organization);

        /** @var list<string> $keys */
        $keys = $request->validated('projects');

        $projects = Project::query()
            ->visibleTo($user)
            ->where('organization_id', $organization->id)
            ->whereIn('key', $keys)
            ->get();

        // A key the caller cannot see is silently absent from the grant, which
        // would create an account with less access than asked for. Refuse.
        abort_if($projects->count() !== count(array_unique($keys)), 403);

        $token = $action->handle($organization, $request->string('name')->toString(), array_values($projects->all()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service account created.')]);

        return to_route('service-accounts.index')->with('createdToken', [
            'name' => $token->accessToken->name,
            'plainText' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, CurrentOrganization $current, User $serviceAccount): RedirectResponse
    {
        $organization = $current->require($this->currentUser($request));

        $this->authorize('manageMembers', $organization);

        // Scoped to this organization's own service accounts: a human user's id,
        // or an account from another org, is a 404 rather than a deletion.
        abort_unless($serviceAccount->is_service && $organization->hasMember($serviceAccount), 404);

        $serviceAccount->tokens()->delete();
        $serviceAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service account revoked.')]);

        return to_route('service-accounts.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountsFor(Organization $organization): array
    {
        $rows = User::query()
            ->serviceAccounts()
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($organization->id))
            ->with(['projects:id,key', 'tokens'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'projects' => Cast::strings($account->projects->pluck('key')->all()),
                'createdAtDiff' => $account->created_at?->diffForHumans(),
                'lastUsedAtDiff' => $this->lastUsedAt($account)?->diffForHumans(),
            ])
            ->all();

        return array_values($rows);
    }

    private function lastUsedAt(User $account): ?Carbon
    {
        return $account->tokens
            ->pluck('last_used_at')
            ->filter(fn (mixed $value): bool => $value instanceof Carbon)
            ->max();
    }
}
