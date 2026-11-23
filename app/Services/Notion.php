<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Cast;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use stdClass;

/**
 * The few Notion API calls the mirror needs, over Laravel's HTTP client. The
 * token is read here and nowhere else, and never ends up in a message.
 */
class Notion
{
    private const BASE_URL = 'https://api.notion.com';

    private const DEFAULT_RETRY_AFTER = 5;

    private const MAX_RETRY_AFTER = 60;

    /**
     * The columns the mirror writes besides the title, as Notion property types.
     */
    public const COLUMNS = [
        'Key' => 'rich_text',
        'State' => 'select',
        'Priority' => 'select',
        'Assignee' => 'rich_text',
        'Labels' => 'multi_select',
        'Link' => 'url',
    ];

    public function __construct(private readonly Integrations $integrations) {}

    /**
     * The id of the one data source behind a database. Since API version
     * 2025-09-03 pages are created in a data source, and a database can hold
     * several; a database with any other number is refused rather than guessed.
     */
    public function dataSourceFor(string $databaseId): string
    {
        try {
            $database = $this->send('GET', '/v1/databases/'.rawurlencode($databaseId));
        } catch (NotionException $e) {
            throw $e->status === 404
                ? new NotionException('Notion could not find that database. Share the database with the integration and try again.', 404)
                : $e;
        }

        $sources = is_array($database['data_sources'] ?? null) ? array_values($database['data_sources']) : [];

        if (count($sources) !== 1 || ! is_array($sources[0]) || Cast::string($sources[0]['id'] ?? null) === '') {
            throw new NotionException('Only a Notion database with exactly one data source can be linked.');
        }

        return Cast::string($sources[0]['id']);
    }

    /**
     * Make sure the data source has the columns the mirror writes, adding the
     * missing ones, and return the name of its title column. A column that
     * exists with another type is refused: changing it would rewrite the
     * values people already keep there.
     */
    public function prepareSchema(string $dataSourceId): string
    {
        $path = '/v1/data_sources/'.rawurlencode($dataSourceId);
        $existing = $this->send('GET', $path)['properties'] ?? [];
        $existing = is_array($existing) ? $existing : [];

        $title = null;
        $types = [];

        foreach ($existing as $name => $property) {
            $type = is_array($property) ? Cast::string($property['type'] ?? null) : '';
            $types[(string) $name] = $type;
            $title ??= $type === 'title' ? (string) $name : null;
        }

        if ($title === null) {
            throw new NotionException('That Notion database has no title column.');
        }

        $missing = [];

        foreach (self::COLUMNS as $name => $type) {
            if (! isset($types[$name])) {
                $missing[$name] = [$type => new stdClass];
            } elseif ($types[$name] !== $type) {
                $actual = $types[$name];
                $expected = str_replace('_', ' ', $type);

                throw new NotionException("The \"{$name}\" column must be a {$expected} column, not {$actual}. Rename or remove it in Notion and try again.");
            }
        }

        if ($missing !== []) {
            $this->send('PATCH', $path, ['properties' => $missing]);
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function createPage(string $dataSourceId, array $properties): string
    {
        $page = $this->send('POST', '/v1/pages', [
            'parent' => ['type' => 'data_source_id', 'data_source_id' => $dataSourceId],
            'properties' => $properties,
        ]);

        return Cast::string($page['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function updatePage(string $pageId, array $properties, bool $trashed): void
    {
        $this->send('PATCH', '/v1/pages/'.rawurlencode($pageId), [
            'properties' => $properties,
            'in_trash' => $trashed,
        ]);
    }

    public function trashPage(string $pageId): void
    {
        $this->send('PATCH', '/v1/pages/'.rawurlencode($pageId), ['in_trash' => true]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    private function send(string $method, string $path, array $body = []): array
    {
        if (! $this->integrations->notion()) {
            throw new NotionException('Notion is not configured.');
        }

        $response = Http::baseUrl(self::BASE_URL)
            ->withToken(Cast::string(config('services.notion.api_key')))
            ->withHeaders(['Notion-Version' => Cast::string(config('services.notion.version'))])
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(15)
            ->send($method, $path, $method === 'GET' ? [] : ['json' => $body]);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function failure(Response $response): NotionException
    {
        $status = $response->status();
        $code = Cast::string($response->json('code'));
        $message = "Notion responded with {$status} ({$code}): ".mb_substr(Cast::string($response->json('message')), 0, 200);

        return in_array($status, [429, 529], true)
            ? new NotionRateLimited($message, $status, $this->retryAfter($response))
            : new NotionException($message, $status);
    }

    private function retryAfter(Response $response): int
    {
        $header = $response->header('Retry-After');

        return ctype_digit($header)
            ? max(1, min(self::MAX_RETRY_AFTER, (int) $header))
            : self::DEFAULT_RETRY_AFTER;
    }
}
