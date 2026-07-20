<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TRACK-140 backfilled the 301 active issues. The API it drew its list
     * from applies notArchived(), so the 381 archived issues were missed and
     * still carry no priority, assignee, estimate or labels.
     *
     * Same approach: values derived per ticket from its title, so a considered
     * reconstruction rather than a record of original intent. Each row is
     * [priority, estimate minutes, labels].
     *
     * @var array<string, array{0: string, 1: int, 2: list<string>}>
     */
    private const BACKFILL = [
        'ARBO-1' => ['medium', 120, ['database']],
        'ARBO-2' => ['medium', 120, ['database']],
        'ARBO-3' => ['medium', 120, ['database', 'security']],
        'ARBO-4' => ['medium', 120, ['database']],
        'ARBO-5' => ['medium', 120, ['database']],
        'ARBO-6' => ['medium', 120, ['database']],
        'ARBO-7' => ['medium', 120, ['database']],
        'ARBO-8' => ['medium', 240, ['api', 'backend']],
        'ARBO-9' => ['high', 240, ['security', 'backend']],
        'ARBO-10' => ['high', 360, ['security', 'backend']],
        'ARBO-11' => ['high', 480, ['security']],
        'ARBO-12' => ['high', 240, ['security', 'backend']],
        'ARBO-13' => ['high', 360, ['security', 'backend']],
        'ARBO-14' => ['urgent', 120, ['security']],
        'ARBO-15' => ['high', 240, ['security']],
        'ARBO-16' => ['high', 480, ['security', 'database']],
        'ARBO-17' => ['high', 240, ['security', 'api']],
        'ARBO-18' => ['medium', 120, ['docs', 'frontend']],
        'ARBO-19' => ['high', 360, ['security']],
        'ARBO-20' => ['high', 360, ['security', 'api']],
        'ARBO-21' => ['medium', 240, ['backend']],
        'ARBO-22' => ['medium', 480, ['backend']],
        'ARBO-23' => ['medium', 240, ['backend']],
        'ARBO-24' => ['medium', 240, ['backend', 'infra']],
        'ARBO-25' => ['high', 120, ['security', 'api']],
        'ARBO-26' => ['medium', 120, ['infra', 'tech-debt']],
        'ARBO-27' => ['medium', 120, ['backend', 'security']],
        'ARBO-28' => ['low', 120, ['database']],
        'ARBO-29' => ['medium', 240, ['frontend', 'security']],
        'ARBO-30' => ['high', 360, ['backend', 'security']],
        'ARBO-31' => ['high', 240, ['security', 'backend']],
        'ARBO-32' => ['high', 240, ['security']],
        'ARBO-33' => ['medium', 240, ['frontend']],
        'ARBO-34' => ['medium', 360, ['backend', 'frontend']],
        'ARBO-35' => ['medium', 360, ['frontend']],
        'ARBO-36' => ['high', 360, ['security']],
        'ARBO-37' => ['medium', 240, ['backend']],
        'ARBO-38' => ['medium', 480, ['frontend', 'security']],
        'ARBO-39' => ['medium', 480, ['ui']],
        'ARBO-40' => ['medium', 240, ['security']],
        'ARBO-41' => ['urgent', 240, ['security']],
        'ARBO-42' => ['low', 120, ['frontend']],
        'ARBO-43' => ['high', 120, ['backend']],
        'ARBO-44' => ['high', 120, ['infra']],
        'ARBO-45' => ['medium', 45, ['infra']],
        'ARBO-46' => ['high', 240, ['backend', 'security']],
        'ARBO-47' => ['high', 120, ['infra']],
        'ARBO-48' => ['high', 120, ['backend']],
        'ARBO-49' => ['high', 120, ['security']],
        'ARBO-50' => ['high', 120, ['backend']],
        'ARBO-51' => ['high', 120, ['infra']],
        'ARBO-52' => ['high', 120, ['infra']],
        'ARBO-53' => ['medium', 240, ['backend']],
        'ARBO-54' => ['medium', 240, ['backend', 'security']],
        'ARBO-55' => ['high', 240, ['security']],
        'ARBO-56' => ['medium', 120, ['tech-debt']],
        'ARBO-57' => ['medium', 240, ['tech-debt']],
        'ARBO-58' => ['high', 120, ['infra']],
        'ARBO-59' => ['high', 120, ['infra']],
        'ARBO-60' => ['medium', 120, ['tech-debt']],
        'ARBO-61' => ['medium', 120, ['tech-debt']],
        'ARBO-62' => ['medium', 120, ['tech-debt']],
        'ARBO-63' => ['medium', 120, ['infra']],
        'ARBO-64' => ['medium', 240, ['tech-debt']],
        'ARBO-65' => ['high', 120, ['security', 'backend']],
        'ARBO-66' => ['medium', 360, ['frontend', 'backend']],
        'ARBO-67' => ['medium', 240, ['backend']],
        'ARBO-68' => ['medium', 240, ['backend']],
        'ARBO-69' => ['low', 45, ['backend']],
        'ARBO-70' => ['medium', 480, ['backend', 'security']],
        'ARBO-71' => ['medium', 480, ['backend', 'frontend']],
        'ARBO-72' => ['medium', 480, ['backend', 'security']],
        'ARBO-73' => ['high', 480, ['security', 'backend']],
        'ARBO-74' => ['low', 120, ['ui']],
        'ARBO-75' => ['medium', 240, ['ui', 'frontend']],
        'ARBO-76' => ['medium', 240, ['ui']],
        'ARBO-77' => ['medium', 240, ['database', 'security']],
        'ARBO-78' => ['medium', 120, ['security']],
        'ARBO-79' => ['medium', 240, ['docs', 'security']],
        'ARBO-80' => ['urgent', 240, ['security']],
        'ARBO-81' => ['high', 240, ['security']],
        'ARBO-82' => ['urgent', 240, ['security']],
        'ARBO-83' => ['high', 240, ['security']],
        'ARBO-84' => ['high', 120, ['security']],
        'ARBO-85' => ['medium', 240, ['security']],
        'ARBO-86' => ['low', 360, ['frontend', 'backend']],
        'ARBO-87' => ['low', 240, ['backend']],
        'BILLR-1' => ['medium', 240, ['frontend']],
        'BILLR-3' => ['medium', 240, ['backend']],
        'BILLR-5' => ['high', 360, ['backend', 'api']],
        'BILLR-6' => ['medium', 480, ['frontend', 'backend']],
        'BILLR-7' => ['medium', 360, ['backend']],
        'BILLR-10' => ['medium', 240, ['tech-debt']],
        'BILLR-11' => ['medium', 240, ['infra']],
        'BILLR-12' => ['medium', 240, ['tech-debt']],
        'BILLR-13' => ['medium', 120, ['database', 'performance']],
        'BILLR-14' => ['medium', 360, ['backend']],
        'BILLR-15' => ['high', 480, ['security']],
        'CHRON-1' => ['low', 45, ['tech-debt']],
        'CMS-1' => ['medium', 240, ['tech-debt']],
        'CMS-2' => ['medium', 240, ['infra']],
        'CMS-3' => ['low', 120, ['backend']],
        'CMS-4' => ['low', 120, ['docs']],
        'CMS-5' => ['low', 45, ['docs']],
        'CMS-6' => ['medium', 360, ['frontend']],
        'CMS-7' => ['medium', 240, ['docs', 'frontend']],
        'CMS-8' => ['low', 120, ['frontend']],
        'CMS-9' => ['medium', 240, ['backend']],
        'CMS-10' => ['medium', 360, ['backend']],
        'CMS-11' => ['medium', 360, ['frontend']],
        'CMS-12' => ['medium', 240, ['frontend']],
        'CMS-13' => ['low', 120, ['frontend']],
        'CMS-14' => ['low', 120, ['frontend', 'ui']],
        'CMS-15' => ['medium', 240, ['security']],
        'CMS-16' => ['low', 45, ['frontend']],
        'CMS-17' => ['medium', 120, ['frontend']],
        'CMS-18' => ['medium', 120, ['docs', 'frontend']],
        'CMS-19' => ['high', 120, ['frontend']],
        'CMS-20' => ['medium', 240, ['frontend']],
        'CMS-21' => ['medium', 240, ['frontend']],
        'CMS-22' => ['low', 120, ['database', 'frontend']],
        'CMS-23' => ['low', 120, ['frontend']],
        'CMS-24' => ['low', 45, ['database']],
        'CMS-25' => ['medium', 360, ['docs']],
        'CMS-26' => ['medium', 240, ['frontend', 'ui']],
        'CMS-27' => ['medium', 120, ['backend']],
        'CMS-28' => ['medium', 240, ['backend', 'frontend']],
        'CMS-29' => ['medium', 240, ['frontend']],
        'CMS-30' => ['high', 360, ['frontend']],
        'CMS-31' => ['medium', 120, ['frontend', 'docs']],
        'CMS-32' => ['high', 960, ['ui']],
        'CMS-33' => ['urgent', 120, ['infra']],
        'CMS-34' => ['medium', 480, ['ui']],
        'CMS-35' => ['high', 960, ['backend', 'frontend']],
        'CMS-36' => ['urgent', 120, ['database']],
        'CMS-37' => ['medium', 360, ['ui']],
        'CMS-38' => ['high', 45, ['infra']],
        'CMS-39' => ['high', 45, ['infra']],
        'CMS-44' => ['medium', 240, ['frontend']],
        'CMS-45' => ['medium', 240, ['frontend']],
        'CMS-46' => ['medium', 120, ['backend']],
        'CMS-47' => ['medium', 240, ['backend']],
        'CMS-48' => ['low', 45, ['security']],
        'CMS-49' => ['low', 120, ['backend']],
        'CMS-50' => ['low', 120, ['backend']],
        'CMS-51' => ['medium', 240, ['frontend']],
        'CMS-52' => ['medium', 240, ['backend', 'frontend']],
        'CMS-53' => ['urgent', 45, ['security']],
        'CMS-54' => ['urgent', 120, ['infra']],
        'CMS-55' => ['medium', 45, ['security']],
        'CMS-56' => ['medium', 240, ['database', 'backend']],
        'CMS-57' => ['urgent', 120, ['infra']],
        'CMS-58' => ['low', 45, ['frontend']],
        'CMS-59' => ['low', 45, ['docs']],
        'CMS-60' => ['high', 45, ['security']],
        'CMS-61' => ['low', 120, ['tech-debt']],
        'CMS-62' => ['medium', 120, ['database', 'performance']],
        'CMS-63' => ['low', 120, ['tech-debt']],
        'CMS-64' => ['medium', 240, ['backend', 'database']],
        'FIN-1' => ['high', 240, ['security']],
        'GROC-1' => ['low', 45, ['docs']],
        'GROC-2' => ['medium', 240, ['frontend', 'security']],
        'GROC-3' => ['medium', 240, ['tech-debt']],
        'GROC-4' => ['medium', 240, ['infra']],
        'GROC-5' => ['medium', 240, ['tech-debt']],
        'GROC-6' => ['medium', 480, ['frontend']],
        'GROC-7' => ['low', 45, ['docs']],
        'HAB-1' => ['high', 960, ['frontend']],
        'HAB-2' => ['high', 360, ['security', 'backend']],
        'HAB-3' => ['high', 360, ['database']],
        'HAB-4' => ['high', 480, ['backend']],
        'HAB-5' => ['high', 360, ['backend', 'frontend']],
        'HAB-6' => ['high', 480, ['backend']],
        'HAB-7' => ['high', 360, ['frontend']],
        'HAB-8' => ['medium', 360, ['backend']],
        'HAB-9' => ['medium', 360, ['frontend']],
        'HAB-10' => ['medium', 240, ['frontend']],
        'HAB-11' => ['medium', 240, ['backend']],
        'HAB-12' => ['medium', 240, ['backend']],
        'HAB-13' => ['low', 120, ['frontend']],
        'HAB-17' => ['medium', 360, ['frontend']],
        'HAB-18' => ['low', 120, ['backend']],
        'HAB-19' => ['medium', 240, ['backend']],
        'HAB-20' => ['low', 120, ['backend']],
        'HAB-21' => ['low', 240, ['backend']],
        'HAB-28' => ['high', 120, ['backend', 'database']],
        'HAB-30' => ['medium', 360, ['frontend']],
        'HAB-31' => ['low', 240, ['frontend']],
        'INFRA-2' => ['high', 120, ['infra', 'security']],
        'INFRA-4' => ['medium', 120, ['security']],
        'INFRA-5' => ['low', 120, ['docs']],
        'OBW-24' => ['medium', 240, ['security']],
        'ST-1' => ['high', 960, ['frontend']],
        'ST-2' => ['high', 960, ['frontend']],
        'ST-3' => ['high', 960, ['frontend']],
        'ST-4' => ['high', 960, ['frontend']],
        'ST-5' => ['high', 960, ['frontend']],
        'ST-6' => ['high', 960, ['frontend']],
        'ST-7' => ['high', 960, ['frontend']],
        'ST-8' => ['medium', 120, ['frontend']],
        'ST-9' => ['medium', 240, ['frontend']],
        'ST-10' => ['low', 45, ['infra']],
        'ST-11' => ['medium', 120, ['frontend']],
        'ST-12' => ['medium', 120, ['frontend']],
        'ST-13' => ['medium', 120, ['frontend']],
        'ST-14' => ['low', 120, ['frontend']],
        'ST-15' => ['medium', 120, ['frontend']],
        'ST-16' => ['medium', 480, ['backend']],
        'ST-17' => ['medium', 360, ['frontend']],
        'ST-18' => ['medium', 120, ['frontend']],
        'ST-19' => ['medium', 120, ['frontend']],
        'ST-20' => ['high', 240, ['backend']],
        'ST-21' => ['medium', 120, ['frontend']],
        'ST-22' => ['low', 240, ['backend']],
        'ST-23' => ['urgent', 120, ['backend']],
        'ST-24' => ['low', 120, ['frontend', 'ui']],
        'ST-25' => ['medium', 45, ['frontend']],
        'ST-26' => ['low', 45, ['frontend']],
        'ST-27' => ['medium', 120, ['frontend']],
        'ST-28' => ['low', 120, ['backend']],
        'ST-29' => ['medium', 120, ['frontend']],
        'ST-30' => ['medium', 120, ['frontend']],
        'ST-31' => ['medium', 45, ['frontend']],
        'ST-32' => ['low', 45, ['frontend']],
        'ST-33' => ['low', 45, ['frontend']],
        'ST-34' => ['low', 120, ['backend']],
        'ST-35' => ['low', 240, ['backend']],
        'ST-36' => ['medium', 360, ['backend']],
        'ST-37' => ['low', 240, ['backend']],
        'ST-38' => ['medium', 360, ['frontend']],
        'ST-39' => ['high', 240, ['infra']],
        'ST-40' => ['low', 120, ['backend']],
        'ST-41' => ['low', 120, ['backend']],
        'ST-42' => ['medium', 240, ['backend']],
        'ST-43' => ['low', 240, ['infra']],
        'ST-44' => ['low', 45, ['infra']],
        'ST-45' => ['low', 45, ['infra']],
        'ST-46' => ['low', 960, ['frontend']],
        'ST-47' => ['low', 480, ['frontend']],
        'ST-48' => ['low', 360, ['frontend']],
        'ST-49' => ['low', 240, ['frontend']],
        'ST-50' => ['low', 120, ['frontend']],
        'ST-51' => ['low', 240, ['frontend']],
        'ST-52' => ['low', 240, ['backend']],
        'ST-53' => ['low', 480, ['backend']],
        'ST-54' => ['low', 240, ['backend']],
        'ST-55' => ['low', 240, ['backend']],
        'ST-56' => ['low', 240, ['backend']],
        'ST-57' => ['low', 360, ['backend']],
        'ST-58' => ['low', 480, ['frontend']],
        'ST-59' => ['low', 240, ['backend']],
        'ST-60' => ['low', 360, ['frontend']],
        'ST-61' => ['low', 240, ['frontend']],
        'ST-62' => ['low', 240, ['backend']],
        'ST-63' => ['low', 480, ['frontend']],
        'ST-64' => ['low', 240, ['frontend']],
        'ST-65' => ['low', 45, ['docs']],
        'ST-66' => ['low', 45, ['docs']],
        'ST-68' => ['medium', 480, ['frontend']],
        'ST-69' => ['medium', 360, ['frontend']],
        'ST-70' => ['medium', 240, ['frontend']],
        'ST-71' => ['low', 120, ['frontend']],
        'ST-72' => ['medium', 360, ['frontend']],
        'ST-73' => ['high', 240, ['backend']],
        'ST-74' => ['medium', 480, ['backend']],
        'ST-75' => ['low', 240, ['backend']],
        'ST-76' => ['low', 240, ['backend']],
        'ST-77' => ['low', 240, ['backend']],
        'ST-78' => ['medium', 360, ['backend']],
        'ST-79' => ['medium', 480, ['frontend']],
        'ST-80' => ['medium', 240, ['backend']],
        'ST-81' => ['medium', 360, ['frontend']],
        'ST-82' => ['medium', 240, ['frontend']],
        'ST-83' => ['medium', 240, ['backend']],
        'ST-87' => ['medium', 45, ['tech-debt']],
        'THI-1' => ['low', 45, ['docs']],
        'THI-2' => ['low', 45, ['docs']],
        'THI-3' => ['low', 45, ['docs']],
        'THI-4' => ['low', 45, ['docs']],
        'THI-5' => ['low', 45, ['tech-debt']],
        'THI-291' => ['low', 45, ['tech-debt']],
        'THI-346' => ['medium', 120, ['tech-debt', 'database']],
        'THI-353' => ['medium', 120, ['infra', 'tech-debt']],
        'THI-355' => ['medium', 240, ['backend']],
        'THI-356' => ['low', 45, ['tech-debt']],
        'THI-357' => ['low', 45, ['docs']],
        'THI-364' => ['high', 240, ['infra']],
        'TRACK-1' => ['high', 360, ['database']],
        'TRACK-2' => ['high', 240, ['database', 'backend']],
        'TRACK-3' => ['high', 360, ['api', 'security']],
        'TRACK-4' => ['medium', 360, ['backend']],
        'TRACK-5' => ['high', 480, ['frontend']],
        'TRACK-6' => ['high', 480, ['frontend']],
        'TRACK-7' => ['high', 240, ['infra', 'database']],
        'TRACK-8' => ['high', 240, ['api', 'backend']],
        'TRACK-9' => ['medium', 240, ['backend']],
        'TRACK-10' => ['medium', 120, ['infra']],
        'TRACK-11' => ['medium', 240, ['backend']],
        'TRACK-12' => ['medium', 120, ['database']],
        'TRACK-13' => ['high', 240, ['docs', 'api']],
        'TRACK-14' => ['high', 45, ['security', 'infra']],
        'TRACK-15' => ['high', 360, ['infra']],
        'TRACK-16' => ['medium', 240, ['infra']],
        'TRACK-18' => ['high', 120, ['infra']],
        'TRACK-19' => ['urgent', 120, ['infra', 'database']],
        'TRACK-20' => ['medium', 120, ['api']],
        'TRACK-21' => ['high', 960, ['ui']],
        'TRACK-22' => ['high', 240, ['ui']],
        'TRACK-23' => ['high', 360, ['database']],
        'TRACK-24' => ['high', 240, ['api']],
        'TRACK-25' => ['high', 360, ['frontend']],
        'TRACK-26' => ['high', 360, ['frontend']],
        'TRACK-27' => ['medium', 360, ['frontend']],
        'TRACK-28' => ['high', 360, ['frontend']],
        'TRACK-29' => ['high', 360, ['frontend', 'ui']],
        'TRACK-30' => ['medium', 240, ['frontend']],
        'TRACK-31' => ['medium', 240, ['frontend']],
        'TRACK-32' => ['medium', 120, ['docs']],
        'TRACK-33' => ['medium', 240, ['tech-debt', 'frontend']],
        'TRACK-34' => ['medium', 120, ['api']],
        'TRACK-35' => ['medium', 240, ['api']],
        'TRACK-36' => ['medium', 240, ['database']],
        'TRACK-37' => ['high', 360, ['backend', 'database']],
        'TRACK-38' => ['medium', 120, ['docs']],
        'TRACK-39' => ['medium', 360, ['security']],
        'TRACK-40' => ['medium', 240, ['backend']],
        'TRACK-41' => ['medium', 120, ['database']],
        'TRACK-42' => ['high', 240, ['security', 'infra']],
        'TRACK-43' => ['medium', 240, ['database', 'backend']],
        'TRACK-44' => ['medium', 240, ['frontend']],
        'TRACK-45' => ['high', 480, ['security']],
        'TRACK-46' => ['high', 240, ['security', 'infra']],
        'TRACK-47' => ['urgent', 120, ['infra']],
        'TRACK-48' => ['medium', 120, ['api']],
        'TRACK-49' => ['medium', 360, ['frontend', 'ui']],
        'TRACK-51' => ['low', 120, ['database']],
        'TRACK-52' => ['medium', 120, ['database']],
        'TRACK-53' => ['low', 120, ['frontend']],
        'TRACK-55' => ['medium', 45, ['ui']],
        'TRACK-56' => ['medium', 240, ['frontend', 'api']],
        'TRACK-57' => ['low', 120, ['frontend']],
        'TRACK-58' => ['low', 45, ['database']],
        'TRACK-59' => ['low', 120, ['frontend']],
        'TRACK-84' => ['low', 240, ['api']],
        'TRACK-99' => ['medium', 240, ['api']],
        'TRACK-101' => ['low', 360, ['backend']],
        'TRACK-129' => ['low', 45, ['tech-debt']],
        'ZERO-1' => ['medium', 120, ['docs']],
        'ZERO-2' => ['medium', 240, ['tech-debt']],
        'ZERO-3' => ['medium', 240, ['infra']],
        'ZERO-4' => ['low', 45, ['docs']],
        'ZERO-5' => ['low', 120, ['docs']],
        'ZERO-6' => ['medium', 120, ['performance', 'backend']],
        'ZERO-7' => ['urgent', 120, ['backend']],
        'ZERO-8' => ['high', 120, ['backend']],
        'ZERO-9' => ['high', 120, ['backend', 'database']],
        'ZERO-10' => ['high', 480, ['frontend']],
        'ZERO-11' => ['high', 240, ['infra']],
        'ZERO-12' => ['high', 960, ['ui']],
        'ZERO-13' => ['high', 480, ['frontend']],
        'ZERO-14' => ['high', 240, ['backend']],
        'ZERO-15' => ['high', 240, ['infra']],
        'ZERO-16' => ['medium', 120, ['tech-debt']],
        'ZERO-17' => ['medium', 120, ['infra']],
        'ZERO-18' => ['low', 45, ['tech-debt']],
        'ZERO-19' => ['medium', 240, ['infra']],
        'ZERO-21' => ['high', 120, ['backend']],
        'ZERO-22' => ['medium', 240, ['performance', 'backend']],
        'ZERO-24' => ['high', 480, ['security']],
        'ZERO-25' => ['high', 120, ['backend']],
        'ZERO-26' => ['high', 120, ['infra']],
        'ZERO-27' => ['urgent', 120, ['infra']],
        'ZERO-28' => ['medium', 360, ['frontend', 'tech-debt']],
        'ZERO-29' => ['medium', 120, ['performance', 'backend']],
        'ZERO-30' => ['urgent', 120, ['infra']],
        'ZERO-32' => ['high', 360, ['security']],
        'ZERO-33' => ['high', 45, ['security']],
        'ZERO-34' => ['low', 480, ['backend', 'api']],
        'ZERO-40' => ['medium', 45, ['infra']],
        'ZERO-41' => ['high', 120, ['infra']],
        'ZERO-43' => ['low', 45, ['tech-debt']],
    ];

    /**
     * Guarded per field exactly as TRACK-140: a value is only written where
     * the column is still empty, labels only attach to issues with none, and
     * the assignee is only set when the project has exactly one member.
     */
    public function up(): void
    {
        foreach (self::BACKFILL as $identifier => [$priority, $minutes, $labels]) {
            $issue = DB::table('issues')
                ->where('identifier', $identifier)
                ->first(['id', 'project_id', 'priority', 'assignee_id', 'estimate_minutes']);

            if ($issue === null) {
                continue;
            }

            $updates = [];

            if ($issue->priority === 'none') {
                $updates['priority'] = $priority;
            }

            if ($issue->estimate_minutes === null) {
                $updates['estimate_minutes'] = $minutes;
            }

            if ($issue->assignee_id === null) {
                $members = DB::table('project_user')
                    ->where('project_id', $issue->project_id)
                    ->pluck('user_id');

                if ($members->count() === 1) {
                    $updates['assignee_id'] = $members->first();
                }
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('issues')->where('id', $issue->id)->update($updates);
            }

            $alreadyLabelled = DB::table('issue_label')->where('issue_id', $issue->id)->exists();

            if ($alreadyLabelled) {
                continue;
            }

            $organizationId = DB::table('projects')->where('id', $issue->project_id)->value('organization_id');

            $labelIds = DB::table('labels')
                ->where('organization_id', $organizationId)
                ->whereIn('name', $labels)
                ->pluck('id');

            if ($labelIds->isEmpty()) {
                continue;
            }

            DB::table('issue_label')->insert(
                $labelIds->map(fn (int $labelId): array => [
                    'issue_id' => $issue->id,
                    'label_id' => $labelId,
                ])->all()
            );
        }
    }

    /**
     * Deliberately empty, as in TRACK-140: this cannot tell a value it wrote
     * from one set by hand afterwards, and guessing would destroy real data.
     */
    public function down(): void
    {
        //
    }
};
