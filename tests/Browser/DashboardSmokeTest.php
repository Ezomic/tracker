<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;

it('lets a signed-in user switch dashboard views', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    $user = member($project);
    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);

    $this->actingAs($user);

    $page = visit('/dashboard');

    // The Focus view is the default.
    $page->assertSee('Needs your attention')
        ->assertSee('Active tickets by project')
        ->assertDontSee('Opened vs completed');

    // Switching to Metrics is client-side; its trend chart + WIP tile appear.
    $page->click('Metrics')
        ->assertSee('Opened vs completed')
        ->assertSee('WIP load');

    $page->assertNoJavascriptErrors();
});
