<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTeamRequest;
use App\Http\Requests\Settings\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Teams', [
            'teams' => Team::withCount('issues')
                ->orderBy('key')
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'key' => $team->key,
                    'name' => $team->name,
                    'issuesCount' => $team->issues_count,
                    'keyLocked' => $team->issues_count > 0,
                ]),
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        Team::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.index');
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.index');
    }
}
