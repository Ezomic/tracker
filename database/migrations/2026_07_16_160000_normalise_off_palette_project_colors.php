<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Off-palette color => nearest selectable palette color, so every project's
     * color is choosable and circled in the settings picker.
     *
     * @var array<string, array{from: string, to: string}>
     */
    private array $recolors = [
        'FIN' => ['from' => '#2e8b57', 'to' => '#639922'],
        'ID' => ['from' => '#8a4fbe', 'to' => '#9b51e0'],
        'SHOP' => ['from' => '#b5652d', 'to' => '#a1663a'],
        'SRV' => ['from' => '#5a6fb0', 'to' => '#4f5bd5'],
        'STK' => ['from' => '#c0533b', 'to' => '#e2413f'],
    ];

    public function up(): void
    {
        foreach ($this->recolors as $key => $colors) {
            DB::table('projects')
                ->where('key', $key)
                ->where('color', $colors['from'])
                ->update(['color' => $colors['to']]);
        }
    }

    public function down(): void
    {
        foreach ($this->recolors as $key => $colors) {
            DB::table('projects')
                ->where('key', $key)
                ->where('color', $colors['to'])
                ->update(['color' => $colors['from']]);
        }
    }
};
