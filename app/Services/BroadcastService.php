<?php

namespace App\Services;

use App\Jobs\SendBroadcastJob;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Models\BroadcastRecipient;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class BroadcastService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Resolve the full recipient list for a broadcast.
     * All queries filter is_active = true.
     *
     * @return Collection<User>
     */
    public function resolveAudience(Broadcast $broadcast): Collection
    {
        $churchId = $broadcast->church_id;

        return match ($broadcast->audience_type) {
            'all_members' => User::where('church_id', $churchId)
                ->where('is_active', true)
                ->get(),

            'role' => User::where('church_id', $churchId)
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', $broadcast->audience_config['role'] ?? ''))
                ->get(),

            'department' => User::where('church_id', $churchId)
                ->where('is_active', true)
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $broadcast->audience_config['department_id'] ?? 0))
                ->get(),

            'event_attendees' => User::where('church_id', $churchId)
                ->where('is_active', true)
                ->whereHas('rsvps', fn ($q) => $q->where('event_id', $broadcast->audience_config['event_id'] ?? 0))
                ->get(),

            'volunteers' => User::where('church_id', $churchId)
                ->where('is_active', true)
                ->whereHas('assignments', fn ($q) => $q->whereHas('servicePlan', fn ($q2) =>
                    $q2->where('church_id', $churchId)
                ))
                ->get(),

            'saved_audience' => $this->resolveFromSaved(
                $broadcast->audience_config['audience_id'] ?? 0,
                $churchId,
            ),

            default => collect(),
        };
    }

    /**
     * Immediately send a broadcast (status must be draft or failed).
     * Deletes any existing recipient rows, inserts fresh pending rows,
     * sets status to "sending", then dispatches the job.
     */
    public function send(Broadcast $broadcast): void
    {
        if (! in_array($broadcast->status, ['draft', 'failed'])) {
            throw new RuntimeException("Broadcast #{$broadcast->id} cannot be sent from status '{$broadcast->status}'.");
        }

        $audience = $this->resolveAudience($broadcast);

        if ($audience->isEmpty()) {
            throw new RuntimeException('No active recipients found for the selected audience.');
        }

        // Wipe any stale recipient rows (handles retries of failed broadcasts)
        $broadcast->recipients()->delete();

        // Insert fresh pending rows
        $now  = now();
        $rows = $audience->map(fn (User $u) => [
            'broadcast_id' => $broadcast->id,
            'user_id'      => $u->id,
            'channel'      => 'in_app',
            'status'       => 'pending',
            'created_at'   => $now,
            'updated_at'   => $now,
        ])->toArray();

        BroadcastRecipient::insert($rows);

        $broadcast->update([
            'status'          => 'sending',
            'recipient_count' => count($rows),
            'delivered_count' => 0,
            'failed_count'    => 0,
        ]);

        SendBroadcastJob::dispatch($broadcast->id);
    }

    /**
     * Schedule a broadcast for future delivery (status must be draft).
     * Creates pending recipient rows now; job is delayed until scheduled_at.
     */
    public function schedule(Broadcast $broadcast): void
    {
        if ($broadcast->status !== 'draft') {
            throw new RuntimeException("Broadcast #{$broadcast->id} cannot be scheduled from status '{$broadcast->status}'.");
        }

        if (! $broadcast->scheduled_at || $broadcast->scheduled_at->isPast()) {
            throw new RuntimeException('scheduled_at must be a future timestamp.');
        }

        $audience = $this->resolveAudience($broadcast);

        if ($audience->isEmpty()) {
            throw new RuntimeException('No active recipients found for the selected audience.');
        }

        $broadcast->recipients()->delete();

        $now  = now();
        $rows = $audience->map(fn (User $u) => [
            'broadcast_id' => $broadcast->id,
            'user_id'      => $u->id,
            'channel'      => 'in_app',
            'status'       => 'pending',
            'created_at'   => $now,
            'updated_at'   => $now,
        ])->toArray();

        BroadcastRecipient::insert($rows);

        $broadcast->update([
            'status'          => 'scheduled',
            'recipient_count' => count($rows),
        ]);

        SendBroadcastJob::dispatch($broadcast->id)->delay($broadcast->scheduled_at);
    }

    /**
     * Replace {{member_name}}, {{church_name}}, {{department_name}} in a body string.
     */
    public function renderBody(string $body, User $user, Broadcast $broadcast): string
    {
        $church  = $broadcast->relationLoaded('church') ? $broadcast->church : $broadcast->church()->first();
        $deptName = $this->resolveDeptName($broadcast, $church);

        return str_replace(
            ['{{member_name}}', '{{church_name}}', '{{department_name}}'],
            [$user->name,       $church?->name ?? '',  $deptName],
            $body,
        );
    }

    /**
     * Alias for renderBody — used by preview endpoints.
     */
    public function previewBody(string $body, User $user, Broadcast $broadcast): string
    {
        return $this->renderBody($body, $user, $broadcast);
    }

    /**
     * Create and immediately send a broadcast on behalf of an Announcement.
     * audience = department if announcement has a department_id, else all_members.
     */
    public function broadcastAnnouncement(Announcement $announcement, User $actor): Broadcast
    {
        $audienceType   = $announcement->department_id ? 'department' : 'all_members';
        $audienceConfig = $announcement->department_id
            ? ['department_id' => $announcement->department_id]
            : null;

        $broadcast = Broadcast::create([
            'church_id'       => $announcement->church_id,
            'created_by'      => $actor->id,
            'title'           => $announcement->title,
            'subject'         => $announcement->title,
            'body'            => strip_tags($announcement->body),
            'status'          => 'draft',
            'audience_type'   => $audienceType,
            'audience_config' => $audienceConfig,
            'announcement_id' => $announcement->id,
        ]);

        try {
            $this->send($broadcast);
        } catch (\RuntimeException $e) {
            // Non-fatal: broadcast failed (e.g. empty audience), but the announcement save continues
            \Illuminate\Support\Facades\Log::warning('Broadcast announcement failed: ' . $e->getMessage(), [
                'announcement_id' => $announcement->id,
                'broadcast_id'    => $broadcast->id,
            ]);
        }

        return $broadcast;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function resolveFromSaved(int $audienceId, int $churchId): Collection
    {
        $saved = BroadcastAudience::withoutGlobalScope('church')
            ->where('id', $audienceId)
            ->where('church_id', $churchId)
            ->first();

        if (! $saved) {
            return collect();
        }

        // Create a proxy Broadcast so we can re-use resolveAudience()
        $proxy               = new Broadcast();
        $proxy->church_id    = $churchId;
        $proxy->audience_type   = $saved->audience_type;
        $proxy->audience_config = $saved->audience_config;

        return $this->resolveAudience($proxy);
    }

    private function resolveDeptName(Broadcast $broadcast, $church): string
    {
        if ($broadcast->audience_type === 'department' && isset($broadcast->audience_config['department_id'])) {
            $dept = \App\Models\Department::find($broadcast->audience_config['department_id']);
            if ($dept) {
                return $dept->name;
            }
        }

        return '';
    }
}
