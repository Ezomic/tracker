<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Mail\ProjectInvitationMail;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function inviteFor(Project $project, string $email, ProjectLevel $level = ProjectLevel::Write, ?User $invitedBy = null): array
{
    $plain = Str::random(40);

    $invitation = Invitation::create([
        'project_id' => $project->id,
        'email' => $email,
        'level' => $level,
        'token' => Invitation::hashToken($plain),
        'invited_by_id' => $invitedBy?->id,
        'expires_at' => now()->addDays(7),
    ]);

    return [$invitation, $plain];
}

it('lets an owner invite someone by email and role', function () {
    Mail::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);

    $this->actingAs($owner)
        ->post('/projects/THI/invitations', ['email' => 'New@Example.com', 'level' => 'admin'])
        ->assertRedirect();

    $invitation = Invitation::query()->firstOrFail();
    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->level)->toBe(ProjectLevel::Admin)
        ->and($invitation->invited_by_id)->toBe($owner->id)
        ->and($invitation->isPending())->toBeTrue();

    Mail::assertSent(ProjectInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

it('forbids a plain member from inviting', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $plain = member($project, ProjectLevel::Write);

    $this->actingAs($plain)
        ->post('/projects/THI/invitations', ['email' => 'new@example.com', 'level' => 'write'])
        ->assertForbidden();

    expect(Invitation::query()->count())->toBe(0);
});

it('forbids a non-member from inviting', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);

    $this->actingAs(User::factory()->create())
        ->post('/projects/THI/invitations', ['email' => 'new@example.com', 'level' => 'write'])
        ->assertForbidden();
});

it('rejects inviting someone to owner', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))
        ->post('/projects/THI/invitations', ['email' => 'new@example.com', 'level' => 'owner'])
        ->assertSessionHasErrors('level');
});

it('rejects inviting an existing member', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $existing = User::factory()->create(['email' => 'in@example.com']);
    joinProjects($existing, $project, ProjectLevel::Write);

    $this->actingAs($owner)
        ->post('/projects/THI/invitations', ['email' => 'in@example.com', 'level' => 'write'])
        ->assertSessionHasErrors('email');
});

it('re-inviting refreshes the token and invalidates the old link', function () {
    Mail::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    [$invitation, $oldPlain] = inviteFor($project, 'new@example.com');

    $this->actingAs($owner)
        ->post('/projects/THI/invitations', ['email' => 'new@example.com', 'level' => 'write']);

    expect(Invitation::query()->count())->toBe(1)
        ->and($invitation->fresh()->token)->not->toBe(Invitation::hashToken($oldPlain));
});

it('lists pending invitations to managers only', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $plain = member($project, ProjectLevel::Write);
    inviteFor($project, 'pending@example.com');

    $this->actingAs($owner)->get('/projects/THI/members')
        ->assertInertia(fn ($page) => $page
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com')
        );

    $this->actingAs($plain)->get('/projects/THI/members')
        ->assertInertia(fn ($page) => $page->has('invitations', 0));
});

it('lets a manager revoke and resend an invitation', function () {
    Mail::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    [$invitation] = inviteFor($project, 'new@example.com');

    $this->actingAs($owner)
        ->post("/projects/THI/invitations/{$invitation->id}/resend")
        ->assertRedirect();
    Mail::assertSent(ProjectInvitationMail::class);

    $this->actingAs($owner)
        ->delete("/projects/THI/invitations/{$invitation->id}")
        ->assertRedirect();
    expect(Invitation::query()->count())->toBe(0);
});

it('404s when managing an invitation from another project', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $other = Project::factory()->create(['key' => 'CMS']);
    $owner = member($project);
    [$invitation] = inviteFor($other, 'new@example.com');

    $this->actingAs($owner)
        ->delete("/projects/THI/invitations/{$invitation->id}")
        ->assertNotFound();
});

it('accepts an invitation for the signed-in invited user', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($project, 'new@example.com', ProjectLevel::Admin);

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertRedirect(route('projects.board', 'THI'));

    expect($project->grantFor($invitee))->toBe(ProjectLevel::Admin)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('shows the invitation to a guest and remembers where to return', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen']);
    $inviter = member($project);
    [, $plain] = inviteFor($project, 'new@example.com', ProjectLevel::Write, $inviter);

    $this->get("/invitations/{$plain}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Invitation')
            ->where('state', 'guest')
            ->where('hasAccount', false)
            ->where('invitation.projectName', 'Thijssen')
        );

    expect(session('url.intended'))->toContain("/invitations/{$plain}");
});

it('refuses an invitation for a different signed-in account', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);
    $other = User::factory()->create(['email' => 'someone@example.com']);
    [, $plain] = inviteFor($project, 'invited@example.com');

    $this->actingAs($other)
        ->get("/invitations/{$plain}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('state', 'mismatch'));

    expect($project->hasMember($other))->toBeFalse();
});

it('rejects an expired invitation', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($project, 'new@example.com');
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertInertia(fn ($page) => $page->where('state', 'expired'));

    expect($project->hasMember($invitee))->toBeFalse();
});

it('rejects an already accepted invitation', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($project, 'new@example.com');
    $invitation->forceFill(['accepted_at' => now()])->save();

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertInertia(fn ($page) => $page->where('state', 'accepted'));
});

it('rejects an unknown token', function () {
    $this->get('/invitations/not-a-real-token')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('state', 'invalid'));
});

it('routes an invited newcomer through registration and then joins them', function () {
    Mail::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    member($project);
    [, $plain] = inviteFor($project, 'newbie@example.com');

    // Guest hits the link: we remember where to send them back.
    $this->get("/invitations/{$plain}")
        ->assertInertia(fn ($page) => $page->where('state', 'guest'));

    // They register and verify the emailed code.
    $this->post('/register', ['name' => 'New Bie', 'email' => 'newbie@example.com']);
    $user = User::query()->where('email', 'newbie@example.com')->firstOrFail();
    Cache::put('login-code:newbie@example.com', ['hash' => Hash::make('123456'), 'attempts' => 0], now()->addMinutes(10));

    $this->post('/login/code/verify', ['code' => '123456'])
        ->assertRedirect("/invitations/{$plain}");

    // Landing back on the link completes the join.
    $this->get("/invitations/{$plain}")->assertRedirect(route('projects.board', 'THI'));

    expect($project->grantFor($user))->toBe(ProjectLevel::Write);
});
