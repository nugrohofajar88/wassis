<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drains the auto-reply debounce queue (App\Jobs\ProcessAutoReply). No persistent worker is
// available on production hosting, so this runs briefly every minute via cron `schedule:run`
// instead — see AGENTS.md ("Production deployment"). Uses Schedule::call() + Artisan::call()
// (in-process), NOT Schedule::command() — the latter spawns a subprocess via Symfony Process,
// which requires proc_open(), and that's disabled on production hosting alongside symlink()
// and exec(). withoutOverlapping guards against two runs stacking up if one ever takes longer
// than expected.
Schedule::call(function () {
    Artisan::call('queue:work', ['--stop-when-empty' => true]);
})->name('process-auto-reply-queue')->everyMinute()->withoutOverlapping(5);
