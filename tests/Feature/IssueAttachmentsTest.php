<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(Attachment::DISK);
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);
});

it('shows the attachments on the issue page with who may remove them', function () {
    $writer = member($this->project, ProjectLevel::Write);
    $own = Attachment::factory()->for($this->issue)->for($writer)->create(['name' => 'mine.png', 'mime_type' => 'image/png']);
    Attachment::factory()->for($this->issue)->create(['name' => 'theirs.pdf']);

    $this->actingAs($writer)->get('/issues/THI-1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attachments', 2)
            ->where('attachments.0.name', 'mine.png')
            ->where('attachments.0.isImage', true)
            ->where('attachments.0.canDelete', true)
            ->where('attachments.0.url', route('issues.attachments.show', ['issue' => 'THI-1', 'attachment' => $own->id]))
            ->where('attachments.1.canDelete', false));
});

it('uploads an attachment from the issue page', function () {
    $writer = member($this->project, ProjectLevel::Write);

    $this->actingAs($writer)
        ->post('/issues/THI-1/attachments', ['file' => UploadedFile::fake()->create('notes.txt', 1)])
        ->assertRedirect();

    expect(Attachment::query()->sole()->name)->toBe('notes.txt');
});

it('forbids uploading from the page with read access', function () {
    $this->actingAs(member($this->project, ProjectLevel::Read))
        ->post('/issues/THI-1/attachments', ['file' => UploadedFile::fake()->create('notes.txt', 1)])
        ->assertForbidden();
});

it('downloads an attachment for a member', function () {
    Storage::disk(Attachment::DISK)->put('attachments/1/x.txt', 'hello');
    $attachment = Attachment::factory()->for($this->issue)->create(['path' => 'attachments/1/x.txt']);

    $response = $this->actingAs(member($this->project, ProjectLevel::Read))
        ->get("/issues/THI-1/attachments/{$attachment->id}")
        ->assertOk();

    expect($response->streamedContent())->toBe('hello');
});

it('forbids downloading for someone outside the project', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(User::factory()->create())
        ->get("/issues/THI-1/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('removes an attachment from the issue page', function () {
    $writer = member($this->project, ProjectLevel::Write);
    $attachment = Attachment::factory()->for($this->issue)->for($writer)->create();

    $this->actingAs($writer)
        ->delete("/issues/THI-1/attachments/{$attachment->id}")
        ->assertRedirect();

    expect(Attachment::query()->count())->toBe(0);
});

it('does not remove an attachment through another issue', function () {
    $attachment = Attachment::factory()->create();

    $this->actingAs(member($this->project, ProjectLevel::Admin))
        ->delete("/issues/THI-1/attachments/{$attachment->id}")
        ->assertNotFound();

    expect(Attachment::query()->count())->toBe(1);
});

it('forbids a writer from removing someone else\'s attachment on the page', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(member($this->project, ProjectLevel::Write))
        ->delete("/issues/THI-1/attachments/{$attachment->id}")
        ->assertForbidden();

    expect(Attachment::query()->count())->toBe(1);
});

it('lets a project admin remove someone else\'s attachment on the page', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(member($this->project, ProjectLevel::Admin))
        ->delete("/issues/THI-1/attachments/{$attachment->id}")
        ->assertRedirect();

    expect(Attachment::query()->count())->toBe(0);
});

it('does not download an attachment through another issue', function () {
    $attachment = Attachment::factory()->create();

    $this->actingAs(member($this->project, ProjectLevel::Admin))
        ->get("/issues/THI-1/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('answers an outsider the same whether or not the attachment belongs to the issue', function () {
    $outsider = User::factory()->create();
    $elsewhere = Attachment::factory()->create();

    $this->actingAs($outsider)->get("/issues/THI-1/attachments/{$elsewhere->id}")->assertForbidden();
    $this->actingAs($outsider)->delete("/issues/THI-1/attachments/{$elsewhere->id}")->assertForbidden();
});
