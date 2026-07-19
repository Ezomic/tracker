<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ConfirmIssueTimeAction;
use App\Actions\LogTimeAction;
use App\Actions\ReportTimeToBillrAction;
use App\Http\Requests\ConfirmTimeRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Models\Issue;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeEntryController extends Controller
{
    public function store(StoreTimeEntryRequest $request, Issue $issue, LogTimeAction $action): RedirectResponse
    {
        $this->authorize('update', $issue);

        $action->handle(
            $issue,
            $request->user(),
            $request->validated('duration'),
            $request->validated('spent_on'),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time logged.')]);

        return back();
    }

    public function confirmTime(ConfirmTimeRequest $request, Issue $issue, ConfirmIssueTimeAction $action, ReportTimeToBillrAction $reportAction): RedirectResponse
    {
        $this->authorize('update', $issue);

        $reported = $action->handle(
            $issue,
            $request->user(),
            $request->integer('minutes'),
            $request->validated('billr_client_name'),
            $reportAction,
        );

        Inertia::flash('toast', $reported
            ? ['type' => 'success', 'message' => __('Time confirmed.')]
            : ['type' => 'error', 'message' => __('Time confirmed, but reporting it to Billr failed.')]);

        return back();
    }

    public function destroy(Request $request, Issue $issue, TimeEntry $timeEntry): RedirectResponse
    {
        abort_unless($timeEntry->issue_id === $issue->id, 404);

        // You can always remove your own entry; otherwise it takes project admin.
        if ($timeEntry->user_id !== $request->user()->id) {
            $this->authorize('delete', $issue);
        } else {
            $this->authorize('view', $issue);
        }

        $timeEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time entry removed.')]);

        return back();
    }
}
