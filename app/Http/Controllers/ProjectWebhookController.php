<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeliverWebhookAction;
use App\Http\Requests\StoreProjectWebhookRequest;
use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectWebhookController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('projects/Webhooks', [
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
            ],
            'webhooks' => $project->webhooks()
                ->orderBy('id')
                ->get()
                ->map(fn (ProjectWebhook $webhook): array => [
                    'id' => $webhook->id,
                    'url' => $webhook->url,
                    'active' => $webhook->active,
                    'lastDeliveredAtDiff' => $webhook->last_delivered_at?->diffForHumans(),
                    'lastStatus' => $webhook->last_status,
                    'lastError' => $webhook->last_error,
                ])
                ->all(),
            'signatureHeader' => DeliverWebhookAction::SIGNATURE_HEADER,
            // Flashed once by store(), through the redirect, then gone.
            'createdSecret' => session('createdSecret'),
        ]);
    }

    public function store(StoreProjectWebhookRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $secret = Str::random(48);

        // The secret is deliberately not fillable: it is generated here, never
        // accepted from a request.
        $project->webhooks()->make()->forceFill([
            'url' => $request->string('url')->toString(),
            'secret' => $secret,
            'active' => true,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook added.')]);

        return to_route('projects.webhooks.index', $project->key)->with('createdSecret', $secret);
    }

    /**
     * Send a ping so the endpoint can be checked before an issue ever moves.
     * Delivered inline rather than queued: the point is to report the result.
     */
    public function test(Project $project, ProjectWebhook $webhook, DeliverWebhookAction $action): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($webhook->project_id === $project->id, 404);

        try {
            $action->handle($webhook, 'ping', [
                'event' => 'ping',
                'project' => $project->key,
                'sent_at' => now()->toIso8601String(),
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook responded successfully.')]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Webhook failed: :message', ['message' => $e->getMessage()])]);
        }

        return to_route('projects.webhooks.index', $project->key);
    }

    public function update(Request $request, Project $project, ProjectWebhook $webhook): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($webhook->project_id === $project->id, 404);

        $webhook->update(['active' => $request->boolean('active')]);

        return to_route('projects.webhooks.index', $project->key);
    }

    public function destroy(Project $project, ProjectWebhook $webhook): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($webhook->project_id === $project->id, 404);

        $webhook->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook removed.')]);

        return to_route('projects.webhooks.index', $project->key);
    }
}
