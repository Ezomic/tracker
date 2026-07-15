<?php

use App\Actions\ReassignIssuesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One-off correction: TRACK-54 (originally THI-349) was swept into TRACK
        // by an over-broad re-file; it is portfolio CMS work and belongs under CMS.
        // Guard on the target project so fresh/test databases are a no-op.
        if (! DB::table('projects')->where('key', 'CMS')->exists()) {
            return;
        }

        app(ReassignIssuesAction::class)->handle([
            'TRACK-54' => 'CMS',
        ]);
    }

    public function down(): void
    {
        // Irreversible: renumbering the issue into CMS cannot be cleanly undone.
    }
};
