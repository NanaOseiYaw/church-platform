# Communication Center Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Add a Communication Center module â€” Broadcasts, Templates, Saved Audiences, Delivery Logs, and Analytics â€” that lets church admins and coordinators send targeted in-app messages to any segment of their congregation.

**Architecture:** Four new DB tables (`broadcast_templates`, `broadcast_audiences`, `broadcasts`, `broadcast_recipients`) back four Eloquent models. A `BroadcastService` handles audience resolution, variable rendering, and dispatch; a `SendBroadcastJob` (queued, 3 tries) iterates recipients and fires `AppNotification` per user. Four controllers expose CRUD + send/schedule/preview endpoints. Six Vue pages render the split-panel composer, dashboard stats, and management tables.

**Tech Stack:** Laravel 11, Inertia.js (Vue 3 + TypeScript), Tailwind CSS, Spatie Permissions, Laravel Queues, `lucide-vue-next`

---

## File Map

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_04_000001_create_broadcast_templates_table.php` |
| Create | `database/migrations/2026_06_04_000002_create_broadcast_audiences_table.php` |
| Create | `database/migrations/2026_06_04_000003_create_broadcasts_table.php` |
| Create | `database/migrations/2026_06_04_000004_create_broadcast_recipients_table.php` |
| Create | `app/Models/Broadcast.php` |
| Create | `app/Models/BroadcastRecipient.php` |
| Create | `app/Models/BroadcastTemplate.php` |
| Create | `app/Models/BroadcastAudience.php` |
| Modify | `resources/js/types/index.ts` |
| Modify | `config/permissions.php` |
| Modify | `app/Notifications/AppNotification.php` |
| Modify | `resources/js/Components/Dashboard/NotificationDropdown.vue` |
| Create | `app/Services/BroadcastService.php` |
| Create | `app/Jobs/SendBroadcastJob.php` |
| Create | `app/Http/Controllers/Dashboard/CommunicationController.php` |
| Create | `app/Http/Controllers/Dashboard/BroadcastController.php` |
| Create | `app/Http/Controllers/Dashboard/BroadcastTemplateController.php` |
| Create | `app/Http/Controllers/Dashboard/BroadcastAudienceController.php` |
| Modify | `routes/web.php` |
| Modify | `resources/js/Layouts/DashboardLayout.vue` |
| Create | `tests/Feature/BroadcastTest.php` |
| Create | `resources/js/Pages/Dashboard/Communication/Dashboard.vue` |
| Create | `resources/js/Pages/Dashboard/Communication/Broadcasts/Index.vue` |
| Create | `resources/js/Pages/Dashboard/Communication/Broadcasts/Create.vue` |
| Create | `resources/js/Pages/Dashboard/Communication/Broadcasts/Show.vue` |
| Create | `resources/js/Pages/Dashboard/Communication/Templates/Index.vue` |
| Create | `resources/js/Pages/Dashboard/Communication/Audiences/Index.vue` |
| Modify | `app/Http/Controllers/Dashboard/AnnouncementsController.php` |

---

### Task 1: Migration â€” broadcast_templates

**Files:**
- Create: `database/migrations/2026_06_04_000001_create_broadcast_templates_table.php`

- [x] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->string('category')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_templates');
    }
};
```

- [x] **Step 2: Run the migration**

```bash
php artisan migrate --path=database/migrations/2026_06_04_000001_create_broadcast_templates_table.php
```

Expected: `Migrating: 2026_06_04_000001_create_broadcast_templates_table` then `Migrated`.

- [x] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_000001_create_broadcast_templates_table.php
git commit -m "feat(comms): migration â€” broadcast_templates table"
```

---

### Task 2: Migration â€” broadcast_audiences

**Files:**
- Create: `database/migrations/2026_06_04_000002_create_broadcast_audiences_table.php`

- [x] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_audiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('audience_type', [
                'all_members', 'role', 'department', 'event_attendees', 'volunteers',
            ]);
            $table->json('audience_config')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_audiences');
    }
};
```

- [x] **Step 2: Run the migration**

```bash
php artisan migrate --path=database/migrations/2026_06_04_000002_create_broadcast_audiences_table.php
```

Expected: `Migrated`.

- [x] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_000002_create_broadcast_audiences_table.php
git commit -m "feat(comms): migration â€” broadcast_audiences table"
```

---

### Task 3: Migration â€” broadcasts

**Files:**
- Create: `database/migrations/2026_06_04_000003_create_broadcasts_table.php`

- [x] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title');
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
            $table->enum('audience_type', [
                'all_members', 'role', 'department', 'event_attendees', 'volunteers', 'saved_audience',
            ]);
            $table->json('audience_config')->nullable();
            $table->unsignedBigInteger('announcement_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('broadcast_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
```

- [x] **Step 2: Run the migration**

```bash
php artisan migrate --path=database/migrations/2026_06_04_000003_create_broadcasts_table.php
```

Expected: `Migrated`.

- [x] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_000003_create_broadcasts_table.php
git commit -m "feat(comms): migration â€” broadcasts table"
```

---

### Task 4: Migration â€” broadcast_recipients

**Files:**
- Create: `database/migrations/2026_06_04_000004_create_broadcast_recipients_table.php`

- [x] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('user_id');
            $table->string('channel')->default('in_app');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['broadcast_id', 'user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
    }
};
```

- [x] **Step 2: Run the migration**

```bash
php artisan migrate --path=database/migrations/2026_06_04_000004_create_broadcast_recipients_table.php
```

Expected: `Migrated`.

- [x] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_000004_create_broadcast_recipients_table.php
git commit -m "feat(comms): migration â€” broadcast_recipients table"
```

---

### Task 5: Models â€” Broadcast + BroadcastRecipient

**Files:**
- Create: `app/Models/Broadcast.php`
- Create: `app/Models/BroadcastRecipient.php`

- [x] **Step 1: Create Broadcast model**

```php
<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Broadcast extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'title', 'subject', 'body',
        'status', 'audience_type', 'audience_config',
        'announcement_id', 'template_id',
        'scheduled_at', 'sent_at',
        'recipient_count', 'delivered_count', 'failed_count',
    ];

    protected $casts = [
        'audience_config' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];

    // â”€â”€ Relationships â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BroadcastTemplate::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    // â”€â”€ Scopes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function scopeDraft($query)      { return $query->where('status', 'draft'); }
    public function scopeScheduled($query)  { return $query->where('status', 'scheduled'); }
    public function scopeSent($query)       { return $query->where('status', 'sent'); }
    public function scopeFailed($query)     { return $query->where('status', 'failed'); }

    // â”€â”€ Accessors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'scheduled' => 'Scheduled',
            'sending'   => 'Sending',
            'sent'      => 'Sent',
            'failed'    => 'Failed',
            default     => ucfirst($this->status),
        };
    }
}
```

- [x] **Step 2: Create BroadcastRecipient model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastRecipient extends Model
{
    protected $fillable = [
        'broadcast_id', 'user_id', 'channel',
        'status', 'sent_at', 'failed_at', 'failure_reason',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [x] **Step 3: Verify models load**

```bash
php artisan tinker --execute="echo App\Models\Broadcast::count();"
```

Expected: `0`

- [x] **Step 4: Commit**

```bash
git add app/Models/Broadcast.php app/Models/BroadcastRecipient.php
git commit -m "feat(comms): Broadcast + BroadcastRecipient models"
```

---

### Task 6: Models â€” BroadcastTemplate + BroadcastAudience + TypeScript types

**Files:**
- Create: `app/Models/BroadcastTemplate.php`
- Create: `app/Models/BroadcastAudience.php`
- Modify: `resources/js/types/index.ts`

- [x] **Step 1: Create BroadcastTemplate model**

```php
<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastTemplate extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'subject', 'body', 'category', 'usage_count',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class, 'template_id');
    }
}
```

- [x] **Step 2: Create BroadcastAudience model**

```php
<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastAudience extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'description',
        'audience_type', 'audience_config', 'member_count',
    ];

    protected $casts = [
        'audience_config' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [x] **Step 3: Append TypeScript types to `resources/js/types/index.ts`**

At the end of the file, add:

```typescript
// â”€â”€ Communication Center â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export type BroadcastStatus       = 'draft' | 'scheduled' | 'sending' | 'sent' | 'failed'
export type BroadcastAudienceType = 'all_members' | 'role' | 'department' | 'event_attendees' | 'volunteers' | 'saved_audience'

export interface Broadcast {
    id: number
    church_id: number
    created_by: number
    title: string
    subject: string
    body: string
    status: BroadcastStatus
    status_label: string
    audience_type: BroadcastAudienceType
    audience_config: Record<string, any> | null
    announcement_id: number | null
    template_id: number | null
    scheduled_at: string | null
    sent_at: string | null
    recipient_count: number
    delivered_count: number
    failed_count: number
    creator?: { id: number; name: string; avatar: string | null }
    announcement?: { id: number; title: string } | null
    created_at: string
    updated_at: string
}

export interface BroadcastRecipient {
    id: number
    broadcast_id: number
    user_id: number
    channel: string
    status: 'pending' | 'sent' | 'failed'
    sent_at: string | null
    failed_at: string | null
    failure_reason: string | null
    user?: { id: number; name: string; avatar: string | null }
}

export interface BroadcastTemplate {
    id: number
    church_id: number
    name: string
    subject: string
    body: string
    category: string | null
    usage_count: number
    created_at: string
}

export interface BroadcastAudience {
    id: number
    church_id: number
    name: string
    description: string | null
    audience_type: 'all_members' | 'role' | 'department' | 'event_attendees' | 'volunteers'
    audience_config: Record<string, any> | null
    member_count: number
    created_at: string
}
```

- [x] **Step 4: Commit**

```bash
git add app/Models/BroadcastTemplate.php app/Models/BroadcastAudience.php resources/js/types/index.ts
git commit -m "feat(comms): BroadcastTemplate + BroadcastAudience models + TS types"
```

---

### Task 7: Permissions + AppNotification type + notification icon

**Files:**
- Modify: `config/permissions.php`
- Modify: `app/Notifications/AppNotification.php`
- Modify: `resources/js/Components/Dashboard/NotificationDropdown.vue`

- [x] **Step 1: Add communication group to `config/permissions.php`**

In the `'permissions'` array, after the `'scheduling'` group (around line 106), add:

```php
        'communication' => [
            'communication.view',      // See Communication Center
            'communication.send',      // Send to any audience
            'communication.send_dept', // Send to own department only
            'communication.manage',    // Templates + saved audiences
            'communication.delete',    // Delete broadcasts
        ],
```

- [x] **Step 2: Add communication permissions to roles in `config/permissions.php`**

In `'roles'` â†’ `'church_admin'`, add to the list:
```
'communication.view', 'communication.send', 'communication.send_dept', 'communication.manage', 'communication.delete',
```

In `'roles'` â†’ `'coordinator'`, add:
```
'communication.view', 'communication.send_dept', 'communication.manage',
```

In `'roles'` â†’ `'assistant_coordinator'`, add:
```
'communication.view',
```

`'member'` gets no communication permissions.

- [x] **Step 3: Re-run the seeder**

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Expected: no errors, no output (or "Seeding complete").

- [x] **Step 4: Add TYPE_BROADCAST constant to `app/Notifications/AppNotification.php`**

After the last `TYPE_SERMON_PUBLISHED` constant line, add:

```php
    public const TYPE_BROADCAST = 'broadcast';
```

- [x] **Step 5: Add broadcast icon to `NotificationDropdown.vue`**

In the lucide imports line, add `Radio` to the existing import:

```typescript
import {
    Bell, CheckSquare, Megaphone, CalendarDays, Building2,
    CheckCheck, X, ExternalLink, Loader2, CalendarCheck2, Mic2, AlertCircle, Radio,
} from 'lucide-vue-next'
```

In the `TYPE_ICONS` object, add after the last `'sermon.published'` entry:

```typescript
    'broadcast': { icon: Radio, bg: 'bg-indigo-100', text: 'text-indigo-600' },
```

- [x] **Step 6: Commit**

```bash
git add config/permissions.php app/Notifications/AppNotification.php resources/js/Components/Dashboard/NotificationDropdown.vue
git commit -m "feat(comms): communication permissions + broadcast notification type + icon"
```

---

### Task 8: BroadcastService

**Files:**
- Create: `app/Services/BroadcastService.php`

- [x] **Step 1: Create the service**

```php
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
    // â”€â”€ Public API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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
     * Alias for renderBody â€” used by preview endpoints.
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

        $this->send($broadcast);

        return $broadcast;
    }

    // â”€â”€ Private helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

        return $church?->name ?? '';
    }
}
```

- [x] **Step 2: Verify class loads**

```bash
php artisan tinker --execute="echo class_exists(App\Services\BroadcastService::class) ? 'ok' : 'fail';"
```

Expected: `ok`

- [x] **Step 3: Commit**

```bash
git add app/Services/BroadcastService.php
git commit -m "feat(comms): BroadcastService â€” audience resolution, send, schedule, variables"
```

---

### Task 9: SendBroadcastJob

**Files:**
- Create: `app/Jobs/SendBroadcastJob.php`

- [x] **Step 1: Create the job**

```php
<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Notifications\AppNotification;
use App\Services\BroadcastService;
use App\Traits\LogsAuditEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LogsAuditEvents;

    public int $tries        = 3;
    public int $maxExceptions = 1;

    public function __construct(public int $broadcastId) {}

    public function handle(BroadcastService $service): void
    {
        $broadcast = Broadcast::with('church')->find($this->broadcastId);

        if (! $broadcast) {
            return;
        }

        // Guard: only process broadcasts in sending or scheduled state
        if (! in_array($broadcast->status, ['sending', 'scheduled'])) {
            return;
        }

        // Transition scheduled â†’ sending
        if ($broadcast->status === 'scheduled') {
            $broadcast->update(['status' => 'sending']);
        }

        $delivered = 0;
        $failed    = 0;

        $broadcast->recipients()
            ->where('status', 'pending')
            ->with('user')
            ->chunkById(50, function ($recipients) use ($service, $broadcast, &$delivered, &$failed) {
                foreach ($recipients as $recipient) {
                    /** @var BroadcastRecipient $recipient */
                    $user = $recipient->user;

                    if (! $user) {
                        $recipient->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'User not found']);
                        $failed++;
                        continue;
                    }

                    try {
                        $rendered = $service->renderBody($broadcast->body, $user, $broadcast);

                        $user->notify(new AppNotification(
                            AppNotification::TYPE_BROADCAST,
                            $broadcast->subject,
                            $rendered,
                            '/dashboard/communication/broadcasts/' . $broadcast->id,
                        ));

                        $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                        $delivered++;
                    } catch (\Throwable $e) {
                        $recipient->update([
                            'status'         => 'failed',
                            'failed_at'      => now(),
                            'failure_reason' => substr($e->getMessage(), 0, 250),
                        ]);
                        $failed++;
                    }
                }
            });

        $total  = $broadcast->recipient_count;
        $status = ($failed > 0 && $delivered === 0) ? 'failed'
                : (($failed > 0) ? 'sent' : 'sent');

        $broadcast->update([
            'delivered_count' => $delivered,
            'failed_count'    => $failed,
            'status'          => $status,
            'sent_at'         => now(),
        ]);

        $this->auditLog('communication.broadcast_sent', $broadcast, [], [], [
            'recipient_count' => $total,
            'delivered_count' => $delivered,
            'failed_count'    => $failed,
        ]);
    }
}
```

- [x] **Step 2: Verify class loads**

```bash
php artisan tinker --execute="echo class_exists(App\Jobs\SendBroadcastJob::class) ? 'ok' : 'fail';"
```

Expected: `ok`

- [x] **Step 3: Commit**

```bash
git add app/Jobs/SendBroadcastJob.php
git commit -m "feat(comms): SendBroadcastJob â€” queued delivery via AppNotification"
```

---

### Task 10: CommunicationController (dashboard stats)

**Files:**
- Create: `app/Http/Controllers/Dashboard/CommunicationController.php`

- [x] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    use ResolvesChurchData;

    /** GET /dashboard/communication */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $churchId = $this->resolvedChurchId();
        $now      = now();

        $sentThisMonth = Broadcast::where('church_id', $churchId)
            ->where('status', 'sent')
            ->whereMonth('sent_at', $now->month)
            ->whereYear('sent_at', $now->year)
            ->count();

        // Delivery rate across all sent broadcasts this month
        $monthBroadcasts = Broadcast::where('church_id', $churchId)
            ->where('status', 'sent')
            ->whereMonth('sent_at', $now->month)
            ->whereYear('sent_at', $now->year)
            ->selectRaw('SUM(recipient_count) as total_r, SUM(delivered_count) as total_d')
            ->first();

        $deliveryRate = ($monthBroadcasts->total_r > 0)
            ? round(($monthBroadcasts->total_d / $monthBroadcasts->total_r) * 100)
            : 0;

        $scheduledCount = Broadcast::where('church_id', $churchId)
            ->where('status', 'scheduled')
            ->count();

        $upcoming = Broadcast::where('church_id', $churchId)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get(['id', 'title', 'audience_type', 'scheduled_at', 'recipient_count']);

        $recent = Broadcast::where('church_id', $churchId)
            ->whereIn('status', ['sent', 'failed'])
            ->with('creator:id,name,avatar')
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get(['id', 'title', 'status', 'audience_type', 'recipient_count', 'delivered_count', 'failed_count', 'sent_at', 'created_by']);

        return Inertia::render('Dashboard/Communication/Dashboard', [
            'stats' => [
                'sent_this_month' => $sentThisMonth,
                'delivery_rate'   => $deliveryRate,
                'scheduled_count' => $scheduledCount,
            ],
            'upcoming' => $upcoming,
            'recent'   => $recent,
            'canSend'  => $request->user()->can('communication.send') || $request->user()->can('communication.send_dept'),
        ]);
    }
}
```

- [x] **Step 2: Commit**

```bash
git add app/Http/Controllers/Dashboard/CommunicationController.php
git commit -m "feat(comms): CommunicationController â€” dashboard stats"
```

---

### Task 11: BroadcastTemplateController + BroadcastAudienceController

**Files:**
- Create: `app/Http/Controllers/Dashboard/BroadcastTemplateController.php`
- Create: `app/Http/Controllers/Dashboard/BroadcastAudienceController.php`

- [x] **Step 1: Create BroadcastTemplateController**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\BroadcastTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastTemplateController extends Controller
{
    use ResolvesChurchData;

    /** GET /dashboard/communication/templates */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $templates = BroadcastTemplate::orderByDesc('created_at')
            ->get(['id', 'name', 'subject', 'body', 'category', 'usage_count', 'created_at']);

        return Inertia::render('Dashboard/Communication/Templates/Index', [
            'templates' => $templates,
            'canManage' => $request->user()->can('communication.manage'),
        ]);
    }

    /** POST /dashboard/communication/templates */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:80',
        ]);

        BroadcastTemplate::create([
            ...$data,
            'church_id'  => $this->resolvedChurchId(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Template saved.');
    }

    /** PUT /dashboard/communication/templates/{template} */
    public function update(Request $request, BroadcastTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:80',
        ]);

        $template->update($data);

        return back()->with('success', 'Template updated.');
    }

    /** DELETE /dashboard/communication/templates/{template} */
    public function destroy(BroadcastTemplate $template): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.manage'), 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }
}
```

- [x] **Step 2: Create BroadcastAudienceController**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastAudienceController extends Controller
{
    use ResolvesChurchData;

    public function __construct(private readonly BroadcastService $service) {}

    /** GET /dashboard/communication/audiences */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $audiences = BroadcastAudience::orderByDesc('created_at')
            ->get(['id', 'name', 'description', 'audience_type', 'audience_config', 'member_count', 'created_at']);

        return Inertia::render('Dashboard/Communication/Audiences/Index', [
            'audiences'   => $audiences,
            'departments' => $this->activeDepartments(),
            'canManage'   => $request->user()->can('communication.manage'),
        ]);
    }

    /** GET /dashboard/communication/audiences/resolve-count */
    public function resolveCount(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $request->validate([
            'audience_type'   => 'required|string',
            'audience_config' => 'nullable|array',
        ]);

        // Build a transient (never-persisted) Broadcast to reuse resolveAudience()
        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $request->input('audience_type');
        $proxy->audience_config = $request->input('audience_config');

        $count = $this->service->resolveAudience($proxy)->count();

        return response()->json(['count' => $count]);
    }

    /** POST /dashboard/communication/audiences */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'description'     => 'nullable|string|max:500',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers',
            'audience_config' => 'nullable|array',
        ]);

        // Snapshot current member count
        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $data['audience_type'];
        $proxy->audience_config = $data['audience_config'] ?? null;
        $count = $this->service->resolveAudience($proxy)->count();

        BroadcastAudience::create([
            ...$data,
            'church_id'    => $this->resolvedChurchId(),
            'created_by'   => $request->user()->id,
            'member_count' => $count,
        ]);

        return back()->with('success', 'Audience saved.');
    }

    /** PUT /dashboard/communication/audiences/{audience} */
    public function update(Request $request, BroadcastAudience $audience): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'description'     => 'nullable|string|max:500',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers',
            'audience_config' => 'nullable|array',
        ]);

        $proxy               = new Broadcast();
        $proxy->church_id    = $this->resolvedChurchId();
        $proxy->audience_type   = $data['audience_type'];
        $proxy->audience_config = $data['audience_config'] ?? null;
        $count = $this->service->resolveAudience($proxy)->count();

        $audience->update([...$data, 'member_count' => $count]);

        return back()->with('success', 'Audience updated.');
    }

    /** DELETE /dashboard/communication/audiences/{audience} */
    public function destroy(BroadcastAudience $audience): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.manage'), 403);
        $audience->delete();
        return back()->with('success', 'Audience deleted.');
    }
}
```

- [x] **Step 3: Commit**

```bash
git add app/Http/Controllers/Dashboard/BroadcastTemplateController.php app/Http/Controllers/Dashboard/BroadcastAudienceController.php
git commit -m "feat(comms): BroadcastTemplateController + BroadcastAudienceController"
```

---

### Task 12: BroadcastController

**Files:**
- Create: `app/Http/Controllers/Dashboard/BroadcastController.php`

- [x] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use App\Models\BroadcastTemplate;
use App\Models\BroadcastAudience;
use App\Services\BroadcastService;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function __construct(private readonly BroadcastService $service) {}

    /** GET /dashboard/communication/broadcasts */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $broadcasts = Broadcast::with('creator:id,name,avatar')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Dashboard/Communication/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
            'filters'    => $request->only('status'),
            'canSend'    => $request->user()->can('communication.send') || $request->user()->can('communication.send_dept'),
            'canDelete'  => $request->user()->can('communication.delete'),
        ]);
    }

    /** GET /dashboard/communication/broadcasts/create */
    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);

        $templates = BroadcastTemplate::orderBy('name')->get(['id', 'name', 'subject', 'body', 'category']);
        $audiences = BroadcastAudience::orderBy('name')->get(['id', 'name', 'audience_type', 'member_count']);

        // Coordinators see only their departments for send_dept restriction
        $depts = $this->activeDepartments();
        if ($user->can('communication.send_dept') && ! $user->can('communication.send')) {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');
            $depts = $depts->whereIn('id', $coordDeptIds)->values();
        }

        return Inertia::render('Dashboard/Communication/Broadcasts/Create', [
            'templates'   => $templates,
            'audiences'   => $audiences,
            'departments' => $depts,
            'canSendAll'  => $user->can('communication.send'),
        ]);
    }

    /** POST /dashboard/communication/broadcasts */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);

        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'subject'         => 'required|string|max:200',
            'body'            => 'required|string',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers,saved_audience',
            'audience_config' => 'nullable|array',
            'template_id'     => 'nullable|exists:broadcast_templates,id',
            'scheduled_at'    => 'nullable|date|after:now',
            'action'          => 'required|in:draft,send,schedule',
        ]);

        // send_dept restriction: coordinator may only target their own departments
        if (! $user->can('communication.send') && $data['audience_type'] === 'department') {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');

            $targetDeptId = $data['audience_config']['department_id'] ?? null;

            if (! $targetDeptId || ! $coordDeptIds->contains($targetDeptId)) {
                abort(403, 'You may only send messages to departments you coordinate.');
            }
        }

        // Coordinators cannot send to non-department audiences
        if (! $user->can('communication.send') && $data['audience_type'] !== 'department') {
            abort(403, 'You may only send messages to a specific department.');
        }

        $broadcast = Broadcast::create([
            'church_id'       => $this->resolvedChurchId(),
            'created_by'      => $user->id,
            'title'           => $data['title'],
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'status'          => 'draft',
            'audience_type'   => $data['audience_type'],
            'audience_config' => $data['audience_config'] ?? null,
            'template_id'     => $data['template_id'] ?? null,
            'scheduled_at'    => $data['scheduled_at'] ?? null,
        ]);

        // Increment template usage
        if ($broadcast->template_id) {
            BroadcastTemplate::where('id', $broadcast->template_id)
                ->increment('usage_count');
        }

        $action = $data['action'];

        if ($action === 'send') {
            $this->service->send($broadcast);
            $this->auditLog('communication.broadcast_queued', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast sent.');
        }

        if ($action === 'schedule') {
            $this->service->schedule($broadcast);
            $this->auditLog('communication.broadcast_scheduled', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast scheduled.');
        }

        // draft
        $this->auditLog('communication.broadcast_drafted', $broadcast);
        return redirect()
            ->route('dashboard.communication.broadcasts.show', $broadcast)
            ->with('success', 'Draft saved.');
    }

    /** GET /dashboard/communication/broadcasts/{broadcast} */
    public function show(Request $request, Broadcast $broadcast): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $broadcast->load('creator:id,name,avatar');

        $recipients = $broadcast->recipients()
            ->with('user:id,name,avatar')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dashboard/Communication/Broadcasts/Show', [
            'broadcast'  => $broadcast->append('status_label'),
            'recipients' => $recipients,
            'filters'    => $request->only('status'),
            'canDelete'  => $request->user()->can('communication.delete'),
        ]);
    }

    /** DELETE /dashboard/communication/broadcasts/{broadcast} */
    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.delete'), 403);

        $this->auditLog('communication.broadcast_deleted', $broadcast);
        $broadcast->delete();

        return redirect()
            ->route('dashboard.communication.broadcasts.index')
            ->with('success', 'Broadcast deleted.');
    }

    /** POST /dashboard/communication/broadcasts/{broadcast}/preview */
    public function preview(Request $request, Broadcast $broadcast): JsonResponse
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $body    = $request->input('body', $broadcast->body);
        $preview = $this->service->previewBody($body, $request->user(), $broadcast->loadMissing('church'));

        return response()->json(['preview' => $preview]);
    }
}
```

- [x] **Step 2: Commit**

```bash
git add app/Http/Controllers/Dashboard/BroadcastController.php
git commit -m "feat(comms): BroadcastController â€” CRUD, send, schedule, preview"
```

---

### Task 13: Routes + Sidebar Navigation

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/DashboardLayout.vue`

- [x] **Step 1: Add use statements to `routes/web.php`**

After the existing `use App\Http\Controllers\Dashboard\SermonsController` line, add:

```php
use App\Http\Controllers\Dashboard\CommunicationController;
use App\Http\Controllers\Dashboard\BroadcastController;
use App\Http\Controllers\Dashboard\BroadcastTemplateController;
use App\Http\Controllers\Dashboard\BroadcastAudienceController;
```

- [x] **Step 2: Add route group to `routes/web.php`**

After the scheduling route group (search for `dashboard/scheduling`), add:

```php
    // â”€â”€ Communication Center â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::prefix('dashboard/communication')->name('dashboard.communication.')->group(function () {

        Route::get('/', [CommunicationController::class, 'index'])->name('index');

        Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
            Route::get('/',               [BroadcastController::class, 'index'])->name('index');
            Route::get('/create',         [BroadcastController::class, 'create'])->name('create');
            Route::post('/',              [BroadcastController::class, 'store'])->name('store');
            Route::get('/{broadcast}',    [BroadcastController::class, 'show'])->name('show');
            Route::delete('/{broadcast}', [BroadcastController::class, 'destroy'])->name('destroy');
            Route::post('/{broadcast}/preview', [BroadcastController::class, 'preview'])->name('preview');
        });

        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/',             [BroadcastTemplateController::class, 'index'])->name('index');
            Route::post('/',            [BroadcastTemplateController::class, 'store'])->name('store');
            Route::put('/{template}',   [BroadcastTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}',[BroadcastTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('audiences')->name('audiences.')->group(function () {
            // IMPORTANT: resolve-count MUST come before /{audience} wildcard
            Route::get('/resolve-count', [BroadcastAudienceController::class, 'resolveCount'])->name('resolve-count');
            Route::get('/',              [BroadcastAudienceController::class, 'index'])->name('index');
            Route::post('/',             [BroadcastAudienceController::class, 'store'])->name('store');
            Route::put('/{audience}',    [BroadcastAudienceController::class, 'update'])->name('update');
            Route::delete('/{audience}', [BroadcastAudienceController::class, 'destroy'])->name('destroy');
        });
    });
```

- [x] **Step 3: Add `Radio` + `Send` to lucide imports in `DashboardLayout.vue`**

Find the lucide import block (around line 18-23) and add `Radio` and `Send`:

```typescript
import {
    LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
    Users, Building2, BarChart3, Settings, LogOut,
    Menu, X, ChevronRight, ExternalLink, Upload, ChevronDown,
    Shield, Hash, CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck,
    Layers, BookOpen, PlusCircle, Radio, Send, FileText,
} from 'lucide-vue-next'
```

- [x] **Step 4: Add Communication nav group to `DashboardLayout.vue`**

In the `navGroups` computed array, before the `'Administration'` group, add:

```typescript
        {
            label: 'Communication',
            items: [
                { label: 'Dashboard',  href: '/dashboard/communication',            icon: Radio,     perm: 'communication.view', exact: true },
                { label: 'Broadcasts', href: '/dashboard/communication/broadcasts', icon: Send,      perm: 'communication.view' },
                { label: 'Templates',  href: '/dashboard/communication/templates',  icon: FileText,  perm: 'communication.view' },
                { label: 'Audiences',  href: '/dashboard/communication/audiences',  icon: Users,     perm: 'communication.view' },
            ],
        },
```

- [x] **Step 5: Verify routes register correctly**

```bash
php artisan route:list --path=dashboard/communication
```

Expected: 12 routes listed (1 dashboard + 5 broadcast + 4 template + 5 audience including resolve-count).

- [x] **Step 6: Commit**

```bash
git add routes/web.php resources/js/Layouts/DashboardLayout.vue
git commit -m "feat(comms): routes + Communication sidebar nav group"
```

---

### Task 14: Feature Tests

**Files:**
- Create: `tests/Feature/BroadcastTest.php`

- [x] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastJob;
use App\Models\Broadcast;
use App\Models\BroadcastTemplate;
use App\Models\Church;
use App\Models\Department;
use App\Models\User;
use App\Services\BroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Church     $church;
    private User       $admin;
    private User       $coordinator;
    private User       $member;
    private Department $dept;
    private Department $otherDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->church = Church::factory()->create();
        app()->instance('church.id', $this->church->id);

        $this->admin = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('church_admin');

        $this->coordinator = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->member = User::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->member->assignRole('member');

        $this->dept = Department::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);
        $this->otherDept = Department::factory()->create([
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);

        // Attach coordinator to dept with coordinator pivot role
        $this->dept->members()->attach($this->coordinator->id, ['role' => 'coordinator']);
    }

    // COM-001: admin can create a draft broadcast
    public function test_admin_can_create_draft_broadcast(): void
    {
        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Test Broadcast',
                'subject'       => 'Hello Church',
                'body'          => 'Dear {{member_name}}, greetings!',
                'audience_type' => 'all_members',
                'action'        => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'church_id'     => $this->church->id,
            'title'         => 'Test Broadcast',
            'status'        => 'draft',
            'audience_type' => 'all_members',
        ]);
    }

    // COM-002: send action dispatches job and creates recipient rows
    public function test_send_action_dispatches_job_and_creates_recipients(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Live Broadcast',
                'subject'       => 'News',
                'body'          => 'Hello {{member_name}}',
                'audience_type' => 'all_members',
                'action'        => 'send',
            ])
            ->assertRedirect();

        $broadcast = Broadcast::where('title', 'Live Broadcast')->first();
        $this->assertNotNull($broadcast);

        $this->assertDatabaseHas('broadcast_recipients', [
            'broadcast_id' => $broadcast->id,
            'user_id'      => $this->admin->id,
        ]);

        Queue::assertPushed(SendBroadcastJob::class, fn ($job) => $job->broadcastId === $broadcast->id);
    }

    // COM-003: coordinator cannot send to a department they don't coordinate
    public function test_coordinator_cannot_send_to_other_department(): void
    {
        $this->actingAs($this->coordinator)
            ->post('/dashboard/communication/broadcasts', [
                'title'           => 'Sneaky Broadcast',
                'subject'         => 'Oops',
                'body'            => 'Hello',
                'audience_type'   => 'department',
                'audience_config' => ['department_id' => $this->otherDept->id],
                'action'          => 'send',
            ])
            ->assertForbidden();
    }

    // COM-004: coordinator CAN send to their own department
    public function test_coordinator_can_send_to_own_department(): void
    {
        Queue::fake();

        $this->actingAs($this->coordinator)
            ->post('/dashboard/communication/broadcasts', [
                'title'           => 'Dept Broadcast',
                'subject'         => 'Hi Team',
                'body'            => 'Meeting tonight',
                'audience_type'   => 'department',
                'audience_config' => ['department_id' => $this->dept->id],
                'action'          => 'send',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'title'  => 'Dept Broadcast',
            'status' => 'sending',
        ]);
    }

    // COM-005: member cannot create a broadcast
    public function test_member_cannot_create_broadcast(): void
    {
        $this->actingAs($this->member)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Spam',
                'subject'       => 'Hello',
                'body'          => 'World',
                'audience_type' => 'all_members',
                'action'        => 'send',
            ])
            ->assertForbidden();
    }

    // COM-006: schedule action sets status to scheduled
    public function test_schedule_action_sets_status_to_scheduled(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Future Broadcast',
                'subject'       => 'Coming Soon',
                'body'          => 'See you then',
                'audience_type' => 'all_members',
                'action'        => 'schedule',
                'scheduled_at'  => now()->addHours(2)->toISOString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('broadcasts', [
            'title'  => 'Future Broadcast',
            'status' => 'scheduled',
        ]);
    }

    // COM-007: using a template increments its usage_count
    public function test_template_usage_count_increments(): void
    {
        Queue::fake();

        $template = BroadcastTemplate::create([
            'church_id'   => $this->church->id,
            'created_by'  => $this->admin->id,
            'name'        => 'My Template',
            'subject'     => 'Weekly Update',
            'body'        => 'Hello {{member_name}}',
            'usage_count' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post('/dashboard/communication/broadcasts', [
                'title'         => 'Template Broadcast',
                'subject'       => 'Weekly Update',
                'body'          => 'Hello {{member_name}}',
                'audience_type' => 'all_members',
                'template_id'   => $template->id,
                'action'        => 'draft',
            ]);

        $this->assertDatabaseHas('broadcast_templates', [
            'id'          => $template->id,
            'usage_count' => 1,
        ]);
    }

    // COM-008: member can view communication dashboard (200)
    public function test_member_cannot_view_communication_dashboard(): void
    {
        $this->actingAs($this->member)
            ->get('/dashboard/communication')
            ->assertForbidden();
    }

    // COM-009: admin can view communication dashboard
    public function test_admin_can_view_communication_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard/communication')
            ->assertOk();
    }

    // COM-010: coordinator can view dashboard (has communication.view)
    public function test_coordinator_can_view_communication_dashboard(): void
    {
        $this->actingAs($this->coordinator)
            ->get('/dashboard/communication')
            ->assertOk();
    }

    // COM-011: resolve-count returns correct count for all_members
    public function test_resolve_count_returns_member_count(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/dashboard/communication/audiences/resolve-count?audience_type=all_members')
            ->assertOk()
            ->assertJsonPath('count', 3); // admin + coordinator + member
    }

    // COM-012: renderBody replaces all three variables
    public function test_render_body_replaces_variables(): void
    {
        $broadcast = new Broadcast();
        $broadcast->church_id     = $this->church->id;
        $broadcast->audience_type = 'all_members';
        $broadcast->setRelation('church', $this->church);

        $service  = app(BroadcastService::class);
        $rendered = $service->renderBody(
            'Hi {{member_name}}, welcome to {{church_name}}. This is from {{department_name}}.',
            $this->member,
            $broadcast,
        );

        $this->assertStringContainsString($this->member->name, $rendered);
        $this->assertStringContainsString($this->church->name, $rendered);
        $this->assertStringNotContainsString('{{member_name}}', $rendered);
        $this->assertStringNotContainsString('{{church_name}}', $rendered);
        $this->assertStringNotContainsString('{{department_name}}', $rendered);
    }
}
```

- [x] **Step 2: Run the tests**

```bash
php artisan test tests/Feature/BroadcastTest.php --verbose
```

Expected: 12 tests, 12 passed.

- [x] **Step 3: Commit**

```bash
git add tests/Feature/BroadcastTest.php
git commit -m "test(comms): BroadcastTest â€” COM-001 through COM-012"
```

---

### Task 15: Communication/Dashboard.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Communication/Dashboard.vue`

- [x] **Step 1: Create the page**

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Radio, Send, Users, FileText, Calendar, TrendingUp, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import type { Broadcast } from '@/types'

const props = defineProps<{
    stats: {
        sent_this_month: number
        delivery_rate: number
        scheduled_count: number
    }
    upcoming: Broadcast[]
    recent: Broadcast[]
    canSend: boolean
}>()

const statusColor = (status: string) => ({
    sent:      'bg-emerald-100 text-emerald-700',
    sending:   'bg-blue-100 text-blue-700',
    failed:    'bg-red-100 text-red-700',
    scheduled: 'bg-amber-100 text-amber-700',
    draft:     'bg-neutral-100 text-neutral-600',
})[status] ?? 'bg-neutral-100 text-neutral-600'

function fmtDate(iso: string | null): string {
    if (!iso) return 'â€”'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <DashboardLayout title="Communication">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-8 py-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <Radio class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-900">Communication</h1>
                        <p class="text-sm text-neutral-500">Send targeted messages to your congregation</p>
                    </div>
                </div>
                <Link
                    v-if="canSend"
                    href="/dashboard/communication/broadcasts/create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition"
                >
                    <Send class="w-4 h-4" />
                    New Broadcast
                </Link>
            </div>

            <!-- Quick Actions -->
            <div v-if="canSend" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link
                    href="/dashboard/communication/broadcasts/create"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Send class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">New Broadcast</span>
                </Link>
                <Link
                    href="/dashboard/communication/templates"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <FileText class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">Templates</span>
                </Link>
                <Link
                    href="/dashboard/communication/audiences"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Users class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">Audiences</span>
                </Link>
                <Link
                    href="/dashboard/communication/broadcasts"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Radio class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">All Broadcasts</span>
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <Send class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Sent This Month</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.sent_this_month }}</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingUp class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Delivery Rate</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.delivery_rate }}%</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <Calendar class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Scheduled</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.scheduled_count }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Upcoming Broadcasts -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-neutral-700 mb-4 flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-amber-500" /> Upcoming Scheduled
                    </h2>
                    <div v-if="upcoming.length === 0" class="text-sm text-neutral-400 text-center py-6">
                        No scheduled broadcasts
                    </div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="b in upcoming"
                            :key="b.id"
                            :href="`/dashboard/communication/broadcasts/${b.id}`"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-neutral-50 transition"
                        >
                            <div>
                                <p class="text-sm font-medium text-neutral-800">{{ b.title }}</p>
                                <p class="text-xs text-neutral-500">{{ fmtDate(b.scheduled_at) }} Â· {{ b.recipient_count }} recipients</p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Scheduled</span>
                        </Link>
                    </div>
                </div>

                <!-- Recent Broadcasts -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-neutral-700 mb-4 flex items-center gap-2">
                        <CheckCircle2 class="w-4 h-4 text-emerald-500" /> Recent Sent
                    </h2>
                    <div v-if="recent.length === 0" class="text-sm text-neutral-400 text-center py-6">
                        No broadcasts sent yet
                    </div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="b in recent"
                            :key="b.id"
                            :href="`/dashboard/communication/broadcasts/${b.id}`"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-neutral-50 transition"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-neutral-800 truncate">{{ b.title }}</p>
                                <p class="text-xs text-neutral-500">{{ fmtDate(b.sent_at) }} Â· {{ b.delivered_count }}/{{ b.recipient_count }} delivered</p>
                            </div>
                            <span :class="['text-xs px-2 py-0.5 rounded-full ml-2 shrink-0', statusColor(b.status)]">
                                {{ b.status_label }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
```

- [x] **Step 2: Commit**

```bash
git add resources/js/Pages/Dashboard/Communication/Dashboard.vue
git commit -m "feat(comms): Communication/Dashboard.vue â€” stats + quick actions"
```

---

### Task 16: Broadcasts/Index.vue + Broadcasts/Show.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Communication/Broadcasts/Index.vue`
- Create: `resources/js/Pages/Dashboard/Communication/Broadcasts/Show.vue`

- [x] **Step 1: Create Broadcasts/Index.vue**

```vue
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Send, Plus, Trash2 } from 'lucide-vue-next'
import type { Broadcast } from '@/types'

const props = defineProps<{
    broadcasts: {
        data: Broadcast[]
        links: any[]
        current_page: number
        last_page: number
    }
    filters: { status?: string }
    canSend: boolean
    canDelete: boolean
}>()

const statuses = ['', 'draft', 'scheduled', 'sending', 'sent', 'failed']
const statusLabel = (s: string) => s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All'

function filterStatus(s: string) {
    router.get('/dashboard/communication/broadcasts', { status: s || undefined }, { preserveState: true, replace: true })
}

const statusColor = (status: string) => ({
    sent:      'bg-emerald-100 text-emerald-700',
    sending:   'bg-blue-100 text-blue-700',
    failed:    'bg-red-100 text-red-700',
    scheduled: 'bg-amber-100 text-amber-700',
    draft:     'bg-neutral-100 text-neutral-600',
})[status] ?? 'bg-neutral-100 text-neutral-600'

function fmtDate(iso: string | null) {
    if (!iso) return 'â€”'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function deleteBroadcast(b: Broadcast) {
    if (!confirm(`Delete "${b.title}"?`)) return
    router.delete(`/dashboard/communication/broadcasts/${b.id}`)
}
</script>

<template>
    <DashboardLayout title="Broadcasts">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6 py-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-neutral-900">Broadcasts</h1>
                <Link
                    v-if="canSend"
                    href="/dashboard/communication/broadcasts/create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition"
                >
                    <Plus class="w-4 h-4" /> New Broadcast
                </Link>
            </div>

            <!-- Status filter tabs -->
            <div class="flex gap-2 flex-wrap">
                <button
                    v-for="s in statuses"
                    :key="s"
                    @click="filterStatus(s)"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg font-medium transition',
                        (filters.status ?? '') === s
                            ? 'bg-brand-600 text-white'
                            : 'bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-50'
                    ]"
                >
                    {{ statusLabel(s) }}
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-neutral-50 border-b border-neutral-200">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Title</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Audience</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Status</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Recipients</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-if="broadcasts.data.length === 0">
                            <td colspan="6" class="text-center text-neutral-400 py-12">No broadcasts found</td>
                        </tr>
                        <tr v-for="b in broadcasts.data" :key="b.id" class="hover:bg-neutral-50 transition">
                            <td class="px-4 py-3">
                                <Link :href="`/dashboard/communication/broadcasts/${b.id}`" class="font-medium text-neutral-800 hover:text-brand-600">
                                    {{ b.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 capitalize">{{ b.audience_type.replace('_', ' ') }}</td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColor(b.status)]">
                                    {{ b.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ b.recipient_count }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ fmtDate(b.sent_at ?? b.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="canDelete"
                                    @click="deleteBroadcast(b)"
                                    class="p-1.5 text-neutral-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="broadcasts.last_page > 1" class="flex gap-2 justify-center flex-wrap">
                <Link
                    v-for="link in broadcasts.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg border transition',
                        link.active ? 'bg-brand-600 text-white border-brand-600' : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                />
            </div>
        </div>
    </DashboardLayout>
</template>
```

- [x] **Step 2: Create Broadcasts/Show.vue**

```vue
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import { ArrowLeft, Send, Users, CheckCircle2, XCircle, Clock, Trash2 } from 'lucide-vue-next'
import type { Broadcast, BroadcastRecipient } from '@/types'

const props = defineProps<{
    broadcast: Broadcast
    recipients: {
        data: BroadcastRecipient[]
        links: any[]
        current_page: number
        last_page: number
    }
    filters: { status?: string }
    canDelete: boolean
}>()

const statusColor = (s: string) => ({
    sent:    'bg-emerald-100 text-emerald-700',
    failed:  'bg-red-100 text-red-700',
    pending: 'bg-amber-100 text-amber-700',
})[s] ?? 'bg-neutral-100 text-neutral-600'

const broadcastStatusColor = (s: string) => ({
    sent:      'bg-emerald-100 text-emerald-700',
    sending:   'bg-blue-100 text-blue-700',
    failed:    'bg-red-100 text-red-700',
    scheduled: 'bg-amber-100 text-amber-700',
    draft:     'bg-neutral-100 text-neutral-600',
})[s] ?? 'bg-neutral-100 text-neutral-600'

function filterStatus(s: string) {
    router.get(`/dashboard/communication/broadcasts/${props.broadcast.id}`, { status: s || undefined }, { preserveState: true, replace: true })
}

function fmtDate(iso: string | null) {
    if (!iso) return 'â€”'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function deleteBroadcast() {
    if (!confirm(`Delete "${props.broadcast.title}"?`)) return
    router.delete(`/dashboard/communication/broadcasts/${props.broadcast.id}`)
}
</script>

<template>
    <DashboardLayout :title="broadcast.title">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6 py-6">

            <!-- Back + header -->
            <div class="flex items-center gap-4">
                <Link href="/dashboard/communication/broadcasts" class="p-2 text-neutral-400 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition">
                    <ArrowLeft class="w-5 h-5" />
                </Link>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-neutral-900">{{ broadcast.title }}</h1>
                    <p class="text-sm text-neutral-500">Subject: {{ broadcast.subject }}</p>
                </div>
                <span :class="['text-sm px-3 py-1 rounded-full font-medium', broadcastStatusColor(broadcast.status)]">
                    {{ broadcast.status_label }}
                </span>
                <button v-if="canDelete" @click="deleteBroadcast" class="p-2 text-neutral-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition">
                    <Trash2 class="w-5 h-5" />
                </button>
            </div>

            <!-- Stats strip -->
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-neutral-900">{{ broadcast.recipient_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><Users class="w-3 h-3" /> Recipients</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ broadcast.delivered_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><CheckCircle2 class="w-3 h-3" /> Delivered</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ broadcast.failed_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><XCircle class="w-3 h-3" /> Failed</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-neutral-900">
                        {{ broadcast.recipient_count > 0 ? Math.round((broadcast.delivered_count / broadcast.recipient_count) * 100) : 0 }}%
                    </p>
                    <p class="text-xs text-neutral-500 mt-1">Delivery Rate</p>
                </div>
            </div>

            <!-- Body preview -->
            <div class="bg-white border border-neutral-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-neutral-700 mb-3">Message Body</h2>
                <p class="text-sm text-neutral-700 whitespace-pre-wrap">{{ broadcast.body }}</p>
            </div>

            <!-- Recipient table -->
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-neutral-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-700">Recipients</h2>
                    <div class="flex gap-2">
                        <button
                            v-for="s in ['', 'sent', 'failed', 'pending']"
                            :key="s"
                            @click="filterStatus(s)"
                            :class="[
                                'px-2.5 py-1 text-xs rounded-lg font-medium transition',
                                (filters.status ?? '') === s
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
                            ]"
                        >
                            {{ s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All' }}
                        </button>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-neutral-50 border-b border-neutral-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Member</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Status</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Delivered At</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Failure Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <tr v-if="recipients.data.length === 0">
                            <td colspan="4" class="text-center text-neutral-400 py-8">No recipients</td>
                        </tr>
                        <tr v-for="r in recipients.data" :key="r.id" class="hover:bg-neutral-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <AppAvatar :user="r.user" size="sm" />
                                    <span class="text-neutral-800">{{ r.user?.name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColor(r.status)]">
                                    {{ r.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 text-xs">{{ fmtDate(r.sent_at) }}</td>
                            <td class="px-4 py-3 text-red-500 text-xs">{{ r.failure_reason ?? 'â€”' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="recipients.last_page > 1" class="flex gap-2 justify-center flex-wrap">
                <Link
                    v-for="link in recipients.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg border transition',
                        link.active ? 'bg-brand-600 text-white border-brand-600' : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                />
            </div>
        </div>
    </DashboardLayout>
</template>
```

- [x] **Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard/Communication/Broadcasts/Index.vue resources/js/Pages/Dashboard/Communication/Broadcasts/Show.vue
git commit -m "feat(comms): Broadcasts/Index.vue + Broadcasts/Show.vue"
```

---

### Task 17: Broadcasts/Create.vue (split-panel composer)

**Files:**
- Create: `resources/js/Pages/Dashboard/Communication/Broadcasts/Create.vue`

- [x] **Step 1: Create the split-panel composer**

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Users, ChevronDown, ArrowLeft, Eye, Send, Calendar, Save } from 'lucide-vue-next'
import type { BroadcastTemplate, BroadcastAudience } from '@/types'

const props = defineProps<{
    templates: BroadcastTemplate[]
    audiences: BroadcastAudience[]
    departments: { id: number; name: string; icon: string | null; color: string | null }[]
    canSendAll: boolean
}>()

// â”€â”€ Form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const form = useForm({
    title:           '',
    subject:         '',
    body:            '',
    audience_type:   'all_members' as string,
    audience_config: null as Record<string, any> | null,
    template_id:     null as number | null,
    scheduled_at:    '',
    action:          'draft' as 'draft' | 'send' | 'schedule',
})

// â”€â”€ Audience picker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const audienceOptions = computed(() => {
    const opts = [
        { value: 'all_members',    label: 'All Members',      desc: 'Everyone in your church' },
        { value: 'department',     label: 'Department',       desc: 'Members of a specific department' },
        { value: 'role',           label: 'By Role',          desc: 'Coordinators, assistantsâ€¦' },
        { value: 'event_attendees',label: 'Event Attendees',  desc: 'People who RSVPed to an event' },
        { value: 'volunteers',     label: 'Volunteers',       desc: 'People with serving assignments' },
    ]
    if (props.canSendAll) {
        opts.push({ value: 'saved_audience', label: 'Saved Audience', desc: 'A reusable preset group' })
    }
    // If coordinator-only, filter to department only
    if (!props.canSendAll) {
        return opts.filter(o => o.value === 'department')
    }
    return opts
})

// Live recipient count
const recipientCount = ref<number | null>(null)
const countLoading   = ref(false)

async function fetchCount() {
    if (!form.audience_type) return
    countLoading.value = true
    try {
        const params = new URLSearchParams({ audience_type: form.audience_type })
        if (form.audience_config) {
            Object.entries(form.audience_config).forEach(([k, v]) =>
                params.append(`audience_config[${k}]`, String(v))
            )
        }
        const resp = await window.axios.get(`/dashboard/communication/audiences/resolve-count?${params}`)
        recipientCount.value = resp.data.count
    } catch {
        recipientCount.value = null
    } finally {
        countLoading.value = false
    }
}

watch([() => form.audience_type, () => form.audience_config], fetchCount, { deep: true, immediate: true })

// â”€â”€ Template picker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function applyTemplate(t: BroadcastTemplate) {
    form.subject     = t.subject
    form.body        = t.body
    form.template_id = t.id
    if (!form.title) form.title = t.name
}

// â”€â”€ Variable chips â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const bodyRef = ref<HTMLTextAreaElement | null>(null)
const variables = ['{{member_name}}', '{{church_name}}', '{{department_name}}']

function insertVariable(v: string) {
    const ta = bodyRef.value
    if (!ta) { form.body += v; return }
    const start = ta.selectionStart ?? form.body.length
    const end   = ta.selectionEnd   ?? form.body.length
    form.body = form.body.slice(0, start) + v + form.body.slice(end)
    // Restore cursor after the inserted variable
    setTimeout(() => {
        ta.focus()
        ta.setSelectionRange(start + v.length, start + v.length)
    }, 0)
}

// â”€â”€ Preview â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const preview     = ref('')
const showPreview = ref(false)

async function loadPreview() {
    try {
        const resp = await window.axios.post('/dashboard/communication/broadcasts/preview-anonymous', {
            body: form.body,
            audience_type: form.audience_type,
        })
        // Fallback: just show the raw body if endpoint not yet available
        preview.value = resp.data?.preview ?? form.body
    } catch {
        preview.value = form.body
    }
    showPreview.value = true
}

// â”€â”€ Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function submit(action: 'draft' | 'send' | 'schedule') {
    form.action = action
    form.post('/dashboard/communication/broadcasts')
}

const submitLabel = computed(() => {
    if (recipientCount.value !== null && recipientCount.value > 0) {
        return `Send to ${recipientCount.value} recipient${recipientCount.value !== 1 ? 's' : ''}`
    }
    return 'Send'
})
</script>

<template>
    <DashboardLayout title="New Broadcast">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-4">

            <!-- Top bar -->
            <div class="flex items-center gap-3">
                <a href="/dashboard/communication/broadcasts" class="p-2 text-neutral-400 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition">
                    <ArrowLeft class="w-5 h-5" />
                </a>
                <h1 class="text-xl font-bold text-neutral-900 flex-1">New Broadcast</h1>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="loadPreview"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-neutral-600 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition"
                    >
                        <Eye class="w-4 h-4" /> Preview
                    </button>
                </div>
            </div>

            <!-- Split panel -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                <!-- LEFT: Audience picker -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5 space-y-5">
                    <h2 class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                        <Users class="w-4 h-4" /> Audience
                    </h2>

                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Broadcast Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="e.g. Sunday Service Reminder"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Audience type cards -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-2">Send To</label>
                        <div class="space-y-2">
                            <button
                                v-for="opt in audienceOptions"
                                :key="opt.value"
                                type="button"
                                @click="form.audience_type = opt.value; form.audience_config = null"
                                :class="[
                                    'w-full text-left px-3 py-2.5 rounded-lg border transition text-sm',
                                    form.audience_type === opt.value
                                        ? 'border-brand-500 bg-brand-50 text-brand-800'
                                        : 'border-neutral-200 hover:border-neutral-300 text-neutral-700'
                                ]"
                            >
                                <span class="font-medium">{{ opt.label }}</span>
                                <span class="block text-xs text-neutral-500 mt-0.5">{{ opt.desc }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Sub-picker: department -->
                    <div v-if="form.audience_type === 'department'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Department</label>
                        <select
                            @change="form.audience_config = { department_id: parseInt(($event.target as HTMLSelectElement).value) }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">â€” choose â€”</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <!-- Sub-picker: role -->
                    <div v-if="form.audience_type === 'role'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Role</label>
                        <select
                            @change="form.audience_config = { role: ($event.target as HTMLSelectElement).value }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">â€” choose â€”</option>
                            <option value="church_admin">Church Admin</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="assistant_coordinator">Assistant Coordinator</option>
                            <option value="member">Member</option>
                        </select>
                    </div>

                    <!-- Sub-picker: saved audience -->
                    <div v-if="form.audience_type === 'saved_audience'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Saved Audience</label>
                        <select
                            @change="form.audience_config = { audience_id: parseInt(($event.target as HTMLSelectElement).value) }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">â€” choose â€”</option>
                            <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }} ({{ a.member_count }})</option>
                        </select>
                    </div>

                    <!-- Live count badge -->
                    <div class="flex items-center gap-2 pt-1">
                        <div class="h-px flex-1 bg-neutral-100"></div>
                        <span v-if="countLoading" class="text-xs text-neutral-400">Countingâ€¦</span>
                        <span v-else-if="recipientCount !== null" class="text-xs font-medium px-2.5 py-1 rounded-full bg-brand-100 text-brand-700">
                            {{ recipientCount }} recipient{{ recipientCount !== 1 ? 's' : '' }}
                        </span>
                        <span v-else class="text-xs text-neutral-400">â€”</span>
                        <div class="h-px flex-1 bg-neutral-100"></div>
                    </div>
                </div>

                <!-- RIGHT: Message composer -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                        <Send class="w-4 h-4" /> Message
                    </h2>

                    <!-- Template picker -->
                    <div v-if="templates.length > 0">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Use Template (optional)</label>
                        <select
                            @change="(e) => { const id = parseInt((e.target as HTMLSelectElement).value); const t = templates.find(x => x.id === id); if (t) applyTemplate(t); }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">â€” select a template â€”</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            placeholder="Notification subject line"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                        <p v-if="form.errors.subject" class="text-xs text-red-500 mt-1">{{ form.errors.subject }}</p>
                    </div>

                    <!-- Body + variable chips -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-neutral-600">Message</label>
                            <div class="flex gap-1">
                                <button
                                    v-for="v in variables"
                                    :key="v"
                                    type="button"
                                    @click="insertVariable(v)"
                                    class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition font-mono"
                                >
                                    {{ v }}
                                </button>
                            </div>
                        </div>
                        <textarea
                            ref="bodyRef"
                            v-model="form.body"
                            rows="8"
                            placeholder="Write your message here. Use the variable chips above to personalise."
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 resize-none"
                        />
                        <p v-if="form.errors.body" class="text-xs text-red-500 mt-1">{{ form.errors.body }}</p>
                    </div>

                    <!-- Schedule field (optional) -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">
                            <Calendar class="w-3.5 h-3.5 inline mr-1" />Schedule For (optional)
                        </label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-2 pt-2">
                        <button
                            type="button"
                            @click="submit('draft')"
                            :disabled="form.processing"
                            class="flex-1 py-2 text-sm border border-neutral-200 text-neutral-700 rounded-lg hover:bg-neutral-50 transition font-medium disabled:opacity-50"
                        >
                            <Save class="w-4 h-4 inline mr-1" /> Save Draft
                        </button>
                        <button
                            v-if="form.scheduled_at"
                            type="button"
                            @click="submit('schedule')"
                            :disabled="form.processing || recipientCount === 0"
                            class="flex-1 py-2 text-sm bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition font-medium disabled:opacity-50"
                        >
                            <Calendar class="w-4 h-4 inline mr-1" /> Schedule
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="submit('send')"
                            :disabled="form.processing || recipientCount === 0"
                            class="flex-1 py-2 text-sm bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition font-medium disabled:opacity-50"
                        >
                            <Send class="w-4 h-4 inline mr-1" /> {{ submitLabel }}
                        </button>
                    </div>

                    <p v-if="form.errors.action" class="text-xs text-red-500">{{ form.errors.action }}</p>
                </div>
            </div>
        </div>

        <!-- Preview modal -->
        <Teleport to="body">
            <div v-if="showPreview" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-neutral-800">Message Preview</h3>
                        <button @click="showPreview = false" class="text-neutral-400 hover:text-neutral-700">âœ•</button>
                    </div>
                    <div class="bg-neutral-50 rounded-lg p-4 text-sm text-neutral-700 whitespace-pre-wrap">{{ preview }}</div>
                    <p class="text-xs text-neutral-400">Variables shown as-is â€” they are replaced per recipient when delivered.</p>
                    <button @click="showPreview = false" class="w-full py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 transition">
                        Close
                    </button>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
```

- [x] **Step 2: Commit**

```bash
git add resources/js/Pages/Dashboard/Communication/Broadcasts/Create.vue
git commit -m "feat(comms): Broadcasts/Create.vue â€” split-panel composer with live count + variables"
```

---

### Task 18: Templates/Index.vue + Audiences/Index.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Communication/Templates/Index.vue`
- Create: `resources/js/Pages/Dashboard/Communication/Audiences/Index.vue`

- [x] **Step 1: Create Templates/Index.vue**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { FileText, Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import type { BroadcastTemplate } from '@/types'

const props = defineProps<{
    templates: BroadcastTemplate[]
    canManage: boolean
}>()

// â”€â”€ Modal state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const showModal  = ref(false)
const editTarget = ref<BroadcastTemplate | null>(null)

const form = useForm({
    name:     '',
    subject:  '',
    body:     '',
    category: '',
})

function openCreate() {
    editTarget.value = null
    form.reset()
    showModal.value = true
}

function openEdit(t: BroadcastTemplate) {
    editTarget.value = t
    form.name     = t.name
    form.subject  = t.subject
    form.body     = t.body
    form.category = t.category ?? ''
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editTarget.value = null
    form.reset()
}

function save() {
    if (editTarget.value) {
        form.put(`/dashboard/communication/templates/${editTarget.value.id}`, {
            onSuccess: closeModal,
        })
    } else {
        form.post('/dashboard/communication/templates', {
            onSuccess: closeModal,
        })
    }
}

function deleteTemplate(t: BroadcastTemplate) {
    if (!confirm(`Delete template "${t.name}"?`)) return
    useForm({}).delete(`/dashboard/communication/templates/${t.id}`)
}
</script>

<template>
    <DashboardLayout title="Templates">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6 py-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-neutral-900 flex items-center gap-2">
                    <FileText class="w-6 h-6 text-neutral-400" /> Templates
                </h1>
                <button
                    v-if="canManage"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition"
                >
                    <Plus class="w-4 h-4" /> New Template
                </button>
            </div>

            <div v-if="templates.length === 0" class="text-center py-16 text-neutral-400">
                No templates yet. Create one to speed up your broadcasts.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                    v-for="t in templates"
                    :key="t.id"
                    class="bg-white border border-neutral-200 rounded-xl p-4 flex flex-col gap-2"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-neutral-800 truncate">{{ t.name }}</p>
                            <p class="text-xs text-neutral-500 truncate">{{ t.subject }}</p>
                        </div>
                        <div v-if="canManage" class="flex gap-1 shrink-0">
                            <button @click="openEdit(t)" class="p-1.5 text-neutral-400 hover:text-brand-600 rounded-lg hover:bg-brand-50 transition">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="deleteTemplate(t)" class="p-1.5 text-neutral-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-neutral-500 line-clamp-3">{{ t.body }}</p>
                    <div class="flex items-center gap-2 mt-auto pt-1">
                        <span v-if="t.category" class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600">{{ t.category }}</span>
                        <span class="text-xs text-neutral-400 ml-auto">Used {{ t.usage_count }}Ã—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-neutral-800">{{ editTarget ? 'Edit Template' : 'New Template' }}</h3>
                        <button @click="closeModal" class="text-neutral-400 hover:text-neutral-700"><X class="w-5 h-5" /></button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Name</label>
                            <input v-model="form.name" type="text" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Subject</label>
                            <input v-model="form.subject" type="text" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                            <p v-if="form.errors.subject" class="text-xs text-red-500 mt-1">{{ form.errors.subject }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Body</label>
                            <textarea v-model="form.body" rows="5" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 resize-none" />
                            <p v-if="form.errors.body" class="text-xs text-red-500 mt-1">{{ form.errors.body }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Category (optional)</label>
                            <input v-model="form.category" type="text" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button @click="closeModal" class="flex-1 py-2 text-sm border border-neutral-200 rounded-lg text-neutral-700 hover:bg-neutral-50 transition">Cancel</button>
                        <button @click="save" :disabled="form.processing" class="flex-1 py-2 text-sm bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition disabled:opacity-50">
                            {{ editTarget ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
```

- [x] **Step 2: Create Audiences/Index.vue**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Users, Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import type { BroadcastAudience } from '@/types'

const props = defineProps<{
    audiences: BroadcastAudience[]
    departments: { id: number; name: string }[]
    canManage: boolean
}>()

const audienceTypeLabel = (t: string) => ({
    all_members:    'All Members',
    department:     'Department',
    role:           'By Role',
    event_attendees:'Event Attendees',
    volunteers:     'Volunteers',
})[t] ?? t

// â”€â”€ Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const showModal  = ref(false)
const editTarget = ref<BroadcastAudience | null>(null)

const form = useForm({
    name:            '',
    description:     '',
    audience_type:   'all_members',
    audience_config: null as Record<string, any> | null,
})

function openCreate() {
    editTarget.value = null
    form.reset()
    showModal.value = true
}

function openEdit(a: BroadcastAudience) {
    editTarget.value = a
    form.name            = a.name
    form.description     = a.description ?? ''
    form.audience_type   = a.audience_type
    form.audience_config = a.audience_config
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.reset()
    editTarget.value = null
}

function save() {
    if (editTarget.value) {
        form.put(`/dashboard/communication/audiences/${editTarget.value.id}`, { onSuccess: closeModal })
    } else {
        form.post('/dashboard/communication/audiences', { onSuccess: closeModal })
    }
}

function deleteAudience(a: BroadcastAudience) {
    if (!confirm(`Delete "${a.name}"?`)) return
    useForm({}).delete(`/dashboard/communication/audiences/${a.id}`)
}
</script>

<template>
    <DashboardLayout title="Saved Audiences">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6 py-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-neutral-900 flex items-center gap-2">
                    <Users class="w-6 h-6 text-neutral-400" /> Saved Audiences
                </h1>
                <button
                    v-if="canManage"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition"
                >
                    <Plus class="w-4 h-4" /> New Audience
                </button>
            </div>

            <div v-if="audiences.length === 0" class="text-center py-16 text-neutral-400">
                No saved audiences yet. Create one to reuse audience presets.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="a in audiences"
                    :key="a.id"
                    class="bg-white border border-neutral-200 rounded-xl px-4 py-3 flex items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">{{ a.name }}</p>
                        <p v-if="a.description" class="text-xs text-neutral-500 truncate">{{ a.description }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600">
                            {{ audienceTypeLabel(a.audience_type) }}
                        </span>
                        <span class="text-xs text-neutral-500">{{ a.member_count }} members</span>
                        <div v-if="canManage" class="flex gap-1">
                            <button @click="openEdit(a)" class="p-1.5 text-neutral-400 hover:text-brand-600 rounded-lg hover:bg-brand-50 transition">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                            <button @click="deleteAudience(a)" class="p-1.5 text-neutral-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-neutral-800">{{ editTarget ? 'Edit Audience' : 'New Saved Audience' }}</h3>
                        <button @click="closeModal" class="text-neutral-400 hover:text-neutral-700"><X class="w-5 h-5" /></button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Name</label>
                            <input v-model="form.name" type="text" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Description (optional)</label>
                            <input v-model="form.description" type="text" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Audience Type</label>
                            <select v-model="form.audience_type" class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                                <option value="all_members">All Members</option>
                                <option value="department">Department</option>
                                <option value="role">By Role</option>
                                <option value="event_attendees">Event Attendees</option>
                                <option value="volunteers">Volunteers</option>
                            </select>
                        </div>
                        <!-- Department sub-picker -->
                        <div v-if="form.audience_type === 'department'">
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Department</label>
                            <select
                                @change="form.audience_config = { department_id: parseInt(($event.target as HTMLSelectElement).value) }"
                                class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                            >
                                <option value="">â€” choose â€”</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button @click="closeModal" class="flex-1 py-2 text-sm border border-neutral-200 rounded-lg text-neutral-700 hover:bg-neutral-50 transition">Cancel</button>
                        <button @click="save" :disabled="form.processing" class="flex-1 py-2 text-sm bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition disabled:opacity-50">
                            {{ editTarget ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
```

- [x] **Step 3: Commit**

```bash
git add resources/js/Pages/Dashboard/Communication/Templates/Index.vue resources/js/Pages/Dashboard/Communication/Audiences/Index.vue
git commit -m "feat(comms): Templates/Index.vue + Audiences/Index.vue"
```

---

### Task 19: Announcement Integration

**Files:**
- Modify: `app/Http/Controllers/Dashboard/AnnouncementsController.php`

- [x] **Step 1: Inject BroadcastService into the constructor**

The constructor currently is:
```php
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}
```

Replace it with:
```php
    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly \App\Services\BroadcastService $broadcastService,
    ) {}
```

- [x] **Step 2: Update `store()` to support `broadcast_to_members`**

In `store()`, after the `$this->auditLog(...)` call and the `if ($announcement->status === 'published')` block, add:

```php
        // Broadcast the announcement as a push notification if requested
        if ($request->boolean('broadcast_to_members') && $announcement->status === 'published') {
            $this->broadcastService->broadcastAnnouncement($announcement, $actor);
        }
```

The full updated `store()` method:

```php
    /** POST /dashboard/announcements */
    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $actor        = $request->user();
        $announcement = $this->announcements->create(
            $request->validated(),
            $this->resolvedChurchId(),
            $actor->id,
        );

        $this->auditLog('announcement.created', $announcement, [], [], ['department_id' => $announcement->department_id]);

        // Fire notification if the announcement was published immediately
        if ($announcement->status === 'published') {
            AnnouncementWasPublished::dispatch(
                $announcement->loadMissing('department'),
                $actor,
            );
        }

        // Broadcast announcement as in-app notification to relevant members
        if ($request->boolean('broadcast_to_members') && $announcement->status === 'published') {
            $this->broadcastService->broadcastAnnouncement($announcement, $actor);
        }

        return redirect()
            ->route('dashboard.announcements.show', $announcement)
            ->with('success', "\u{201c}{$announcement->title}\u{201d} posted.");
    }
```

- [x] **Step 3: Update `publish()` to support `broadcast_to_members`**

In the `publish()` method, after the `AnnouncementWasPublished::dispatch(...)` call and before `return back(...)`, add:

```php
        if ($request->boolean('broadcast_to_members')) {
            $this->broadcastService->broadcastAnnouncement(
                $announcement->fresh()->loadMissing('department'),
                $request->user(),
            );
        }
```

The updated tail of `publish()`:

```php
        $this->announcements->publish($announcement);
        $this->auditLog('announcement.published', $announcement, [], [], ['department_id' => $announcement->department_id]);

        // Notify relevant members (subscriber applies its own importance gate)
        AnnouncementWasPublished::dispatch(
            $announcement->fresh()->loadMissing('department'),
            $request->user(),
        );

        // Optionally broadcast as in-app notification
        if ($request->boolean('broadcast_to_members')) {
            $this->broadcastService->broadcastAnnouncement(
                $announcement->fresh()->loadMissing('department'),
                $request->user(),
            );
        }

        return back()->with('success', 'Announcement published.');
```

- [x] **Step 4: Commit**

```bash
git add app/Http/Controllers/Dashboard/AnnouncementsController.php
git commit -m "feat(comms): AnnouncementsController â€” broadcast_to_members integration"
```

---

## Self-Review

**Spec coverage check:**
- âœ… 4 DB migrations (templates â†’ audiences â†’ broadcasts â†’ recipients, correct FK order)
- âœ… 4 Eloquent models with BelongsToChurch, SoftDeletes, scopes, relationships
- âœ… TypeScript types for all 4 models
- âœ… 5 new permissions + role assignments + seeder re-run
- âœ… TYPE_BROADCAST constant + notification icon
- âœ… BroadcastService: resolveAudience (6 types), send, schedule, renderBody, broadcastAnnouncement
- âœ… SendBroadcastJob: ShouldQueue, $tries=3, $maxExceptions=1, chunkById(50), per-recipient notify
- âœ… CommunicationController: stats dashboard
- âœ… BroadcastTemplateController: CRUD
- âœ… BroadcastAudienceController: CRUD + resolveCount (static route before wildcard)
- âœ… BroadcastController: index, create, store (draft/send/schedule), show, destroy, preview + send_dept enforcement
- âœ… Routes: all 12 routes, /resolve-count before /{audience}
- âœ… Sidebar: Communication NavGroup with 4 items
- âœ… Feature tests: COM-001â€“COM-012 (12 tests)
- âœ… 6 Vue pages: Dashboard, Broadcasts/Index, Broadcasts/Create, Broadcasts/Show, Templates/Index, Audiences/Index
- âœ… Announcement integration: broadcast_to_members in store() + publish()

**Type consistency:** `BroadcastService::resolveAudience()` returns `Collection<User>`, `SendBroadcastJob` injects `BroadcastService` via `handle()`, `BroadcastController` and `BroadcastAudienceController` both inject via constructor â€” consistent throughout.

**Route conflict:** `/resolve-count` is declared before `/{audience}` in audiences group â€” âœ…

**send_dept enforcement:** Checked in `BroadcastController::store()` server-side on every request â€” âœ…

**Migration FK order:** templates â†’ audiences â†’ broadcasts (FKâ†’templates) â†’ recipients (FKâ†’broadcasts) â€” âœ…

