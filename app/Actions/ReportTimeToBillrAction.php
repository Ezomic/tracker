<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use Illuminate\Support\Facades\Http;

class ReportTimeToBillrAction
{
    /**
     * Report an issue's confirmed time to Billr as a billable entry. The
     * first call for a project sends its name plus a client name so Billr
     * can match or create both; every call after that sends the cached
     * billr_project_id directly, once the project is linked.
     */
    public function handle(Issue $issue, int $minutes, ?string $clientName): void
    {
        $project = $issue->project;

        $payload = [
            'external_source' => 'tracker',
            'external_ref' => $issue->identifier,
            'minutes' => $minutes,
            'spent_on' => now()->toDateString(),
            'description' => $issue->title,
            'billable' => true,
        ];

        if ($project->billrLinked()) {
            $payload['billr_project_id'] = $project->billr_project_id;
        } else {
            $payload['client_name'] = $clientName;
            $payload['project_name'] = $project->name;
        }

        $response = Http::withToken(config('services.billr.token'))
            ->baseUrl(config('services.billr.base_url'))
            ->post('/api/time-entries', $payload)
            ->throw();

        if (! $project->billrLinked()) {
            $project->forceFill([
                'billr_project_id' => $response->json('project_id'),
                'billr_client_id' => $response->json('client_id'),
            ])->save();
        }
    }
}
