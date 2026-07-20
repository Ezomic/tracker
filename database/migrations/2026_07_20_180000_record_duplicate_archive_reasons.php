<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Archived issues that duplicate another issue which carried the same
     * work. They were already archived, but with no reason recorded, so they
     * read as abandoned rather than superseded and give no pointer to the
     * ticket that actually shipped.
     *
     * ST-46..64 map one-to-one onto ST-67..85. ZERO-43 and THI-291 are both
     * copies of ZERO-45, so that work exists three times. ARBO-86/87 differ
     * from the rest in being done rather than backlog: the same work was
     * closed twice.
     *
     * Keyed by duplicate, holding the superseding identifier and the title
     * expected at the duplicate.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const DUPLICATES = [
        'ARBO-86' => ['ARBO-66', 'Add reintegration milestone timeline to case detail (Wet Poortwachter)'],
        'ARBO-87' => ['ARBO-67', 'Add task type conditions for auto-generating dossier tasks'],
        'ST-46' => ['ST-67', 'Epic: More Content, Better Practice'],
        'ST-47' => ['ST-68', 'Split lessons into Learn & Exercise phases'],
        'ST-48' => ['ST-69', 'Adaptive exercise count (practice until confident)'],
        'ST-49' => ['ST-70', 'Redo-your-mistakes retry queue in the exercise phase'],
        'ST-50' => ['ST-71', 'Non-repeating, shuffled exercise order on lesson redo'],
        'ST-51' => ['ST-72', 'Master mode per lesson with a mistake budget'],
        'ST-52' => ['ST-73', 'Reconcile & migrate lesson progress to two-phase shape'],
        'ST-53' => ['ST-74', 'Expand curriculum with Unit 5 and fill thin units'],
        'ST-54' => ['ST-75', 'Expand the Play exercises catalog'],
        'ST-55' => ['ST-76', 'Expand the theory scale & chord catalogs'],
        'ST-56' => ['ST-77', 'Expand ear-training drill variety'],
        'ST-57' => ['ST-78', 'Add Riff content type and library page'],
        'ST-58' => ['ST-79', 'Build the mic-matched riff player flow'],
        'ST-59' => ['ST-80', 'Seed riff catalog with progress & achievements'],
        'ST-60' => ['ST-81', 'Add chord voicing model and diagram component'],
        'ST-61' => ['ST-82', 'Build the chord library browser page'],
        'ST-62' => ['ST-83', 'Add practice routine model with tempo goals'],
        'ST-63' => ['ST-84', 'Build routine runner and Daily Mix integration'],
        'ST-64' => ['ST-85', 'Add a no-instrument mode that skips Play items'],
        'THI-291' => ['ZERO-45', 'Remove dead ProfileController and profile views (never routed)'],
        'ZERO-34' => ['ZERO-20', 'Migrate Outlook mail reading to Microsoft Graph Mail API'],
        'ZERO-43' => ['ZERO-45', 'Remove dead ProfileController and profile views (never routed)'],
    ];

    /**
     * Only fills a reason that is missing, only on issues already archived,
     * only when the title still matches what was surveyed, and only when the
     * superseding issue exists. Nothing is archived, unarchived, or restatused
     * here. Re-running is a no-op.
     */
    public function up(): void
    {
        foreach (self::DUPLICATES as $identifier => [$supersededBy, $expectedTitle]) {
            $issue = DB::table('issues')
                ->where('identifier', $identifier)
                ->first(['id', 'title', 'archived_at', 'archive_reason']);

            if ($issue === null || $issue->title !== $expectedTitle) {
                continue;
            }

            if ($issue->archived_at === null || $issue->archive_reason !== null) {
                continue;
            }

            if (! DB::table('issues')->where('identifier', $supersededBy)->exists()) {
                continue;
            }

            DB::table('issues')->where('id', $issue->id)->update([
                'archive_reason' => "Duplicate of {$supersededBy}, which covers the same work.",
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Clearing the reasons again would lose the only record of why these were
     * archived, and they were empty before only because nobody filled them in.
     */
    public function down(): void
    {
        //
    }
};
