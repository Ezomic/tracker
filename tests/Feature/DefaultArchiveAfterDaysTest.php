<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Support\Facades\DB;

it('gives new projects a week before done issues auto-archive', function () {
    DB::table('projects')->insert(['key' => 'NEW', 'name' => 'New', 'color' => '#112233', 'created_at' => now(), 'updated_at' => now()]);

    expect(Project::query()->where('key', 'NEW')->firstOrFail()->archive_after_days)->toBe(7);
});
