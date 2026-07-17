<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

it('lists a project templates to any member', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $bug = IssueTemplate::factory()->for($project)->create(['name' => 'Bug report']);
    $bug->labels()->attach(Label::factory()->create(['name' => 'bug']));

    $this->actingAs(member($project, ProjectRole::Member))
        ->get('/projects/THI/templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Templates')
            ->has('templates', 1)
            ->where('templates.0.name', 'Bug report')
            ->has('templates.0.labelIds', 1)
            ->where('canManage', false)
        );
});

it('marks owners and admins as able to manage', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project, ProjectRole::Admin))
        ->get('/projects/THI/templates')
        ->assertInertia(fn ($page) => $page->where('canManage', true));
});

it('forbids a non-member from viewing templates', function () {
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs(User::factory()->create())
        ->get('/projects/THI/templates')
        ->assertForbidden();
});

it('creates a template with defaults and labels', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $label = Label::factory()->create();

    $this->actingAs(member($project))
        ->post('/projects/THI/templates', [
            'name' => 'Bug report',
            'description' => "## Steps\n## Expected",
            'type' => 'fix',
            'priority' => 'high',
            'labels' => [$label->id],
        ])
        ->assertRedirect();

    $template = IssueTemplate::query()->firstOrFail();
    expect($template->name)->toBe('Bug report')
        ->and($template->type)->toBe(IssueType::Fix)
        ->and($template->priority)->toBe(IssuePriority::High)
        ->and($template->project_id)->toBe($project->id)
        ->and($template->labels->pluck('id')->all())->toBe([$label->id]);
});

it('allows a template with no defaults', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))
        ->post('/projects/THI/templates', [
            'name' => 'Blank-ish',
            'description' => 'Just a body',
            'type' => '',
            'priority' => '',
        ])
        ->assertRedirect();

    $template = IssueTemplate::query()->firstOrFail();
    expect($template->type)->toBeNull()
        ->and($template->priority)->toBeNull();
});

it('forbids a plain member from creating a template', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project, ProjectRole::Member))
        ->post('/projects/THI/templates', ['name' => 'Nope'])
        ->assertForbidden();

    expect(IssueTemplate::query()->count())->toBe(0);
});

it('rejects a duplicate name within the same project', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    IssueTemplate::factory()->for($project)->create(['name' => 'Bug report']);

    $this->actingAs(member($project))
        ->post('/projects/THI/templates', ['name' => 'Bug report'])
        ->assertSessionHasErrors('name');
});

it('allows the same template name in a different project', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    IssueTemplate::factory()->for($cms)->create(['name' => 'Bug report']);

    $this->actingAs(member($thi))
        ->post('/projects/THI/templates', ['name' => 'Bug report'])
        ->assertRedirect();

    expect(IssueTemplate::query()->count())->toBe(2);
});

it('updates a template and keeps its own name valid', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $template = IssueTemplate::factory()->for($project)->create(['name' => 'Bug report']);

    $this->actingAs(member($project))
        ->patch("/projects/THI/templates/{$template->id}", [
            'name' => 'Bug report',
            'description' => 'Updated body',
            'priority' => 'urgent',
        ])
        ->assertRedirect();

    expect($template->fresh())
        ->description->toBe('Updated body')
        ->priority->toBe(IssuePriority::Urgent);
});

it('deletes a template', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $template = IssueTemplate::factory()->for($project)->create();

    $this->actingAs(member($project))
        ->delete("/projects/THI/templates/{$template->id}")
        ->assertRedirect();

    expect(IssueTemplate::query()->count())->toBe(0);
});

it('404s when touching a template from another project', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $template = IssueTemplate::factory()->for($cms)->create();

    $this->actingAs(member($thi))
        ->delete("/projects/THI/templates/{$template->id}")
        ->assertNotFound();

    expect(IssueTemplate::query()->count())->toBe(1);
});

it('offers templates from the users other projects to copy, and no one elses', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $stranger = Project::factory()->create(['key' => 'ZZZ']);
    $user = member([$thi, $cms]);

    IssueTemplate::factory()->for($cms)->create(['name' => 'Shared bug']);
    IssueTemplate::factory()->for($thi)->create(['name' => 'Own template']);
    IssueTemplate::factory()->for($stranger)->create(['name' => 'Secret']);

    $this->actingAs($user)->get('/projects/THI/templates')
        ->assertInertia(fn ($page) => $page
            ->has('copyable', 1)
            ->where('copyable.0.name', 'Shared bug')
            ->where('copyable.0.projectKey', 'CMS')
        );
});

it('cascades templates when the project is deleted', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    IssueTemplate::factory()->for($project)->create();

    $project->delete();

    expect(IssueTemplate::query()->count())->toBe(0);
});
