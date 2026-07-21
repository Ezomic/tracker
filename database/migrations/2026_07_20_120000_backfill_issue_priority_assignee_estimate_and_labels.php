<?php

declare(strict_types=1);

use App\Support\Cast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every issue predating the ticket skill's required fields was created
     * with no priority, no assignee, no estimate and (mostly) no labels.
     *
     * Values were derived per ticket from its title, so they are a considered
     * reconstruction rather than a record of what was originally intended.
     * Each row is [priority, estimate minutes, labels]; an empty label list
     * means the issue already carries curated labels and is left alone.
     *
     * @var array<string, array{0: string, 1: int, 2: list<string>}>
     */
    private const BACKFILL = [
        'ARBO-88' => ['medium', 120, ['tech-debt']],
        'ARBO-89' => ['high', 480, ['security', 'backend']],
        'ARBO-90' => ['medium', 240, ['security', 'ui']],
        'ARBO-91' => ['medium', 360, ['backend']],
        'ARBO-92' => ['medium', 480, ['backend', 'frontend']],
        'ARBO-93' => ['medium', 360, ['backend', 'frontend']],
        'ARBO-94' => ['medium', 480, ['frontend', 'backend']],
        'ARBO-95' => ['medium', 240, ['backend', 'security']],
        'ARBO-96' => ['medium', 480, ['frontend']],
        'ARBO-97' => ['medium', 360, ['backend', 'frontend']],
        'ARBO-98' => ['medium', 360, ['frontend']],
        'ARBO-99' => ['low', 240, ['backend']],
        'ARBO-100' => ['medium', 960, ['frontend']],
        'BILLR-16' => ['high', 360, ['api', 'backend']],
        'BILLR-17' => ['high', 240, ['infra']],
        'BILLR-18' => ['high', 45, ['infra']],
        'BILLR-19' => ['medium', 240, []],
        'BILLR-20' => ['urgent', 240, []],
        'BILLR-21' => ['medium', 240, []],
        'CHRON-2' => ['high', 480, ['backend', 'infra']],
        'CHRON-3' => ['high', 480, ['security']],
        'CHRON-4' => ['high', 360, ['database', 'backend']],
        'CHRON-5' => ['high', 360, ['frontend', 'ui']],
        'CHRON-6' => ['high', 240, ['frontend', 'backend']],
        'CHRON-7' => ['medium', 360, ['frontend', 'ui']],
        'CHRON-8' => ['medium', 240, ['api', 'security']],
        'CHRON-9' => ['low', 120, ['frontend']],
        'CHRON-10' => ['high', 480, ['security', 'backend']],
        'CHRON-11' => ['high', 480, ['backend', 'api']],
        'CHRON-12' => ['high', 480, ['backend', 'api']],
        'CHRON-13' => ['medium', 120, ['backend']],
        'CHRON-14' => ['high', 120, ['backend']],
        'CHRON-15' => ['medium', 480, ['backend']],
        'CHRON-16' => ['medium', 240, []],
        'CHRON-17' => ['low', 120, []],
        'CMS-40' => ['low', 120, ['docs']],
        'CMS-41' => ['medium', 120, ['frontend']],
        'CMS-42' => ['high', 45, ['infra']],
        'CMS-43' => ['low', 45, ['tech-debt']],
        'CMS-65' => ['medium', 45, ['infra']],
        'CMS-66' => ['medium', 120, ['backend', 'docs']],
        'CMS-67' => ['medium', 120, ['backend']],
        'CMS-68' => ['low', 120, ['docs']],
        'CMS-69' => ['high', 120, ['security']],
        'CMS-70' => ['medium', 120, ['frontend', 'performance']],
        'CMS-71' => ['medium', 45, ['infra', 'security']],
        'CMS-72' => ['low', 120, ['frontend']],
        'CMS-73' => ['urgent', 120, ['backend']],
        'CMS-74' => ['low', 45, ['frontend']],
        'CMS-75' => ['medium', 45, ['docs']],
        'CMS-76' => ['low', 45, ['frontend']],
        'CMS-77' => ['urgent', 120, ['backend']],
        'CMS-78' => ['urgent', 120, ['backend']],
        'CMS-79' => ['high', 120, ['backend', 'performance']],
        'CMS-80' => ['medium', 240, []],
        'CMS-81' => ['medium', 120, []],
        'FIN-2' => ['medium', 240, ['tech-debt']],
        'FIN-3' => ['medium', 240, ['infra']],
        'FIN-4' => ['medium', 120, ['database', 'performance']],
        'FIN-5' => ['medium', 240, ['tech-debt', 'backend']],
        'FIN-6' => ['low', 120, ['docs', 'infra']],
        'FIN-7' => ['medium', 360, ['infra', 'tech-debt']],
        'HAB-14' => ['high', 960, ['frontend']],
        'HAB-15' => ['high', 360, ['frontend']],
        'HAB-16' => ['medium', 240, ['backend', 'tech-debt']],
        'HAB-22' => ['medium', 480, ['frontend', 'performance']],
        'HAB-23' => ['medium', 480, ['backend']],
        'HAB-24' => ['high', 120, ['frontend']],
        'HAB-25' => ['high', 240, ['backend']],
        'HAB-26' => ['medium', 120, ['tech-debt', 'database']],
        'HAB-27' => ['high', 240, ['backend']],
        'HAB-29' => ['medium', 960, ['backend']],
        'HAB-32' => ['medium', 480, ['backend']],
        'HAB-33' => ['medium', 960, ['backend']],
        'HAB-34' => ['medium', 480, ['backend']],
        'HAB-35' => ['low', 240, ['backend']],
        'HAB-36' => ['low', 240, ['frontend']],
        'HAB-37' => ['high', 240, ['infra']],
        'HAB-38' => ['high', 360, ['security']],
        'HAB-39' => ['high', 120, ['infra']],
        'HAB-40' => ['medium', 240, ['frontend', 'ui']],
        'HAB-41' => ['high', 240, ['infra']],
        'HAB-42' => ['medium', 45, ['infra', 'tech-debt']],
        'HAB-43' => ['urgent', 120, ['infra', 'database']],
        'HAB-44' => ['high', 120, ['backend']],
        'HAB-45' => ['low', 45, ['ui']],
        'HAB-46' => ['medium', 120, ['frontend']],
        'HAB-47' => ['medium', 120, ['frontend', 'ui']],
        'HAB-48' => ['medium', 240, ['frontend']],
        'HAB-49' => ['high', 120, ['infra']],
        'HAB-50' => ['medium', 360, ['frontend', 'tech-debt']],
        'HAB-51' => ['medium', 240, []],
        'ID-1' => ['high', 960, ['security']],
        'ID-2' => ['high', 960, ['security', 'backend']],
        'ID-3' => ['high', 480, ['backend']],
        'ID-4' => ['high', 360, ['infra']],
        'ID-5' => ['medium', 480, ['frontend']],
        'ID-6' => ['urgent', 120, ['infra']],
        'ID-7' => ['high', 45, ['infra']],
        'ID-8' => ['low', 240, ['frontend', 'ui']],
        'ID-9' => ['low', 240, ['backend']],
        'ID-10' => ['low', 240, ['frontend']],
        'ID-11' => ['medium', 45, []],
        'INFRA-1' => ['urgent', 120, ['security', 'infra']],
        'INFRA-3' => ['medium', 120, ['infra']],
        'INFRA-6' => ['urgent', 120, ['security', 'infra']],
        'INFRA-7' => ['high', 240, ['security', 'infra']],
        'INFRA-8' => ['high', 120, ['security', 'infra']],
        'INFRA-9' => ['low', 120, ['docs']],
        'INFRA-10' => ['low', 45, ['docs', 'infra']],
        'INFRA-11' => ['low', 45, ['docs']],
        'INFRA-12' => ['medium', 45, ['infra']],
        'INFRA-13' => ['low', 120, ['docs']],
        'INFRA-14' => ['medium', 120, ['docs', 'infra']],
        'INFRA-15' => ['high', 120, []],
        'OBW-1' => ['high', 960, ['frontend']],
        'OBW-2' => ['high', 360, ['frontend', 'ui']],
        'OBW-3' => ['high', 240, ['database']],
        'OBW-4' => ['high', 240, ['security']],
        'OBW-5' => ['high', 360, ['security']],
        'OBW-6' => ['medium', 360, ['backend', 'frontend']],
        'OBW-7' => ['high', 480, ['frontend', 'backend']],
        'OBW-8' => ['medium', 240, ['frontend']],
        'OBW-9' => ['medium', 240, ['frontend']],
        'OBW-10' => ['high', 240, ['security', 'backend']],
        'OBW-11' => ['high', 360, ['security', 'backend']],
        'OBW-12' => ['medium', 240, ['backend', 'database']],
        'OBW-13' => ['medium', 360, ['tech-debt', 'infra']],
        'OBW-14' => ['high', 240, ['infra']],
        'OBW-15' => ['medium', 120, ['infra']],
        'OBW-16' => ['low', 45, ['infra']],
        'OBW-17' => ['low', 120, ['docs']],
        'OBW-18' => ['low', 120, ['database']],
        'OBW-19' => ['medium', 120, ['frontend']],
        'OBW-20' => ['high', 480, ['security']],
        'OBW-21' => ['urgent', 120, ['security']],
        'OBW-22' => ['urgent', 45, ['security']],
        'OBW-23' => ['urgent', 45, ['security', 'infra']],
        'OBW-25' => ['high', 120, ['security']],
        'OBW-26' => ['medium', 45, ['security', 'infra']],
        'OBW-27' => ['medium', 45, ['security']],
        'OBW-28' => ['medium', 120, ['security']],
        'OBW-29' => ['medium', 120, ['security', 'backend']],
        'OBW-30' => ['high', 45, ['security', 'backend']],
        'OBW-31' => ['medium', 45, ['frontend']],
        'OBW-32' => ['medium', 360, ['security', 'frontend']],
        'OBW-33' => ['medium', 240, ['frontend']],
        'OBW-34' => ['high', 480, ['security']],
        'OBW-35' => ['high', 480, ['security']],
        'OBW-36' => ['high', 240, ['security']],
        'OBW-37' => ['medium', 360, ['security']],
        'OBW-38' => ['low', 120, ['frontend']],
        'OBW-39' => ['medium', 480, ['tech-debt']],
        'OBW-40' => ['high', 240, ['security', 'tech-debt']],
        'OBW-41' => ['medium', 120, ['backend', 'performance']],
        'OBW-42' => ['medium', 120, ['database', 'performance']],
        'OBW-43' => ['medium', 45, ['database', 'performance']],
        'OBW-44' => ['medium', 120, ['security']],
        'OBW-45' => ['low', 45, ['tech-debt']],
        'OBW-46' => ['low', 45, ['tech-debt', 'security']],
        'OBW-47' => ['low', 45, ['tech-debt']],
        'OBW-48' => ['urgent', 240, ['security']],
        'OBW-49' => ['low', 120, ['frontend']],
        'OBW-50' => ['low', 120, ['docs']],
        'OBW-51' => ['urgent', 120, ['security']],
        'OBW-52' => ['high', 45, ['infra']],
        'OBW-53' => ['low', 120, ['docs', 'frontend']],
        'OBW-54' => ['low', 120, ['frontend']],
        'OBW-55' => ['high', 360, ['security']],
        'OBW-56' => ['medium', 480, []],
        'OBW-57' => ['high', 120, []],
        'OBW-58' => ['medium', 240, []],
        'OBW-59' => ['medium', 120, []],
        'OBW-60' => ['medium', 120, []],
        'OBW-61' => ['low', 45, []],
        'OBW-62' => ['medium', 120, []],
        'OBW-63' => ['medium', 45, []],
        'OBW-64' => ['low', 120, []],
        'OBW-65' => ['low', 45, []],
        'OBW-66' => ['low', 120, []],
        'OBW-67' => ['low', 45, []],
        'OBW-68' => ['medium', 360, []],
        'SHOP-1' => ['high', 960, ['backend', 'infra']],
        'SHOP-2' => ['medium', 360, ['infra', 'tech-debt']],
        'SRV-1' => ['medium', 960, ['backend']],
        'SRV-2' => ['medium', 480, ['infra', 'backend']],
        'SRV-3' => ['medium', 480, ['infra', 'backend']],
        'SRV-4' => ['medium', 360, ['infra']],
        'SRV-5' => ['low', 240, ['infra', 'frontend']],
        'SRV-6' => ['medium', 240, ['infra', 'security']],
        'SRV-7' => ['low', 120, ['frontend']],
        'ST-67' => ['medium', 960, ['frontend']],
        'ST-84' => ['medium', 480, ['frontend', 'backend']],
        'ST-85' => ['low', 240, ['frontend']],
        'ST-86' => ['medium', 480, ['frontend']],
        'ST-88' => ['medium', 480, ['frontend']],
        'ST-89' => ['low', 360, ['backend']],
        'ST-90' => ['medium', 360, ['frontend']],
        'ST-91' => ['medium', 240, ['backend']],
        'ST-92' => ['medium', 960, ['backend']],
        'ST-93' => ['high', 480, ['backend', 'database']],
        'ST-94' => ['high', 360, ['security']],
        'ST-95' => ['high', 360, ['backend']],
        'ST-96' => ['high', 480, ['backend']],
        'ST-97' => ['high', 480, ['backend']],
        'ST-98' => ['medium', 240, ['frontend', 'ui']],
        'STAT-1' => ['high', 960, []],
        'STAT-2' => ['medium', 120, []],
        'STK-1' => ['medium', 960, ['backend']],
        'STK-2' => ['medium', 240, ['infra', 'tech-debt']],
        'TRACK-50' => ['low', 120, ['frontend', 'ui']],
        'TRACK-60' => ['medium', 120, ['frontend']],
        'TRACK-61' => ['low', 120, ['ui', 'frontend']],
        'TRACK-62' => ['high', 240, ['security']],
        'TRACK-63' => ['high', 45, ['infra']],
        'TRACK-64' => ['medium', 360, ['backend', 'database']],
        'TRACK-65' => ['low', 120, ['ui', 'frontend']],
        'TRACK-66' => ['low', 240, ['ui', 'frontend']],
        'TRACK-67' => ['low', 120, ['ui']],
        'TRACK-68' => ['low', 120, ['frontend']],
        'TRACK-69' => ['medium', 480, ['frontend']],
        'TRACK-70' => ['medium', 480, ['frontend', 'backend']],
        'TRACK-71' => ['low', 45, ['ui']],
        'TRACK-72' => ['low', 120, ['tech-debt']],
        'TRACK-73' => ['low', 120, ['tech-debt']],
        'TRACK-74' => ['medium', 240, ['frontend', 'tech-debt']],
        'TRACK-75' => ['low', 120, ['frontend']],
        'TRACK-76' => ['low', 120, ['frontend']],
        'TRACK-77' => ['high', 960, ['security', 'backend']],
        'TRACK-78' => ['high', 480, ['security', 'database']],
        'TRACK-79' => ['medium', 360, ['frontend']],
        'TRACK-80' => ['medium', 240, ['security']],
        'TRACK-81' => ['medium', 360, ['security', 'backend']],
        'TRACK-82' => ['high', 120, ['frontend']],
        'TRACK-83' => ['medium', 45, ['frontend']],
        'TRACK-85' => ['urgent', 120, ['frontend']],
        'TRACK-86' => ['medium', 240, ['backend', 'database']],
        'TRACK-87' => ['low', 120, ['frontend']],
        'TRACK-88' => ['low', 120, ['frontend', 'ui']],
        'TRACK-89' => ['medium', 960, ['backend']],
        'TRACK-90' => ['medium', 360, ['database', 'frontend']],
        'TRACK-91' => ['medium', 240, ['frontend']],
        'TRACK-92' => ['high', 240, ['security']],
        'TRACK-93' => ['low', 120, ['docs']],
        'TRACK-94' => ['high', 960, ['backend', 'security']],
        'TRACK-95' => ['high', 480, ['database', 'backend']],
        'TRACK-96' => ['high', 360, ['database', 'backend']],
        'TRACK-97' => ['high', 480, ['security', 'backend']],
        'TRACK-98' => ['high', 480, ['security', 'backend']],
        'TRACK-100' => ['low', 120, ['api', 'docs']],
        'TRACK-102' => ['low', 45, ['database']],
        'TRACK-103' => ['low', 120, ['frontend']],
        'TRACK-104' => ['medium', 120, ['security']],
        'TRACK-105' => ['high', 480, ['backend', 'frontend']],
        'TRACK-106' => ['low', 120, ['frontend', 'ui']],
        'TRACK-107' => ['medium', 360, ['backend', 'frontend']],
        'TRACK-108' => ['medium', 45, ['tech-debt']],
        'TRACK-109' => ['medium', 240, ['backend', 'frontend']],
        'TRACK-110' => ['medium', 240, ['frontend', 'ui']],
        'TRACK-111' => ['low', 240, ['frontend', 'ui']],
        'TRACK-112' => ['low', 120, ['frontend']],
        'TRACK-113' => ['medium', 360, ['backend', 'frontend']],
        'TRACK-114' => ['medium', 240, ['backend', 'api']],
        'TRACK-115' => ['low', 120, ['database']],
        'TRACK-116' => ['medium', 120, ['infra', 'api']],
        'TRACK-117' => ['medium', 360, ['ui', 'frontend']],
        'TRACK-118' => ['low', 120, ['ui']],
        'TRACK-119' => ['medium', 240, ['security']],
        'TRACK-120' => ['low', 120, ['frontend', 'security']],
        'TRACK-121' => ['medium', 480, ['backend', 'frontend']],
        'TRACK-122' => ['medium', 480, ['frontend']],
        'TRACK-123' => ['low', 45, ['ui']],
        'TRACK-124' => ['high', 45, ['security']],
        'TRACK-125' => ['medium', 480, ['ui', 'frontend']],
        'TRACK-126' => ['medium', 120, ['api']],
        'TRACK-127' => ['medium', 120, ['api']],
        'TRACK-128' => ['low', 120, ['docs', 'api']],
        'TRACK-130' => ['medium', 360, ['backend', 'api']],
        'TRACK-131' => ['medium', 240, ['api']],
        'TRACK-132' => ['low', 120, ['docs']],
        'TRACK-133' => ['low', 120, ['database']],
        'TRACK-134' => ['medium', 120, []],
        'TRACK-135' => ['low', 120, []],
        'TRACK-136' => ['low', 45, []],
        'TRACK-137' => ['medium', 360, []],
        'TRACK-138' => ['low', 45, []],
        'TRACK-139' => ['low', 120, []],
        'ZERO-20' => ['high', 480, ['backend', 'api']],
        'ZERO-23' => ['medium', 360, ['security']],
        'ZERO-31' => ['medium', 120, ['performance', 'backend']],
        'ZERO-35' => ['high', 240, ['backend']],
        'ZERO-36' => ['low', 120, ['infra', 'tech-debt']],
        'ZERO-37' => ['high', 45, ['backend']],
        'ZERO-38' => ['medium', 240, ['tech-debt']],
        'ZERO-39' => ['medium', 240, ['tech-debt']],
        'ZERO-42' => ['medium', 45, ['infra']],
        'ZERO-44' => ['medium', 45, ['frontend', 'security']],
        'ZERO-45' => ['low', 45, ['tech-debt']],
        'ZERO-46' => ['medium', 120, ['database']],
        'ZERO-47' => ['medium', 240, ['backend', 'api']],
        'ZERO-48' => ['medium', 240, []],
    ];

    /**
     * Guarded per field: a value is only written where the column is still
     * empty, so re-running is a no-op and anything since curated by hand
     * survives. The assignee is only set when the issue's project has exactly
     * one member, so adding teammates later cannot cause a wrong assignment.
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

            if ($labels === []) {
                continue;
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
                $labelIds->map(fn (mixed $labelId): array => [
                    'issue_id' => $issue->id,
                    'label_id' => Cast::int($labelId),
                ])->all()
            );
        }
    }

    /**
     * The pre-backfill state was "empty", but this cannot distinguish a value
     * this migration wrote from one set by hand afterwards, so it deliberately
     * does nothing rather than clearing curated data.
     */
    public function down(): void
    {
        //
    }
};
