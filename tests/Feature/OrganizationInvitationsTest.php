<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Mail\OrganizationInvitationMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: Invitation, 1: string}
 */
function inviteFor(Organization $organization, string $email, array $attributes = []): array
{
    $plain = Str::random(40);

    $invitation = Invitation::create([
        'organization_id' => $organization->id,
        'email' => $email,
        'role' => OrganizationRole::Member,
        'token' => Invitation::hashToken($plain),
        'expires_at' => now()->addDays(7),
        ...$attributes,
    ]);

    return [$invitation, $plain];
}

it('lets an owner invite someone to the organization', function () {
    Mail::fake();
    [$org, $owner] = organizationWith(OrganizationRole::Owner);

    $this->actingAs($owner)
        ->post('/settings/invitations', ['email' => 'New@Example.com', 'role' => 'member'])
        ->assertRedirect();

    $invitation = Invitation::query()->firstOrFail();
    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe(OrganizationRole::Member)
        ->and($invitation->organization_id)->toBe($org->id)
        ->and($invitation->project_id)->toBeNull()
        ->and($invitation->invited_by_id)->toBe($owner->id)
        ->and($invitation->isPending())->toBeTrue();

    Mail::assertSent(OrganizationInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

it('invites a guest with an initial project grant', function () {
    Mail::fake();
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);

    $this->actingAs($owner)
        ->post('/settings/invitations', [
            'email' => 'guest@example.com',
            'role' => 'guest',
            'project_id' => $project->id,
            'level' => 'read',
        ])
        ->assertRedirect();

    $invitation = Invitation::query()->firstOrFail();
    expect($invitation->role)->toBe(OrganizationRole::Guest)
        ->and($invitation->project_id)->toBe($project->id)
        ->and($invitation->level)->toBe(ProjectLevel::Read);
});

it('requires a level when a project is given', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);

    $this->actingAs($owner)
        ->post('/settings/invitations', [
            'email' => 'guest@example.com',
            'role' => 'guest',
            'project_id' => $project->id,
        ])
        ->assertSessionHasErrors('level');
});

it('rejects a project from another organization', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $foreign = Project::factory()->create(['key' => 'CMS']);

    $this->actingAs($owner)
        ->post('/settings/invitations', [
            'email' => 'guest@example.com',
            'role' => 'guest',
            'project_id' => $foreign->id,
            'level' => 'read',
        ])
        ->assertNotFound();
});

it('forbids inviting as owner or admin', function () {
    [, $owner] = organizationWith(OrganizationRole::Owner);

    $this->actingAs($owner)
        ->post('/settings/invitations', ['email' => 'new@example.com', 'role' => 'owner'])
        ->assertSessionHasErrors('role');
});

it('forbids a plain member from inviting', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $plain = User::factory()->create();
    $org->members()->attach($plain->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($plain)
        ->post('/settings/invitations', ['email' => 'new@example.com', 'role' => 'member'])
        ->assertForbidden();

    expect(Invitation::query()->count())->toBe(0);
});

it('rejects inviting an existing organization member', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $existing = User::factory()->create(['email' => 'in@example.com']);
    $org->members()->attach($existing->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)
        ->post('/settings/invitations', ['email' => 'in@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

it('re-inviting refreshes the token and invalidates the old link', function () {
    Mail::fake();
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    [$invitation, $oldPlain] = inviteFor($org, 'new@example.com');

    $this->actingAs($owner)
        ->post('/settings/invitations', ['email' => 'new@example.com', 'role' => 'member']);

    expect(Invitation::query()->count())->toBe(1)
        ->and($invitation->fresh()->token)->not->toBe(Invitation::hashToken($oldPlain));
});

it('lists pending invitations on the members page', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    inviteFor($org, 'pending@example.com');

    $this->actingAs($owner)->get('/settings/members')
        ->assertInertia(fn ($page) => $page
            ->component('settings/Members')
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com')
        );
});

it('keeps the members page from members and guests', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $member = User::factory()->create();
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($member)->get('/settings/members')->assertForbidden();
});

it('lets a manager revoke and resend an invitation', function () {
    Mail::fake();
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    [$invitation] = inviteFor($org, 'new@example.com');

    $this->actingAs($owner)
        ->post("/settings/invitations/{$invitation->id}/resend")
        ->assertRedirect();
    Mail::assertSent(OrganizationInvitationMail::class);

    $this->actingAs($owner)
        ->delete("/settings/invitations/{$invitation->id}")
        ->assertRedirect();
    expect(Invitation::query()->count())->toBe(0);
});

it('404s when managing an invitation from another organization', function () {
    [, $owner] = organizationWith(OrganizationRole::Owner);
    [$other] = organizationWith(OrganizationRole::Owner);
    [$invitation] = inviteFor($other, 'new@example.com');

    $this->actingAs($owner)
        ->delete("/settings/invitations/{$invitation->id}")
        ->assertNotFound();
});

it('accepts an invitation and joins the organization and its project', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($org, 'new@example.com', [
        'role' => OrganizationRole::Guest,
        'project_id' => $project->id,
        'level' => ProjectLevel::Write,
    ]);

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertRedirect(route('projects.board', 'THI'));

    expect($org->roleFor($invitee))->toBe(OrganizationRole::Guest)
        ->and($project->grantFor($invitee))->toBe(ProjectLevel::Write)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('accepts an org-only invitation and lands on the dashboard', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [, $plain] = inviteFor($org, 'new@example.com');

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertRedirect(route('dashboard'));

    expect($org->roleFor($invitee))->toBe(OrganizationRole::Member);
});

it('shows the invitation to a guest and remembers where to return', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $org->forceFill(['name' => 'Thijssen'])->save();
    $inviter = User::factory()->create();
    [, $plain] = inviteFor($org, 'new@example.com', ['invited_by_id' => $inviter->id]);

    $this->get("/invitations/{$plain}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Invitation')
            ->where('state', 'guest')
            ->where('hasAccount', false)
            ->where('invitation.organizationName', 'Thijssen')
        );

    expect(session('url.intended'))->toContain("/invitations/{$plain}");
});

it('refuses an invitation for a different signed-in account', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $other = User::factory()->create(['email' => 'someone@example.com']);
    [, $plain] = inviteFor($org, 'invited@example.com');

    $this->actingAs($other)
        ->get("/invitations/{$plain}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('state', 'mismatch'));

    expect($org->hasMember($other))->toBeFalse();
});

it('rejects an expired invitation', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($org, 'new@example.com');
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($invitee)
        ->get("/invitations/{$plain}")
        ->assertInertia(fn ($page) => $page->where('state', 'expired'));

    expect($org->hasMember($invitee))->toBeFalse();
});

it('rejects an already accepted invitation', function () {
    [$org] = organizationWith(OrganizationRole::Owner);
    $invitee = User::factory()->create(['email' => 'new@example.com']);
    [$invitation, $plain] = inviteFor($org, 'new@example.com');
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
