<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drains the auto-reply debounce queue (App\Jobs\ProcessAutoReply). No persistent worker is
// available on production hosting, so this runs briefly every minute via cron `schedule:run`
// instead — see AGENTS.md ("Production deployment"). withoutOverlapping guards against two
// instances stacking up if one run ever takes longer than expected.
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping(5);
