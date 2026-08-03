<?php

declare(strict_types=1);

use App\Enums\StatusCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reproduce today's fixed statuses as a default "Engineering" project type
     * per organization, point every existing project at it, and backfill each
     * issue's workflow_state_id from its status. issues.status stays
     * authoritative for now; this only lights up the new columns.
     *
     * Lanes, in order: [name, category, colour].
     *
     * @var list<array{0: string, 1: StatusCategory, 2: string}>
     */
    private const LANES = [
        ['Backlog', StatusCategory::Backlog, '#9ca3af'],
        ['In Progress', StatusCategory::Started, '#d85a30'],
        ['In Review', StatusCategory::Started, '#378add'],
        ['Done', StatusCategory::Completed, '#1d9e75'],
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_TO_LANE = [
        'backlog' => 'Backlog',
        'in_progress' => 'In Progress',
        'in_review' => 'In Review',
        'done' => 'Done',
    ];

    public function up(): void
    {
        // No-op on a fresh or fully-migrated database (nothing to seed against,
        // or already assigned): guards test DBs and re-runs.
        if (DB::table('projects')->whereNull('project_type_id')->doesntExist()) {
            return;
        }

        $organizationIds = DB::table('projects')
            ->whereNull('project_type_id')
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $typeId = DB::table('project_types')->insertGetId([
                'organization_id' => $organizationId,
                'name' => 'Engineering',
                'description' => 'Backlog, In Progress, In Review, Done.',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $laneIds = [];

            foreach (self::LANES as $position => [$name, $category, $color]) {
                $laneIds[$name] = DB::table('workflow_states')->insertGetId([
                    'project_type_id' => $typeId,
                    'name' => $name,
                    'category' => $category->value,
                    'color' => $color,
                    'position' => $position,
                    'is_default' => $position === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $projectIds = DB::table('projects')
                ->when(
                    $organizationId === null,
                    fn ($query) => $query->whereNull('organization_id'),
                    fn ($query) => $query->where('organization_id', $organizationId),
                )
                ->pluck('id');

            DB::table('projects')->whereIn('id', $projectIds)->update(['project_type_id' => $typeId]);

            foreach (self::STATUS_TO_LANE as $status => $lane) {
                $laneId = $laneIds[$lane] ?? null;

                if ($laneId === null) {
                    continue;
                }

                DB::table('issues')
                    ->whereIn('project_id', $projectIds)
                    ->where('status', $status)
                    ->update(['workflow_state_id' => $laneId]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible data seed: the schema migration's down() drops the tables.
    }
};
