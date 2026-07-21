<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('teams:seed {key} {name} {next_number=0 : Counter floor to seed - never lowers an existing counter}')]
#[Description('Create or update a team with a next_number floor, for seeding a counter from an external source (e.g. Linear) before cutover')]
class SeedTeamCommand extends Command
{
    public function handle(): int
    {
        $key = strtoupper($this->argument('key'));
        $name = $this->argument('name');
        $requestedNumber = (int) $this->argument('next_number');

        $team = Project::query()->where('key', $key)->first();

        if ($team === null) {
            (new Project)->forceFill([
                'key' => $key,
                'name' => $name,
                'next_number' => $requestedNumber,
            ])->save();

            $this->info("Created team [{$key}] with next_number={$requestedNumber}.");

            return self::SUCCESS;
        }

        $previousNumber = $team->next_number;
        $seededNumber = max($previousNumber, $requestedNumber);

        if ($seededNumber > $previousNumber) {
            $team->forceFill(['name' => $name, 'next_number' => $seededNumber])->save();
            $this->info("Updated team [{$key}]: next_number {$previousNumber} -> {$seededNumber}.");
        } else {
            $this->info("Team [{$key}] already has next_number={$previousNumber} (>= requested {$requestedNumber}); left unchanged.");
        }

        return self::SUCCESS;
    }
}
