<?php

use App\Actions\ReassignIssuesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One-off: these five issues covering tracker work were filed under THI
        // by mistake and belong under TRACK. Guard on the target project so fresh
        // and test databases (which have no TRACK project) are left untouched.
        if (! DB::table('projects')->where('key', 'TRACK')->exists()) {
            return;
        }

        app(ReassignIssuesAction::class)->handle([
            'THI-348' => 'TRACK',
            'THI-349' => 'TRACK',
            'THI-350' => 'TRACK',
            'THI-351' => 'TRACK',
            'THI-352' => 'TRACK',
        ]);
    }

    public function down(): void
    {
        // Irreversible: renumbering issues into TRACK cannot be cleanly undone.
    }
};
