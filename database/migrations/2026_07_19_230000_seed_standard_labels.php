<?php

declare(strict_types=1);

use App\Enums\LabelColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Area/domain labels, deliberately orthogonal to the issue templates
     * (which already cover intent: bug, feature, chore, spike).
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'backend' => LabelColor::Blue->value,
        'frontend' => LabelColor::Purple->value,
        'api' => LabelColor::Blue->value,
        'database' => LabelColor::Gray->value,
        'infra' => LabelColor::Gray->value,
        'ui' => LabelColor::Purple->value,
        'docs' => LabelColor::Green->value,
        'security' => LabelColor::Red->value,
        'performance' => LabelColor::Yellow->value,
        'tech-debt' => LabelColor::Yellow->value,
    ];

    /**
     * Ticket creation now expects labels, but organizations predating that
     * have none. Seed a starter set for any organization without labels, so
     * there is something valid to pass. Guarded on the organization having
     * zero labels, so it never overwrites a curated set and re-running is a
     * no-op. Fresh and test databases have no organizations at migration
     * time, so they no-op too.
     */
    public function up(): void
    {
        $now = now();

        foreach (DB::table('organizations')->get(['id']) as $organization) {
            $hasLabels = DB::table('labels')->where('organization_id', $organization->id)->exists();

            if ($hasLabels) {
                continue;
            }

            $rows = [];

            foreach (self::LABELS as $name => $color) {
                $rows[] = [
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'color' => $color,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('labels')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('labels')->whereIn('name', array_keys(self::LABELS))->delete();
    }
};
