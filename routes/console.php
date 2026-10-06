<?php

use App\Jobs\SyncYouTubeChannelJob;
use App\Models\ChannelConnection;
use App\Services\Instagram\InstagramService;
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

// ── Instagram gallery ──────────────────────────────────────────────────────────
//
// The feed is metadata only (captions, permalinks, Meta CDN URLs) — no media
// is ever downloaded. Visitors never call Meta: these keep the cache warm.

Artisan::command('instagram:refresh {--force : Refresh even if the cached feed is still fresh}', function (InstagramService $instagram) {
    $this->info('Instagram feed: ' . $instagram->refresh((bool) $this->option('force')));
})->purpose('Refresh the cached Instagram feed');

Artisan::command('instagram:refresh-token {--force : Refresh even if done in the last 6 days}', function (InstagramService $instagram) {
    $this->info('Instagram token: ' . $instagram->refreshToken((bool) $this->option('force')));
})->purpose('Extend the Instagram access token by another 60 days');

Artisan::command('instagram:check', function (InstagramService $instagram) {
    $r = $instagram->check();
    $t = $r['token'];

    $this->line('Token:     ' . ($t['configured'] ? "configured ({$t['source']})" : 'NOT configured'));
    if ($t['expires_at']) {
        $this->line("           refreshed {$t['refreshed_at']}, expires {$t['expires_at']}");
    }

    if (! $r['ok']) {
        $this->error('Problem:   ' . $r['problem']);

        return 1;
    }

    $this->info("Account:   @{$r['account']}");
    $this->line("Posts:     Meta returned {$r['returned_raw']}, {$r['kept']} usable");
    foreach ($r['sample'] as $p) {
        $this->line(sprintf('  - %-15s %d item(s)  caption:%s  plays-on-site:%s  %s',
            $p['type'], $p['items'], $p['has_caption'] ? 'yes' : 'no', $p['playable'] ? 'yes' : 'no', $p['permalink']));
    }

    return 0;
})->purpose('Check the Instagram connection against the live API (never prints the token)');

// Cheap when nothing is due: refresh() returns without calling Meta until the
// cached copy is older than INSTAGRAM_CACHE_MINUTES.
Schedule::command('instagram:refresh')->everyFifteenMinutes()->withoutOverlapping();

// Meta only refreshes a token that is 24+ hours old, and one left for 60 days
// dies for good. Checked daily, refreshed weekly — many chances before expiry.
Schedule::command('instagram:refresh-token')->daily()->withoutOverlapping();
