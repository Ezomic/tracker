<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backup = Schedule::command('backup:database')->daily();
$archive = Schedule::command('issues:archive-done')->hourly();
$recurring = Schedule::command('issues:spawn-recurring')->hourly();

if (($adminEmail = config('tracker.admin_email')) !== null) {
    $backup->emailOutputOnFailure($adminEmail);
    $archive->emailOutputOnFailure($adminEmail);
    $recurring->emailOutputOnFailure($adminEmail);
}
