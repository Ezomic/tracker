<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;

it('renders the unified dashboard for a signed-in user', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    $user = member($project);
    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
    ]);

    $this->actingAs($user);

    $page = visit('/dashboard');

    $page->assertSee('Needs your attention')
        ->assertSee('Active tickets by project')
        ->assertSee('Completed per week')
        ->assertSee('Tickets done by project, week by week')
        ->assertSee("This week's time");

    $page->assertNoJavascriptErrors();
});
