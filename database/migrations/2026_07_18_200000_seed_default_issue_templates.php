<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Guarded data seed: only runs where the founder exists (prod), no-ops on
     * fresh/test databases. Idempotent by template name within the org.
     */
    public function up(): void
    {
        $founder = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($founder === null) {
            return;
        }

        $organizationId = DB::table('organization_user')
            ->where('user_id', $founder->id)
            ->where('role', 'owner')
            ->orderBy('organization_id')
            ->value('organization_id');

        if ($organizationId === null) {
            return;
        }

        foreach ($this->templates() as $template) {
            $exists = DB::table('issue_templates')
                ->where('organization_id', $organizationId)
                ->where('name', $template['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('issue_templates')->insert([
                'organization_id' => $organizationId,
                'name' => $template['name'],
                'description' => $template['description'],
                'type' => $template['type'],
                'priority' => $template['priority'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $founder = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($founder === null) {
            return;
        }

        $organizationId = DB::table('organization_user')
            ->where('user_id', $founder->id)
            ->where('role', 'owner')
            ->orderBy('organization_id')
            ->value('organization_id');

        if ($organizationId === null) {
            return;
        }

        DB::table('issue_templates')
            ->where('organization_id', $organizationId)
            ->whereIn('name', array_column($this->templates(), 'name'))
            ->delete();
    }

    /**
     * @return list<array{name: string, description: string, type: string, priority: string|null}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Bug report',
                'type' => 'fix',
                'priority' => 'high',
                'description' => "## Steps to reproduce\n1. \n\n## Expected\n\n## Actual\n\n## Environment\n",
            ],
            [
                'name' => 'Feature request',
                'type' => 'feature',
                'priority' => null,
                'description' => "## Problem\n\n## Proposed solution\n\n## Acceptance criteria\n- [ ] \n",
            ],
            [
                'name' => 'Chore',
                'type' => 'feature',
                'priority' => null,
                'description' => "## What\n\n## Why\n",
            ],
            [
                'name' => 'Spike',
                'type' => 'feature',
                'priority' => null,
                'description' => "## Question\n\n## Timebox\n\n## Findings\n",
            ],
        ];
    }
};
