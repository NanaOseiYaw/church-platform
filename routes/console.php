<?php

use App\Jobs\SyncYouTubeChannelJob;
use App\Models\ChannelConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Sermon auto-sync ───────────────────────────────────────────────────────────
//
// Every hour, dispatch a sync job for every active channel connection whose
// next_sync_at has passed (or was never set).  The job handles API calls,
// quota management, and retry back-off independently per connection.
//
// To run the scheduler locally:  php artisan schedule:run
// Production (cron):  * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
Schedule::call(function () {
    ChannelConnection::dueForSync()
        ->each(fn (ChannelConnection $c) => SyncYouTubeChannelJob::dispatch($c));
})->hourly()->name('sync-sermon-channels')->withoutOverlapping();
