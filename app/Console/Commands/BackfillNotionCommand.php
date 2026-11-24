<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\BackfillIssuesToNotionAction;
use App\Models\Project;
use App\Services\Integrations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notion:backfill {project : The project key}')]
#[Description('Queue the issues of a linked project that are not in its Notion database yet.')]
class BackfillNotionCommand extends Command
{
    public function handle(Integrations $integrations, BackfillIssuesToNotionAction $action): int
    {
        if (! $integrations->notion()) {
            $this->error('Notion is not configured.');

            return self::FAILURE;
        }

        $key = $this->argument('project');
        $project = Project::query()->where('key', $key)->first();

        if ($project === null) {
            $this->error("No project with key {$key}.");

            return self::FAILURE;
        }

        if (! $project->isLinkedToNotion()) {
            $this->error("Project {$key} is not linked to a Notion database.");

            return self::FAILURE;
        }

        $this->info("Queued {$action->handle($project)} issue(s) for Notion.");

        return self::SUCCESS;
    }
}
