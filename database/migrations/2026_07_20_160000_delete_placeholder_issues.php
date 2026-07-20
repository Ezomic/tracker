<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rows that are not tickets and never were. THI-1 through THI-4 are
     * Linear's stock onboarding content, imported verbatim at cutover; their
     * descriptions still carry uploads.linear.app asset URLs. The rest are
     * test and canary artefacts.
     *
     * Keyed by identifier with the exact title expected at that identifier,
     * so this can only ever delete the row it was written for.
     *
     * @var array<string, string>
     */
    private const PLACEHOLDERS = [
        'THI-1' => 'Get familiar with Linear',
        'THI-2' => 'Connect your tools',
        'THI-3' => 'Import your data',
        'THI-4' => 'Set up your teams',
        'THI-5' => 'test',
        'CHRON-1' => 'probe',
        'TRACK-129' => '__canary_TRACK-127_verify',
    ];

    /**
     * Deletion is irreversible, so this refuses to act on anything that does
     * not look exactly like what was surveyed: the title must match, and the
     * issue must still have no comments, time entries, commits or children.
     * A mismatch skips the row rather than destroying real work.
     *
     * Numbering gaps are expected and already normal here after the July
     * re-file, so nothing is renumbered.
     */
    public function up(): void
    {
        foreach (self::PLACEHOLDERS as $identifier => $expectedTitle) {
            $issue = DB::table('issues')
                ->where('identifier', $identifier)
                ->first(['id', 'title']);

            if ($issue === null || $issue->title !== $expectedTitle) {
                continue;
            }

            $hasDependents = DB::table('comments')->where('issue_id', $issue->id)->exists()
                || DB::table('time_entries')->where('issue_id', $issue->id)->exists()
                || DB::table('commits')->where('issue_id', $issue->id)->exists()
                || DB::table('issues')->where('parent_id', $issue->id)->exists();

            if ($hasDependents) {
                continue;
            }

            DB::table('issue_label')->where('issue_id', $issue->id)->delete();
            DB::table('activities')->where('issue_id', $issue->id)->delete();
            DB::table('issues')->where('id', $issue->id)->delete();
        }
    }

    /**
     * Not reversible: the rows and their activity are gone. Recreating empty
     * placeholders would be worse than leaving the gap.
     */
    public function down(): void
    {
        //
    }
};
