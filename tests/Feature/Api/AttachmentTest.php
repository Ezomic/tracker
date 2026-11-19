<?php

declare(strict_types=1);

use App\Actions\CreateServiceAccountAction;
use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(Attachment::DISK);
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);
});

function uploadTo(User $user, UploadedFile $file): TestResponse
{
    return test()->actingAs($user, 'sanctum')->post('/api/issues/THI-1/attachments', ['file' => $file], ['Accept' => 'application/json']);
}

it('uploads an attachment to an issue', function () {
    $user = member($this->project, ProjectLevel::Write);

    $response = uploadTo($user, UploadedFile::fake()->createWithContent('design.html', '<h1>Portal</h1>'))
        ->assertCreated()
        ->assertJson(['name' => 'design.html', 'user' => $user->email, 'size' => 15]);

    $attachment = Attachment::query()->sole();
    expect($response->json('url'))->toBe(url("/api/issues/THI-1/attachments/{$attachment->id}"))
        ->and($attachment->path)->toStartWith("attachments/{$this->issue->id}/");
    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

it('records the upload in the issue activity', function () {
    uploadTo(member($this->project, ProjectLevel::Write), UploadedFile::fake()->create('notes.pdf', 10))->assertCreated();

    expect($this->issue->activities()->where('type', 'attachment_added')->value('data'))
        ->toBe(['name' => 'notes.pdf']);
});

it('detects the type from the content rather than the client', function () {
    // A real UploadedFile, because the fake one reports its type from the extension.
    $path = tempnam(sys_get_temp_dir(), 'attachment-');
    file_put_contents($path, '<html><script>alert(1)</script></html>');
    $file = new UploadedFile($path, 'shot.png', 'image/png', test: true);

    uploadTo(member($this->project, ProjectLevel::Write), $file)->assertCreated();

    expect(Attachment::query()->sole()->isImage())->toBeFalse();
});

it('shortens a file name longer than the column', function () {
    uploadTo(member($this->project, ProjectLevel::Write), UploadedFile::fake()->create(str_repeat('a', 300).'.txt', 1))
        ->assertCreated();

    expect(mb_strlen(Attachment::query()->sole()->name))->toBe(255);
});

it('removes the stored file when recording the upload fails', function () {
    Attachment::creating(fn () => throw new RuntimeException('database down'));

    $this->withoutExceptionHandling();

    expect(fn () => uploadTo(member($this->project, ProjectLevel::Write), UploadedFile::fake()->create('notes.txt', 1)))
        ->toThrow(RuntimeException::class, 'database down');

    expect(Storage::disk(Attachment::DISK)->allFiles())->toBe([]);
});

it('rejects a file over 20 MB', function () {
    uploadTo(member($this->project, ProjectLevel::Write), UploadedFile::fake()->create('big.zip', Attachment::MAX_KILOBYTES + 1))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('requires a file', function () {
    $this->actingAs(member($this->project, ProjectLevel::Write), 'sanctum')
        ->postJson('/api/issues/THI-1/attachments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('forbids uploading with read access only', function () {
    uploadTo(member($this->project, ProjectLevel::Read), UploadedFile::fake()->create('notes.txt', 1))
        ->assertForbidden();

    expect(Attachment::query()->count())->toBe(0);
});

it('forbids uploading to an issue in another project', function () {
    uploadTo(User::factory()->create(), UploadedFile::fake()->create('notes.txt', 1))
        ->assertForbidden();
});

it('lists the attachments of an issue for a reader', function () {
    $uploader = User::factory()->create();
    Attachment::factory()->for($this->issue)->for($uploader)->create(['name' => 'a.txt']);
    Attachment::factory()->for($this->issue)->for($uploader)->create(['name' => 'b.png', 'mime_type' => 'image/png']);
    Attachment::factory()->create(['name' => 'elsewhere.txt']);

    $this->actingAs(member($this->project, ProjectLevel::Read), 'sanctum')
        ->getJson('/api/issues/THI-1/attachments')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJson([
            ['name' => 'a.txt', 'user' => $uploader->email],
            ['name' => 'b.png', 'mime_type' => 'image/png'],
        ]);
});

it('forbids listing attachments of an issue the user cannot see', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/issues/THI-1/attachments')
        ->assertForbidden();
});

it('downloads a non-image as an attachment with nosniff', function () {
    Storage::disk(Attachment::DISK)->put('attachments/1/x.html', '<h1>Portal</h1>');
    $attachment = Attachment::factory()->for($this->issue)->create([
        'name' => 'design.html', 'path' => 'attachments/1/x.html', 'mime_type' => 'text/html',
    ]);

    $response = $this->actingAs(member($this->project, ProjectLevel::Read), 'sanctum')
        ->get("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', 'sandbox');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->toContain('design.html')
        ->and($response->streamedContent())->toBe('<h1>Portal</h1>');
});

it('serves a raster image inline', function () {
    Storage::disk(Attachment::DISK)->put('attachments/1/x.png', 'png');
    $attachment = Attachment::factory()->for($this->issue)->create([
        'name' => 'shot.png', 'path' => 'attachments/1/x.png', 'mime_type' => 'image/png',
    ]);

    $response = $this->actingAs(member($this->project, ProjectLevel::Read), 'sanctum')
        ->get("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('downloads an SVG rather than rendering it', function () {
    Storage::disk(Attachment::DISK)->put('attachments/1/x.svg', '<svg/>');
    $attachment = Attachment::factory()->for($this->issue)->create([
        'name' => 'logo.svg', 'path' => 'attachments/1/x.svg', 'mime_type' => 'image/svg+xml',
    ]);

    $response = $this->actingAs(member($this->project, ProjectLevel::Read), 'sanctum')
        ->get("/api/issues/THI-1/attachments/{$attachment->id}");

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('forbids downloading for a non-member', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('does not serve an attachment through another issue', function () {
    $attachment = Attachment::factory()->create();

    $this->actingAs(member($this->project, ProjectLevel::Admin), 'sanctum')
        ->getJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertNotFound();
});

it('lets the uploader delete their own attachment', function () {
    $uploader = member($this->project, ProjectLevel::Write);
    Storage::disk(Attachment::DISK)->put('attachments/1/x.txt', 'x');
    $attachment = Attachment::factory()->for($this->issue)->for($uploader)->create(['path' => 'attachments/1/x.txt']);

    $this->actingAs($uploader, 'sanctum')
        ->deleteJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertNoContent();

    expect(Attachment::query()->count())->toBe(0)
        ->and($this->issue->activities()->where('type', 'attachment_removed')->exists())->toBeTrue();
    Storage::disk(Attachment::DISK)->assertMissing('attachments/1/x.txt');
});

it('lets a project admin delete someone else\'s attachment', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(member($this->project, ProjectLevel::Admin), 'sanctum')
        ->deleteJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertNoContent();
});

it('forbids a writer from deleting someone else\'s attachment', function () {
    $attachment = Attachment::factory()->for($this->issue)->create();

    $this->actingAs(member($this->project, ProjectLevel::Write), 'sanctum')
        ->deleteJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertForbidden();

    expect(Attachment::query()->count())->toBe(1);
});

it('forbids the uploader from deleting once they are down to read access', function () {
    $uploader = member($this->project, ProjectLevel::Read);
    $attachment = Attachment::factory()->for($this->issue)->for($uploader)->create();

    $this->actingAs($uploader, 'sanctum')
        ->deleteJson("/api/issues/THI-1/attachments/{$attachment->id}")
        ->assertForbidden();
});

it('keeps attachments closed to a service account', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'SVC']);
    Issue::factory()->for($project)->create(['identifier' => 'SVC-1']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    Sanctum::actingAs(User::query()->where('is_service', true)->firstOrFail(), CreateServiceAccountAction::ABILITIES);

    $this->getJson('/api/issues/SVC-1/attachments')->assertForbidden();
    $this->postJson('/api/issues/SVC-1/attachments', ['file' => UploadedFile::fake()->create('a.txt', 1)])->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson('/api/issues/THI-1/attachments')->assertUnauthorized();
});
