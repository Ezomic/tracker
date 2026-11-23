<?php

declare(strict_types=1);

use App\Services\Notion;
use App\Services\NotionException;
use App\Services\NotionRateLimited;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureNotion();
    $this->notion = app(Notion::class);
});

it('sends the pinned version and the token on every call', function () {
    fakeNotion();

    $this->notion->dataSourceFor('0123456789abcdef0123456789abcdef');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.notion.com/v1/databases/0123456789abcdef0123456789abcdef'
        && $request->method() === 'GET'
        && $request->header('Notion-Version') === ['2026-03-11']
        && $request->header('Authorization') === ['Bearer secret_test_token']);
});

it('returns the single data source of a database', function () {
    fakeNotion();

    expect($this->notion->dataSourceFor('0123456789abcdef0123456789abcdef'))->toBe('ds-1');
});

it('refuses a database that is not shared with the integration', function () {
    fakeNotion(['GET /v1/databases/*' => Http::response(['object' => 'error', 'status' => 404, 'code' => 'object_not_found', 'message' => 'Could not find database'], 404)]);

    expect(fn () => $this->notion->dataSourceFor('0123456789abcdef0123456789abcdef'))
        ->toThrow(NotionException::class, 'Share the database with the integration');
});

it('refuses a database with no data source or several', function (array $sources) {
    fakeNotion(['GET /v1/databases/*' => ['object' => 'database', 'data_sources' => $sources]]);

    expect(fn () => $this->notion->dataSourceFor('0123456789abcdef0123456789abcdef'))
        ->toThrow(NotionException::class, 'exactly one data source');
})->with([
    'none' => [[]],
    'two' => [[['id' => 'a', 'name' => 'A'], ['id' => 'b', 'name' => 'B']]],
]);

it('reports an unauthorised token without echoing it', function () {
    fakeNotion(['GET /v1/databases/*' => Http::response(['object' => 'error', 'status' => 401, 'code' => 'unauthorized', 'message' => 'API token is invalid.'], 401)]);

    try {
        $this->notion->dataSourceFor('0123456789abcdef0123456789abcdef');
    } catch (NotionException $e) {
        expect($e->getMessage())->toContain('401')->toContain('unauthorized')
            ->and($e->getMessage())->not->toContain('secret_test_token')
            ->and($e->status)->toBe(401);

        return;
    }

    $this->fail('expected a NotionException');
});

it('creates the missing columns and finds the title column by type', function () {
    fakeNotion(['GET /v1/data_sources/*' => ['properties' => [
        'Task name' => ['name' => 'Task name', 'type' => 'title'],
        'Priority' => ['name' => 'Priority', 'type' => 'select'],
    ]]]);

    expect($this->notion->prepareSchema('ds-1'))->toBe('Task name');

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $properties = $request->data()['properties'];

        return $request->url() === 'https://api.notion.com/v1/data_sources/ds-1'
            && array_keys($properties) === ['Key', 'State', 'Assignee', 'Labels', 'Link']
            && json_encode($properties['Key']) === '{"rich_text":{}}'
            && json_encode($properties['State']) === '{"select":{}}'
            && json_encode($properties['Labels']) === '{"multi_select":{}}'
            && json_encode($properties['Link']) === '{"url":{}}';
    });
});

it('does not touch the schema when every column already exists', function () {
    fakeNotion(['GET /v1/data_sources/*' => ['properties' => [
        'Name' => ['name' => 'Name', 'type' => 'title'],
        'Key' => ['type' => 'rich_text'],
        'State' => ['type' => 'select'],
        'Priority' => ['type' => 'select'],
        'Assignee' => ['type' => 'rich_text'],
        'Labels' => ['type' => 'multi_select'],
        'Link' => ['type' => 'url'],
    ]]]);

    $this->notion->prepareSchema('ds-1');

    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
});

it('refuses a column that exists with the wrong type', function () {
    fakeNotion(['GET /v1/data_sources/*' => ['properties' => [
        'Name' => ['name' => 'Name', 'type' => 'title'],
        'State' => ['type' => 'status'],
    ]]]);

    expect(fn () => $this->notion->prepareSchema('ds-1'))
        ->toThrow(NotionException::class, 'The "State" column must be a select column, not status');
});

it('refuses a data source with no title column', function () {
    fakeNotion(['GET /v1/data_sources/*' => ['properties' => []]]);

    expect(fn () => $this->notion->prepareSchema('ds-1'))
        ->toThrow(NotionException::class, 'no title column');
});

it('creates a page under the data source and returns its id', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'new-page']]);

    expect($this->notion->createPage('ds-1', ['Name' => ['title' => []]]))->toBe('new-page');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data() === [
            'parent' => ['type' => 'data_source_id', 'data_source_id' => 'ds-1'],
            'properties' => ['Name' => ['title' => []]],
        ]);
});

it('updates a page and sets its trash flag', function (bool $trashed) {
    fakeNotion();

    $this->notion->updatePage('page-1', ['Key' => ['rich_text' => []]], $trashed);

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://api.notion.com/v1/pages/page-1'
        && $request->data() === ['properties' => ['Key' => ['rich_text' => []]], 'in_trash' => $trashed]);
})->with([true, false]);

it('trashes a page without touching its properties', function () {
    fakeNotion();

    $this->notion->trashPage('page-1');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://api.notion.com/v1/pages/page-1'
        && $request->data() === ['in_trash' => true]);
});

it('signals a retry with the Retry-After seconds on 429 and 529', function (int $status, array $headers, int $expected) {
    fakeNotion(['POST /v1/pages' => Http::response(['object' => 'error', 'code' => 'rate_limited', 'message' => 'slow down'], $status, $headers)]);

    try {
        $this->notion->createPage('ds-1', []);
    } catch (NotionRateLimited $e) {
        expect($e->retryAfter)->toBe($expected)->and($e->status)->toBe($status);

        return;
    }

    $this->fail('expected a NotionRateLimited');
})->with([
    '429 with header' => [429, ['Retry-After' => '7'], 7],
    '529 with header' => [529, ['Retry-After' => '12'], 12],
    '429 without header' => [429, [], 5],
    '429 with garbage' => [429, ['Retry-After' => 'soon'], 5],
    '429 with zero' => [429, ['Retry-After' => '0'], 1],
    '429 with a huge value' => [429, ['Retry-After' => '99999'], 60],
]);

it('treats other failures as plain errors', function (int $status) {
    fakeNotion(['POST /v1/pages' => Http::response(['object' => 'error', 'code' => 'validation_error', 'message' => 'bad body'], $status)]);

    try {
        $this->notion->createPage('ds-1', []);
    } catch (NotionException $e) {
        expect($e)->not->toBeInstanceOf(NotionRateLimited::class)
            ->and($e->status)->toBe($status)
            ->and($e->getMessage())->toContain('bad body');

        return;
    }

    $this->fail('expected a NotionException');
})->with([400, 500, 503]);

it('does not call Notion at all when the integration is off', function (?string $value) {
    Http::fake();
    config()->set('services.notion.api_key', $value);

    expect(fn () => $this->notion->dataSourceFor('0123456789abcdef0123456789abcdef'))
        ->toThrow(NotionException::class, 'not configured');

    Http::assertNothingSent();
})->with('blank');
