<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Issue;

final class NotionIssueProperties
{
    private const TEXT_LIMIT = 2000;

    private const OPTION_LIMIT = 100;

    private const MAX_LABELS = 100;

    /**
     * The Notion page properties for an issue. The title goes in whatever the
     * database calls its title column; the rest are the columns Notion::COLUMNS
     * describes. Free text is sent as literal text, so markdown is not
     * interpreted, and cut to what Notion accepts.
     *
     * @return array<string, mixed>
     */
    public static function for(Issue $issue, string $titleProperty): array
    {
        $state = $issue->workflowState->name ?? $issue->status->value;

        return [
            $titleProperty => ['title' => self::text($issue->title)],
            'Key' => ['rich_text' => self::text($issue->identifier)],
            'State' => ['select' => self::option($state)],
            'Priority' => ['select' => self::option($issue->priority->value)],
            'Assignee' => ['rich_text' => self::text($issue->assignee->name ?? '')],
            'Labels' => ['multi_select' => self::options($issue)],
            'Link' => ['url' => url("/issues/{$issue->identifier}")],
        ];
    }

    /**
     * @return list<array{text: array{content: string}}>
     */
    private static function text(string $value): array
    {
        return $value === ''
            ? []
            : [['text' => ['content' => mb_substr($value, 0, self::TEXT_LIMIT)]]];
    }

    /**
     * Notion option names cannot contain commas, are unique ignoring case, and
     * cannot be empty.
     *
     * @return array{name: string}|null
     */
    private static function option(string $name): ?array
    {
        $clean = mb_substr(trim(preg_replace('/\s*,\s*/u', ' ', $name) ?? ''), 0, self::OPTION_LIMIT);

        return $clean === '' ? null : ['name' => $clean];
    }

    /**
     * @return list<array{name: string}>
     */
    private static function options(Issue $issue): array
    {
        $options = [];

        foreach ($issue->labels()->orderBy('labels.name')->pluck('labels.name') as $name) {
            $option = self::option(Cast::string($name));

            if ($option !== null) {
                $options[mb_strtolower($option['name'])] ??= $option;
            }
        }

        return array_slice(array_values($options), 0, self::MAX_LABELS);
    }
}
