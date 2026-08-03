<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\ProjectType;
use App\Models\WorkflowState;
use App\Support\Cast;
use Illuminate\Support\Facades\DB;

class SaveProjectTypeAction
{
    /**
     * Create or update a project type and sync its lanes: existing lanes are
     * updated, new ones created, and removed ones deleted after their issues
     * are moved to the surviving default lane so nothing falls off the board.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, ?ProjectType $type = null): ProjectType
    {
        return DB::transaction(function () use ($organization, $data, $type): ProjectType {
            $type ??= new ProjectType;

            $description = Cast::string($data['description'] ?? '');

            $type->forceFill([
                'organization_id' => $organization->id,
                'name' => Cast::string($data['name'] ?? ''),
                'description' => $description === '' ? null : $description,
                'is_default' => $type->is_default ?? false,
            ])->save();

            $defaultChosen = false;
            $keptIds = [];

            foreach ($this->lanes($data) as $position => $lane) {
                $state = $lane['id'] > 0
                    ? ($type->states()->whereKey($lane['id'])->first() ?? new WorkflowState)
                    : new WorkflowState;

                $isDefault = $lane['isDefault'] && ! $defaultChosen;
                $defaultChosen = $defaultChosen || $isDefault;

                $state->forceFill([
                    'project_type_id' => $type->id,
                    'name' => $lane['name'],
                    'category' => $lane['category'],
                    'color' => $lane['color'],
                    'position' => $position,
                    'is_default' => $isDefault,
                ])->save();

                $keptIds[] = $state->id;
            }

            if (! $defaultChosen && $keptIds !== []) {
                WorkflowState::query()->whereKey($keptIds[0])->update(['is_default' => true]);
            }

            $this->removeDroppedLanes($type, $keptIds);

            return $type->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{id: int, name: string, category: string, color: string, isDefault: bool}>
     */
    private function lanes(array $data): array
    {
        $raw = $data['states'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $lanes = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $lanes[] = [
                'id' => Cast::int($entry['id'] ?? null),
                'name' => Cast::string($entry['name'] ?? ''),
                'category' => Cast::string($entry['category'] ?? ''),
                'color' => Cast::string($entry['color'] ?? ''),
                'isDefault' => (bool) ($entry['isDefault'] ?? false),
            ];
        }

        return $lanes;
    }

    /**
     * @param  list<int>  $keptIds
     */
    private function removeDroppedLanes(ProjectType $type, array $keptIds): void
    {
        $dropped = $type->states()->whereKeyNot($keptIds)->pluck('id');

        if ($dropped->isEmpty()) {
            return;
        }

        $fallback = Cast::int($keptIds[0] ?? null);
        $projectIds = $type->projects()->pluck('id');

        if ($fallback > 0 && $projectIds->isNotEmpty()) {
            DB::table('issues')
                ->whereIn('project_id', $projectIds->all())
                ->whereIn('workflow_state_id', $dropped->all())
                ->update(['workflow_state_id' => $fallback]);
        }

        $type->states()->whereIn('id', $dropped->all())->delete();
    }
}
