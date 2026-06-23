<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Sermons\ConnectChannelRequest;
use App\Http\Resources\ChannelConnectionResource;
use App\Jobs\SyncYouTubeChannelJob;
use App\Models\ChannelConnection;
use App\Services\Sermons\Exceptions\ProviderException;
use App\Services\Sermons\SermonSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages the connection between a church and external video providers.
 *
 * Routes:
 *   GET    /dashboard/sermons/channel        → index (part of Channel page data)
 *   POST   /dashboard/sermons/channel        → connect a new channel
 *   POST   /dashboard/sermons/channel/{c}/sync  → trigger manual sync
 *   DELETE /dashboard/sermons/channel/{c}    → disconnect
 */
class SermonChannelController extends Controller
{
    use LogsAuditEvents;

    public function __construct(
        private readonly SermonSyncService $sync,
    ) {}

    /**
     * POST /dashboard/sermons/channel
     *
     * Validate the channel identifier against the YouTube API, persist the
     * connection, and dispatch an initial sync job.
     */
    public function store(ConnectChannelRequest $request): RedirectResponse
    {
        $churchId = $this->resolvedChurchId();

        try {
            $connection = $this->sync->connectChannel(
                churchId:           $churchId,
                provider:           $request->provider,
                channelInput:       $request->channel_input,
                syncFrequencyHours: (int) ($request->sync_frequency_hours ?? 24),
                customApiKey:       $request->custom_api_key ?: null,
            );

            $this->auditLog('sermon.channel.created', $connection);

            // Kick off the first sync; pass auth user so job can notify on completion
            SyncYouTubeChannelJob::dispatch($connection, $request->user()->id);

        } catch (ProviderException $e) {
            return back()->withErrors(['channel_input' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "✓ {$connection->channel_title} connected. Sync in progress.");
    }

    /**
     * POST /dashboard/sermons/channel/{connection}/sync
     *
     * Trigger an on-demand sync for the given channel.
     */
    public function sync(Request $request, ChannelConnection $connection): RedirectResponse
    {
        abort_unless(
            $request->user()->can('sermons.manage_channels') &&
            $connection->church_id === $this->resolvedChurchId(),
            403,
        );

        SyncYouTubeChannelJob::dispatch($connection, $request->user()->id);

        return back()->with('success', "Syncing {$connection->channel_title} — you'll get a notification when it finishes.");
    }

    /**
     * DELETE /dashboard/sermons/channel/{connection}
     *
     * Disconnect (deactivate) a channel. Sermons synced from it are preserved
     * but the channel is marked inactive and will no longer sync.
     */
    public function destroy(Request $request, ChannelConnection $connection): RedirectResponse
    {
        abort_unless(
            $request->user()->can('sermons.manage_channels') &&
            $connection->church_id === $this->resolvedChurchId(),
            403,
        );

        $this->auditLog('sermon.channel.deleted', $connection);
        $connection->update(['is_active' => false]);

        return back()->with('success', "{$connection->channel_title} disconnected.");
    }
}
