<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\SavedView;
use App\Models\User;

it('saves the current filters as a named view', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);

    $this->actingAs($user)->post('/saved-views', [
        'name' => 'Hot backlog',
        'project_id' => null,
        'criteria' => ['status' => 'backlog', 'priority' => 'high', 'search' => ''],
    ])->assertRedirect();

    $view = SavedView::query()->firstOrFail();
    expect($view->name)->toBe('Hot backlog')
        ->and($view->user_id)->toBe($user->id)
        ->and($view->project_id)->toBeNull()
        // The empty search is dropped so the stored criteria stays minimal.
        ->and($view->criteria)->toBe(['status' => 'backlog', 'priority' => 'high']);
});

it('scopes a saved view to a project when given one', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);

    $this->actingAs($user)->post('/saved-views', [
        'name' => 'THI review',
        'project_id' => $project->id,
        'criteria' => ['status' => 'in_review'],
    ])->assertRedirect();

    expect(SavedView::query()->firstOrFail()->project_id)->toBe($project->id);
});

it('shares only the user global and current-project views on the list', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $other = User::factory()->create();

    SavedView::query()->create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Mine global', 'criteria' => []]);
    SavedView::query()->create(['user_id' => $user->id, 'project_id' => $project->id, 'name' => 'Mine project', 'criteria' => []]);
    SavedView::query()->create(['user_id' => $other->id, 'project_id' => null, 'name' => 'Theirs', 'criteria' => []]);

    $this->actingAs($user)->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('savedViews', 1)
            ->where('savedViews.0.name', 'Mine global')
        );
});

it('deletes the user own view', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $view = SavedView::query()->create(['user_id' => $user->id, 'project_id' => null, 'name' => 'Temp', 'criteria' => []]);

    $this->actingAs($user)->delete("/saved-views/{$view->id}")->assertRedirect();

    expect(SavedView::query()->count())->toBe(0);
});

it('refuses to delete another user view', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $other = User::factory()->create();
    $view = SavedView::query()->create(['user_id' => $other->id, 'project_id' => null, 'name' => 'Theirs', 'criteria' => []]);

    $this->actingAs($user)->delete("/saved-views/{$view->id}")->assertForbidden();

    expect(SavedView::query()->count())->toBe(1);
});
