<?php

declare(strict_types=1);

/*
| config/issue_sources.php reads SNAG_URL and FLARE_URL straight from the
| environment, so these tests set the variables and load the file themselves.
*/

const ISSUE_SOURCE_VARIABLES = ['SNAG_URL', 'FLARE_URL'];

beforeEach(function () {
    $this->savedEnvironment = collect(ISSUE_SOURCE_VARIABLES)
        ->mapWithKeys(fn (string $name) => [$name => [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)]])
        ->all();
});

afterEach(function () {
    foreach ($this->savedEnvironment as $name => [$server, $env, $process]) {
        setIssueSourceVariable($name, null);

        if ($server !== null) {
            $_SERVER[$name] = $server;
        }

        if ($env !== null) {
            $_ENV[$name] = $env;
        }

        if ($process !== false) {
            putenv("{$name}={$process}");
        }
    }
});

function setIssueSourceVariable(string $name, ?string $value): void
{
    unset($_SERVER[$name], $_ENV[$name]);
    putenv($name);

    if ($value !== null) {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

/**
 * @return array<string, array{label: string, url: string|null}>
 */
function loadIssueSources(): array
{
    return require config_path('issue_sources.php');
}

it('links snag and flare reports to the configured hosts', function () {
    setIssueSourceVariable('SNAG_URL', 'https://snag.example.test');
    setIssueSourceVariable('FLARE_URL', 'https://flare.example.test');

    $sources = loadIssueSources();

    expect($sources['snag']['url'])->toBe('https://snag.example.test/reports/:ref')
        ->and($sources['flare']['url'])->toBe('https://flare.example.test/errors/:ref');
});

it('leaves snag and flare reports unlinked when their hosts are not configured', function (?string $value) {
    foreach (ISSUE_SOURCE_VARIABLES as $name) {
        setIssueSourceVariable($name, $value);
    }

    $sources = loadIssueSources();

    expect($sources['snag'])->toBe(['label' => 'Snag', 'url' => null])
        ->and($sources['flare'])->toBe(['label' => 'Flare', 'url' => null]);
})->with('blank');
