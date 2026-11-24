<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/**
 * A project inside an organization, with a user holding the given org role and
 * (optionally) a direct grant on it.
 *
 * @return array{0: Project, 1: User}
 */
function noteActor(OrganizationRole $role, ?ProjectLevel $grant = null, ?Project $project = null): array
{
    $project ??= Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $user = User::factory()->create();
    $project->organization?->members()->attach($user->id, ['role' => $role->value]);

    if ($grant !== null) {
        joinProjects($user, $project, $grant);
    }

    return [$project, $user];
}

function noteIn(Project $project, array $attributes = []): Note
{
    return Note::factory()->for($project)->create($attributes);
}

describe('who can read', function () {
    it('lets a read-only member list and open notes', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Read);
        $note = noteIn($project, ['title' => 'Runbook']);

        $this->actingAs($user)->get("/{$project->key}/notes")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notes/Index')
                ->has('notes', 1)
                ->where('notes.0.title', 'Runbook')
                ->where('can.create', false));

        $this->actingAs($user)->get("/{$project->key}/notes/{$note->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notes/Show')
                ->where('note.title', 'Runbook')
                ->where('can.update', false)
                ->where('can.delete', false));
    });

    it('lets a guest with a read grant read but not write', function () {
        [$project, $user] = noteActor(OrganizationRole::Guest, ProjectLevel::Read);
        $note = noteIn($project);

        $this->actingAs($user)->get("/{$project->key}/notes")->assertOk();
        $this->actingAs($user)->get("/{$project->key}/notes/{$note->id}")->assertOk();
        $this->actingAs($user)->post("/{$project->key}/notes", ['title' => 'x'])->assertForbidden();
    });

    it('redirects guests of the site to log in', function () {
        $project = Project::factory()->create();

        $this->get("/{$project->key}/notes")->assertRedirect('/login');
    });
});

describe('who is shut out', function () {
    it('denies everything to people who cannot see the project', function (string $who) {
        $project = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $note = noteIn($project, ['title' => 'Secret', 'body' => 'hidden']);

        $user = match ($who) {
            'non-member of the org' => User::factory()->create(),
            'org member without a grant' => noteActor(OrganizationRole::Member, null, $project)[1],
            'guest without a grant' => noteActor(OrganizationRole::Guest, null, $project)[1],
            'admin of another org' => organizationWith(OrganizationRole::Owner)[1],
        };

        $this->actingAs($user)->get("/{$project->key}/notes")->assertForbidden();
        $this->actingAs($user)->get("/{$project->key}/notes/{$note->id}")->assertForbidden();
        $this->actingAs($user)->post("/{$project->key}/notes", ['title' => 'Mine'])->assertForbidden();
        $this->actingAs($user)->patch("/{$project->key}/notes/{$note->id}", ['title' => 'Mine'])->assertForbidden();
        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")->assertForbidden();

        expect(Note::query()->count())->toBe(1)
            ->and($note->fresh()?->title)->toBe('Secret')
            ->and($note->fresh()?->trashed())->toBeFalse();
    })->with(['non-member of the org', 'org member without a grant', 'guest without a grant', 'admin of another org']);

    it('does not expose another project through a project the user can see', function () {
        [$visible, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Admin);
        $hidden = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $foreign = noteIn($hidden, ['title' => 'Foreign']);

        $this->actingAs($user)->get("/{$visible->key}/notes/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->patch("/{$visible->key}/notes/{$foreign->id}", ['title' => 'Taken'])->assertNotFound();
        $this->actingAs($user)->delete("/{$visible->key}/notes/{$foreign->id}")->assertNotFound();

        expect($foreign->fresh()?->title)->toBe('Foreign')
            ->and($foreign->fresh()?->trashed())->toBeFalse();
    });

    it('does not mix notes of two projects the user can see', function () {
        [$first, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Admin);
        $second = Project::factory()->create(['organization_id' => $first->organization_id]);
        joinProjects($user, $second);
        $other = noteIn($second, ['title' => 'Second only']);

        $this->actingAs($user)->get("/{$first->key}/notes/{$other->id}")->assertNotFound();
        $this->actingAs($user)->patch("/{$first->key}/notes/{$other->id}", ['title' => 'Moved'])->assertNotFound();
        $this->actingAs($user)->delete("/{$first->key}/notes/{$other->id}")->assertNotFound();
        $this->actingAs($user)->get("/{$first->key}/notes")
            ->assertInertia(fn ($page) => $page->has('notes', 0));

        expect($other->fresh()?->title)->toBe('Second only')
            ->and($other->fresh()?->trashed())->toBeFalse();
    });
});

describe('who can write', function () {
    it('lets a writer create a note stamped with themselves as author', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);

        $this->actingAs($user)->post("/{$project->key}/notes", ['title' => 'Plan', 'body' => '# Heading'])
            ->assertRedirect();

        $note = Note::query()->firstOrFail();
        expect($note->project_id)->toBe($project->id)
            ->and($note->user_id)->toBe($user->id)
            ->and($note->title)->toBe('Plan')
            ->and($note->body)->toBe('# Heading');
    });

    it('ignores an author or project smuggled into the payload', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $other = Project::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($user)->post("/{$project->key}/notes", [
            'title' => 'Plan',
            'user_id' => $stranger->id,
            'project_id' => $other->id,
        ])->assertRedirect();

        $note = Note::query()->firstOrFail();
        expect($note->user_id)->toBe($user->id)->and($note->project_id)->toBe($project->id);
    });

    it('lets a writer edit anyone\'s note without changing its author', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $author = User::factory()->create();
        $note = noteIn($project, ['user_id' => $author->id, 'title' => 'Old']);

        $this->actingAs($user)->patch("/{$project->key}/notes/{$note->id}", ['title' => 'New', 'body' => 'Body'])
            ->assertRedirect();

        $note->refresh();
        expect($note->title)->toBe('New')->and($note->body)->toBe('Body')->and($note->user_id)->toBe($author->id);
    });

    it('denies a read-only member and a guest even with a write grant', function (OrganizationRole $role, ProjectLevel $grant) {
        [$project, $user] = noteActor($role, $grant);
        $note = noteIn($project, ['title' => 'Keep', 'user_id' => $user->id]);

        $this->actingAs($user)->post("/{$project->key}/notes", ['title' => 'New'])->assertForbidden();
        $this->actingAs($user)->patch("/{$project->key}/notes/{$note->id}", ['title' => 'Edited'])->assertForbidden();
        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")->assertForbidden();

        expect(Note::query()->count())->toBe(1)
            ->and($note->fresh()?->title)->toBe('Keep')
            ->and($note->fresh()?->trashed())->toBeFalse();
    })->with([
        'read-only member' => [OrganizationRole::Member, ProjectLevel::Read],
        'guest with write grant' => [OrganizationRole::Guest, ProjectLevel::Write],
        'guest with admin grant' => [OrganizationRole::Guest, ProjectLevel::Admin],
    ]);

    it('lets an org owner with no direct grant write', function () {
        [$project, $user] = noteActor(OrganizationRole::Owner);

        $this->actingAs($user)->post("/{$project->key}/notes", ['title' => 'Owner note'])->assertRedirect();

        expect(Note::query()->count())->toBe(1);
    });
});

describe('deleting', function () {
    it('soft-deletes the note for its author', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $note = noteIn($project, ['user_id' => $user->id]);

        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")
            ->assertRedirect("/{$project->key}/notes");

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
        $this->actingAs($user)->get("/{$project->key}/notes/{$note->id}")->assertNotFound();
        $this->actingAs($user)->get("/{$project->key}/notes")->assertInertia(fn ($page) => $page->has('notes', 0));
    });

    it('refuses a non-admin writer deleting someone else\'s note', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $note = noteIn($project, ['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")->assertForbidden();

        expect($note->fresh()?->trashed())->toBeFalse();
    });

    it('lets a project admin delete someone else\'s note', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Admin);
        $note = noteIn($project, ['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")->assertRedirect();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    });

    it('lets an org admin with no grant delete someone else\'s note', function () {
        [$project, $user] = noteActor(OrganizationRole::Admin);
        $note = noteIn($project, ['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->delete("/{$project->key}/notes/{$note->id}")->assertRedirect();

        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    });

    it('lets only an admin delete a note whose author is gone', function () {
        [$project, $writer] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $note = noteIn($project, ['user_id' => null]);

        $this->actingAs($writer)->delete("/{$project->key}/notes/{$note->id}")->assertForbidden();
        expect($note->fresh()?->trashed())->toBeFalse();
    });

    it('reports the delete permission to the page', function () {
        [$project, $user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $own = noteIn($project, ['user_id' => $user->id]);
        $theirs = noteIn($project, ['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->get("/{$project->key}/notes/{$own->id}")
            ->assertInertia(fn ($page) => $page->where('can.update', true)->where('can.delete', true));
        $this->actingAs($user)->get("/{$project->key}/notes/{$theirs->id}")
            ->assertInertia(fn ($page) => $page->where('can.update', true)->where('can.delete', false));
    });
});

describe('what a title and body may be', function () {
    beforeEach(function () {
        [$this->project, $this->user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
    });

    it('trims the title and keeps the body as written, apart from the edges Laravel trims', function () {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", [
            'title' => "  Padded title \n",
            'body' => "\n line one\n\n    indented code\n- item  \n",
        ])->assertRedirect();

        $note = Note::query()->firstOrFail();
        expect($note->title)->toBe('Padded title')
            ->and($note->body)->toBe("line one\n\n    indented code\n- item");
    });

    it('rejects a missing, empty or whitespace-only title', function (mixed $title) {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => $title, 'body' => 'x'])
            ->assertSessionHasErrors('title');

        expect(Note::query()->count())->toBe(0);
    })->with([
        'null' => [null],
        'empty' => [''],
        'spaces' => ['    '],
        'tabs and newlines' => ["\t\n \r\n"],
    ]);

    it('rejects a title that is not a string', function (mixed $title) {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => $title])
            ->assertSessionHasErrors('title');

        expect(Note::query()->count())->toBe(0);
    })->with([
        'array' => [['a', 'b']],
        'nested' => [['a' => ['b']]],
    ]);

    it('accepts punctuation only, unicode and emoji titles', function (string $title) {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => $title])->assertRedirect();

        expect(Note::query()->firstOrFail()->title)->toBe($title);
    })->with([
        'punctuation' => ['!!! ???'],
        'dots' => ['...'],
        'cjk' => ['数据库说明'],
        'accents' => ['Ünïcödé café'],
        'emoji' => ['Launch 🚀👩‍💻'],
        'rtl' => ['ملاحظات'],
    ]);

    it('stores markup in a title as text', function () {
        $title = '<script>alert(1)</script> & <b>bold</b>';

        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => $title])->assertRedirect();

        expect(Note::query()->firstOrFail()->title)->toBe($title);
    });

    it('limits the title to 200 characters, counting characters rather than bytes', function () {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => str_repeat('é', 200)])
            ->assertRedirect();
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => str_repeat('é', 201)])
            ->assertSessionHasErrors('title');

        expect(Note::query()->count())->toBe(1);
    });

    it('stores an absent or empty body as an empty string', function (array $payload) {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'T', ...$payload])
            ->assertRedirect();

        expect(Note::query()->firstOrFail()->body)->toBe('');
    })->with([
        'absent' => [[]],
        'empty' => [['body' => '']],
        'null' => [['body' => null]],
        'whitespace' => [['body' => "  \n "]],
    ]);

    it('rejects a body that is not a string', function () {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'T', 'body' => ['x']])
            ->assertSessionHasErrors('body');

        expect(Note::query()->count())->toBe(0);
    });

    it('limits the body to 50000 characters', function () {
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'Fits', 'body' => str_repeat('a', 50000)])
            ->assertRedirect();
        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'Too long', 'body' => str_repeat('a', 50001)])
            ->assertSessionHasErrors('body');

        expect(Note::query()->count())->toBe(1);
    });

    it('stores html, script and markdown injection in the body untouched and serves it back as data', function () {
        $body = "<script>alert(1)</script>\n<img src=x onerror=alert(1)>\n[x](javascript:alert(1))\n\n```\n<b>code</b>\n```";

        $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'XSS', 'body' => $body])
            ->assertRedirect();
        $note = Note::query()->firstOrFail();

        expect($note->body)->toBe($body);
        $this->actingAs($this->user)->get("/{$this->project->key}/notes/{$note->id}")
            ->assertInertia(fn ($page) => $page->where('note.body', $body));
    });

    it('allows duplicate titles', function () {
        foreach ([1, 2] as $_) {
            $this->actingAs($this->user)->post("/{$this->project->key}/notes", ['title' => 'Same'])->assertRedirect();
        }

        expect(Note::query()->where('title', 'Same')->count())->toBe(2);
    });

    it('applies the same rules when updating', function () {
        $note = noteIn($this->project, ['title' => 'Keep', 'body' => 'Keep']);
        $url = "/{$this->project->key}/notes/{$note->id}";

        $this->actingAs($this->user)->patch($url, ['title' => '   ', 'body' => 'x'])->assertSessionHasErrors('title');
        $this->actingAs($this->user)->patch($url, ['title' => str_repeat('a', 201)])->assertSessionHasErrors('title');
        $this->actingAs($this->user)->patch($url, ['title' => 'T', 'body' => str_repeat('a', 50001)])->assertSessionHasErrors('body');
        $this->actingAs($this->user)->patch($url, ['title' => ['a']])->assertSessionHasErrors('title');

        expect($note->fresh()?->title)->toBe('Keep')->and($note->fresh()?->body)->toBe('Keep');

        $this->actingAs($this->user)->patch($url, ['title' => '  Edited ', 'body' => ''])->assertRedirect();

        expect($note->fresh()?->title)->toBe('Edited')->and($note->fresh()?->body)->toBe('');
    });
});

describe('listing and searching', function () {
    beforeEach(function () {
        [$this->project, $this->user] = noteActor(OrganizationRole::Member, ProjectLevel::Write);
        $this->titles = fn (string $search = '') => $this->actingAs($this->user)
            ->get("/{$this->project->key}/notes".($search === '' ? '' : '?search='.urlencode($search)))
            ->assertOk()
            ->viewData('page')['props']['notes'];
    });

    it('lists the newest edit first and never leaks other projects or trashed notes', function () {
        noteIn($this->project, ['title' => 'Older', 'updated_at' => now()->subDay()]);
        noteIn($this->project, ['title' => 'Newer', 'updated_at' => now()]);
        noteIn($this->project, ['title' => 'Gone'])->delete();
        noteIn(Project::factory()->create(), ['title' => 'Elsewhere']);

        expect(collect(($this->titles)())->pluck('title')->all())->toBe(['Newer', 'Older']);
    });

    it('matches titles and bodies, ignoring ascii case', function () {
        noteIn($this->project, ['title' => 'Deployment guide', 'body' => 'nothing']);
        noteIn($this->project, ['title' => 'Other', 'body' => 'Rollback via the DEPLOY button']);
        noteIn($this->project, ['title' => 'Unrelated', 'body' => 'nothing']);

        expect(collect(($this->titles)('deploy'))->pluck('title')->sort()->values()->all())
            ->toBe(['Deployment guide', 'Other']);
    });

    it('matches unicode exactly', function () {
        noteIn($this->project, ['title' => 'Café notes']);
        noteIn($this->project, ['title' => 'Plain']);

        expect(collect(($this->titles)('café'))->pluck('title')->all())->toBe(['Café notes']);
    });

    it('treats wildcard characters as plain text', function (string $term, array $expected) {
        noteIn($this->project, ['title' => '100% done']);
        noteIn($this->project, ['title' => 'snake_case']);
        noteIn($this->project, ['title' => 'back\\slash']);
        noteIn($this->project, ['title' => 'plain words']);

        expect(collect(($this->titles)($term))->pluck('title')->all())->toBe($expected);
    })->with([
        'percent' => ['%', ['100% done']],
        'underscore' => ['_', ['snake_case']],
        'backslash' => ['\\', ['back\\slash']],
        'percent in the middle' => ['0% d', ['100% done']],
    ]);

    it('returns the whole list for an empty or whitespace-only search', function (string $term) {
        noteIn($this->project, ['title' => 'One']);
        noteIn($this->project, ['title' => 'Two']);

        expect(($this->titles)($term))->toHaveCount(2);
    })->with(['empty' => [''], 'spaces' => ['   ']]);

    it('returns nothing for a term that matches nothing', function () {
        noteIn($this->project, ['title' => 'One']);

        expect(($this->titles)('zzz'))->toHaveCount(0);
    });

    it('does not search notes of projects the user cannot see', function () {
        $hidden = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        noteIn($hidden, ['title' => 'Needle']);

        expect(($this->titles)('needle'))->toHaveCount(0);
        $this->actingAs($this->user)->get("/{$hidden->key}/notes?search=needle")->assertForbidden();
    });

    it('rejects a search that is not a string', function () {
        $this->actingAs($this->user)->get("/{$this->project->key}/notes?search[]=a")
            ->assertSessionHasErrors('search');
    });

    it('does not search soft-deleted notes', function () {
        noteIn($this->project, ['title' => 'Needle'])->delete();

        expect(($this->titles)('needle'))->toHaveCount(0);
    });
});
