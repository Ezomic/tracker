<?php

declare(strict_types=1);

use App\Support\NotionDatabaseId;

const NOTION_HEX = '0123456789abcdef0123456789abcdef';

it('normalises every accepted spelling to 32 lowercase hex characters', function (string $input) {
    expect(NotionDatabaseId::normalise($input))->toBe(NOTION_HEX);
})->with([
    'bare' => NOTION_HEX,
    'uppercase' => '0123456789ABCDEF0123456789ABCDEF',
    'dashed' => '01234567-89ab-cdef-0123-456789abcdef',
    'surrounding whitespace' => "  \t".NOTION_HEX."\n",
    'url with title slug' => 'https://www.notion.so/My-Tasks-'.NOTION_HEX,
    'url with workspace and view id' => 'https://www.notion.so/acme/My-Tasks-'.NOTION_HEX.'?v=fedcba9876543210fedcba9876543210&pvs=4',
    'url without a title' => 'https://notion.so/'.NOTION_HEX,
    'url with a fragment' => 'https://www.notion.so/'.NOTION_HEX.'#section',
    'notion.site url' => 'https://acme.notion.site/My-Tasks-'.NOTION_HEX,
    'url with unicode title' => 'https://www.notion.so/Tâches-'.NOTION_HEX,
]);

it('rejects anything that is not a database id', function (mixed $input) {
    expect(NotionDatabaseId::normalise($input))->toBeNull();
})->with([
    'null' => [null],
    'an integer' => [12345],
    'an array' => [[NOTION_HEX]],
    'empty' => [''],
    'whitespace only' => ['   '],
    'too short' => ['0123456789abcdef'],
    'one char too long' => [NOTION_HEX.'0'],
    'non hex' => ['zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'],
    'unicode lookalike digits' => ['０１２３４５６７８９abcdef0123456789abcdef'],
    'misplaced dashes' => ['0123-456789abcdef0123456789abcdef-'],
    'foreign host' => ['https://evil.example/'.NOTION_HEX],
    'host that merely ends in notion.so' => ['https://evilnotion.so/'.NOTION_HEX],
    'notion in the path of another host' => ['https://evil.example/notion.so/'.NOTION_HEX],
    'userinfo trick' => ['https://www.notion.so@evil.example/'.NOTION_HEX],
    'plain http' => ['http://www.notion.so/'.NOTION_HEX],
    'javascript scheme' => ['javascript:alert(1)//notion.so/'.NOTION_HEX],
    'notion url with no id' => ['https://www.notion.so/My-Tasks'],
    'id only in the query' => ['https://www.notion.so/My-Tasks?v='.NOTION_HEX],
    'very long' => [str_repeat('a', 100000)],
    'very long url' => ['https://www.notion.so/'.str_repeat('a', 5000).NOTION_HEX],
]);
