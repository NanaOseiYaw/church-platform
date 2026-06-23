# Volunteer Scheduling System — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Phase 1 of the volunteer scheduling system — service plans, position library, volunteer assignments with confirm/decline flow, and in-app notifications.

**Architecture:** Three-tier model (ServingPosition library → ServicePlanPosition slots → VolunteerAssignment rows). Plans move draft → published → archived; assignments move pending → confirmed/declined. All new models use the `BelongsToChurch` trait for automatic tenant scoping. Notifications use the existing `NotificationService` with the database channel only.

**Tech Stack:** Laravel 12, Eloquent, Inertia/Vue 3 `<script setup lang="ts">`, Spatie Permission, Pinia, lucide-vue-next.

---

## Context for all tasks

- **`BelongsToChurch` trait** — adds a global Eloquent scope `WHERE church_id = app('church.id')`. Use `Model::forChurch($id)` to scope explicitly (e.g. in tests).
- **`resolvedChurchId()`** — base `Controller` method, aborts 403 if `app('church.id')` is null.
- **`ResolvesChurchData` trait** — provides `activeDepartments()` and `churchMembers()` helpers for controllers.
- **Policies** are registered in `AppServiceProvider::$policies`, not in a separate AuthServiceProvider.
- **`Gate::before`** in `AppServiceProvider` gives `super_admin` unconditional access to every gate.
- **`config/permissions.php`** is the single source of truth for Spatie permissions. Edit it and re-run `RolesAndPermissionsSeeder` to apply changes.
- **No git repository** — skip all `git commit` steps.
- **Feature tests**: use `RefreshDatabase`, create `Church` directly (no factory: `Church::create(['name' => 'Test', 'slug' => 'test-'.uniqid()])`), seed permissions via `$this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class)`.

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_02_000001_create_service_plans_table.php`
- Create: `database/migrations/2026_06_02_000002_create_serving_positions_table.php`
- Create: `database/migrations/2026_06_02_000003_create_service_plan_positions_table.php`
- Create: `database/migrations/2026_06_02_000004_create_volunteer_assignments_table.php`

- [ ] **Step 1: Create service_plans migration**

```php
<?php
// database/migrations/2026_06_02_000001_create_service_plans_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('location')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_plans');
    }
};
```

- [ ] **Step 2: Create serving_positions migration**

```php
<?php
// database/migrations/2026_06_02_000002_create_serving_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['church_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_positions');
    }
};
```

- [ ] **Step 3: Create service_plan_positions migration**

```php
<?php
// database/migrations/2026_06_02_000003_create_service_plan_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_plan_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serving_position_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('service_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_plan_positions');
    }
};
```

- [ ] **Step 4: Create volunteer_assignments migration**

```php
<?php
// database/migrations/2026_06_02_000004_create_volunteer_assignments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_plan_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['service_plan_position_id', 'user_id']);
            $table->index(['church_id', 'user_id']);
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_assignments');
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate
```

Expected: `Running migrations... 2026_06_02_000001... 2026_06_02_000002... 2026_06_02_000003... 2026_06_02_000004...` all show `DONE`.

---

## Task 2: Eloquent Models + TypeScript Types

**Files:**
- Create: `app/Models/ServicePlan.php`
- Create: `app/Models/ServingPosition.php`
- Create: `app/Models/ServicePlanPosition.php`
- Create: `app/Models/VolunteerAssignment.php`
- Modify: `app/Models/Department.php` (add `servingPositions()` relation)
- Modify: `app/Models/User.php` (add `volunteerAssignments()` relation)
- Modify: `resources/js/types/index.ts` (add scheduling types)

- [ ] **Step 1: Create ServicePlan model**

```php
<?php
// app/Models/ServicePlan.php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePlan extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'published_by',
        'title', 'description', 'scheduled_at', 'location',
        'status', 'notes', 'published_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function planPositions(): HasMany
    {
        return $this->hasMany(ServicePlanPosition::class)->orderBy('sort_order');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())->orderBy('scheduled_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<', now())->orderByDesc('scheduled_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isPublished(): bool { return $this->status === 'published'; }
    public function isArchived(): bool  { return $this->status === 'archived'; }
}
```

- [ ] **Step 2: Create ServingPosition model**

```php
<?php
// app/Models/ServingPosition.php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServingPosition extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'church_id', 'department_id', 'name', 'description',
        'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function planPositions(): HasMany
    {
        return $this->hasMany(ServicePlanPosition::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 3: Create ServicePlanPosition model**

```php
<?php
// app/Models/ServicePlanPosition.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlanPosition extends Model
{
    protected $fillable = [
        'service_plan_id', 'serving_position_id', 'notes', 'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class, 'service_plan_id');
    }

    public function servingPosition(): BelongsTo
    {
        return $this->belongsTo(ServingPosition::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class, 'service_plan_position_id');
    }

    public function isFilled(): bool
    {
        return $this->assignments()
            ->where('status', '!=', 'declined')
            ->exists();
    }
}
```

- [ ] **Step 4: Create VolunteerAssignment model**

```php
<?php
// app/Models/VolunteerAssignment.php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerAssignment extends Model
{
    use BelongsToChurch;

    const STATUSES = ['pending', 'confirmed', 'declined'];

    protected $fillable = [
        'church_id', 'service_plan_position_id', 'user_id',
        'assigned_by', 'status', 'notes', 'responded_at',
    ];

    protected $casts = ['responded_at' => 'datetime'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function planPosition(): BelongsTo
    {
        return $this->belongsTo(ServicePlanPosition::class, 'service_plan_position_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopePending(Builder $query): Builder   { return $query->where('status', 'pending'); }
    public function scopeConfirmed(Builder $query): Builder { return $query->where('status', 'confirmed'); }
    public function scopeDeclined(Builder $query): Builder  { return $query->where('status', 'declined'); }
}
```

- [ ] **Step 5: Add `servingPositions()` to Department model**

Open `app/Models/Department.php`. Add this import at the top with the other `HasMany` imports:

```php
use App\Models\ServingPosition; // already in same namespace — no import needed
```

Add this method inside the `Department` class (after the existing `attendanceSessions()` method):

```php
public function servingPositions(): HasMany
{
    return $this->hasMany(ServingPosition::class);
}
```

- [ ] **Step 6: Add `volunteerAssignments()` to User model**

Open `app/Models/User.php`. Add this method after the existing `attendances()` method:

```php
public function volunteerAssignments(): HasMany
{
    return $this->hasMany(VolunteerAssignment::class, 'user_id');
}
```

- [ ] **Step 7: Add scheduling TypeScript types to `resources/js/types/index.ts`**

Append to the end of the file:

```typescript
// ── Volunteer Scheduling ──────────────────────────────────────────────────────

export type PlanStatus       = 'draft' | 'published' | 'archived'
export type AssignmentStatus = 'pending' | 'confirmed' | 'declined'

export interface ServicePlan {
    id: number
    title: string
    description: string | null
    scheduled_at: string
    scheduled_at_formatted: string
    scheduled_time: string
    location: string | null
    status: PlanStatus
    notes: string | null
    published_at: string | null
    created_at: string
    created_by: number | null
    published_by: number | null
    total_positions: number | null
    filled_positions: number | null
    fill_rate: number | null
    creator?: { id: number; name: string; avatar: string | null } | null
    plan_positions?: ServicePlanPosition[]
}

export interface ServingPosition {
    id: number
    church_id: number
    department_id: number
    name: string
    description: string | null
    sort_order: number
    is_active: boolean
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
}

export interface ServicePlanPosition {
    id: number
    service_plan_id: number
    serving_position_id: number
    notes: string | null
    sort_order: number
    is_filled: boolean
    serving_position?: ServingPosition | null
    assignments?: VolunteerAssignment[]
}

export interface VolunteerAssignment {
    id: number
    service_plan_position_id: number
    user_id: number
    assigned_by: number | null
    status: AssignmentStatus
    notes: string | null
    responded_at: string | null
    volunteer?: { id: number; name: string; avatar: string | null } | null
}
```

---

## Task 3: Permissions Config

**Files:**
- Modify: `config/permissions.php`

- [ ] **Step 1: Add scheduling permissions group**

Open `config/permissions.php`. Add the `scheduling` group inside `'permissions' => [...]` after the `'audit'` group (before the closing `],`):

```php
'scheduling' => [
    'scheduling.manage', // create/edit/publish plans, manage positions, assign volunteers
    'scheduling.view',   // view any service plan (not available to plain members)
],
```

- [ ] **Step 2: Add permissions to `church_admin` role**

Inside `'church_admin' => [...]`, add at the end (before the closing `],`):

```php
'scheduling.manage', 'scheduling.view',
```

- [ ] **Step 3: Add permissions to `coordinator` role**

Inside `'coordinator' => [...]`, add at the end (before the closing `],`):

```php
'scheduling.manage', 'scheduling.view',
```

- [ ] **Step 4: Add `scheduling.view` to `assistant_coordinator` role**

Inside `'assistant_coordinator' => [...]`, add at the end:

```php
'scheduling.view',
```

- [ ] **Step 5: Re-run the permissions seeder**

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Expected output:
```
RBAC seeded: N permissions across 5 roles.
```

---

## Task 4: Policies

**Files:**
- Create: `app/Policies/ServicePlanPolicy.php`
- Create: `app/Policies/ServingPositionPolicy.php`
- Create: `app/Policies/VolunteerAssignmentPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create ServicePlanPolicy**

```php
<?php
// app/Policies/ServicePlanPolicy.php

namespace App\Policies;

use App\Models\ServicePlan;
use App\Models\User;

class ServicePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, ServicePlan $plan): bool
    {
        return $user->can('scheduling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, ServicePlan $plan): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        // Coordinator: allowed if they created the plan OR their dept has positions on it
        if ($plan->created_by === $user->id) return true;

        $deptIds = $plan->planPositions()
            ->join('serving_positions', 'service_plan_positions.serving_position_id', '=', 'serving_positions.id')
            ->pluck('serving_positions.department_id')
            ->unique();

        return $user->departments()->whereIn('departments.id', $deptIds)->exists();
    }

    public function publish(User $user, ServicePlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function archive(User $user, ServicePlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function delete(User $user, ServicePlan $plan): bool
    {
        return $user->hasRole('church_admin') || $user->hasRole('super_admin');
    }
}
```

- [ ] **Step 2: Create ServingPositionPolicy**

```php
<?php
// app/Policies/ServingPositionPolicy.php

namespace App\Policies;

use App\Models\ServingPosition;
use App\Models\User;

class ServingPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, ServingPosition $position): bool
    {
        return $user->can('scheduling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, ServingPosition $position): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        return $user->departments()
            ->where('departments.id', $position->department_id)
            ->exists();
    }

    public function delete(User $user, ServingPosition $position): bool
    {
        return $this->update($user, $position);
    }
}
```

- [ ] **Step 3: Create VolunteerAssignmentPolicy**

```php
<?php
// app/Policies/VolunteerAssignmentPolicy.php

namespace App\Policies;

use App\Models\ServicePlanPosition;
use App\Models\VolunteerAssignment;
use App\Models\User;

class VolunteerAssignmentPolicy
{
    public function create(User $user, ServicePlanPosition $planPosition): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        $deptId = $planPosition->servingPosition?->department_id;

        return $deptId && $user->departments()
            ->where('departments.id', $deptId)
            ->exists();
    }

    public function delete(User $user, VolunteerAssignment $assignment): bool
    {
        if (! $user->can('scheduling.manage')) return false;
        if ($user->can('church.edit')) return true;

        $deptId = $assignment->planPosition?->servingPosition?->department_id;

        return $deptId && $user->departments()
            ->where('departments.id', $deptId)
            ->exists();
    }

    public function respond(User $user, VolunteerAssignment $assignment): bool
    {
        return $assignment->user_id === $user->id;
    }
}
```

- [ ] **Step 4: Register policies in AppServiceProvider**

Open `app/Providers/AppServiceProvider.php`. Add these three imports alongside the existing model imports at the top:

```php
use App\Models\ServicePlan;
use App\Models\ServingPosition;
use App\Models\VolunteerAssignment;
use App\Models\ServicePlanPosition;
use App\Policies\ServicePlanPolicy;
use App\Policies\ServingPositionPolicy;
use App\Policies\VolunteerAssignmentPolicy;
```

Add these three entries inside `protected $policies = [...]`:

```php
ServicePlan::class       => ServicePlanPolicy::class,
ServingPosition::class   => ServingPositionPolicy::class,
VolunteerAssignment::class => VolunteerAssignmentPolicy::class,
```

---

## Task 5: API Resources

**Files:**
- Create: `app/Http/Resources/ServingPositionResource.php`
- Create: `app/Http/Resources/VolunteerAssignmentResource.php`
- Create: `app/Http/Resources/ServicePlanPositionResource.php`
- Create: `app/Http/Resources/ServicePlanResource.php`

- [ ] **Step 1: Create ServingPositionResource**

```php
<?php
// app/Http/Resources/ServingPositionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServingPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'department_id' => $this->department_id,
            'name'        => $this->name,
            'description' => $this->description,
            'sort_order'  => $this->sort_order,
            'is_active'   => $this->is_active,
            'department'  => $this->whenLoaded('department', fn () => $this->department ? [
                'id'    => $this->department->id,
                'name'  => $this->department->name,
                'icon'  => $this->department->icon,
                'color' => $this->department->color,
            ] : null),
        ];
    }
}
```

- [ ] **Step 2: Create VolunteerAssignmentResource**

```php
<?php
// app/Http/Resources/VolunteerAssignmentResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'service_plan_position_id' => $this->service_plan_position_id,
            'user_id'                 => $this->user_id,
            'assigned_by'             => $this->assigned_by,
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'responded_at'            => $this->responded_at?->toJSON(),
            'volunteer' => $this->whenLoaded('volunteer', fn () => $this->volunteer ? [
                'id'     => $this->volunteer->id,
                'name'   => $this->volunteer->name,
                'avatar' => $this->volunteer->avatar,
            ] : null),
        ];
    }
}
```

- [ ] **Step 3: Create ServicePlanPositionResource**

```php
<?php
// app/Http/Resources/ServicePlanPositionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePlanPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'service_plan_id'     => $this->service_plan_id,
            'serving_position_id' => $this->serving_position_id,
            'notes'               => $this->notes,
            'sort_order'          => $this->sort_order,
            'is_filled' => $this->when(
                $this->relationLoaded('assignments'),
                fn () => $this->assignments->where('status', '!=', 'declined')->count() > 0,
                false,
            ),
            'serving_position' => $this->whenLoaded('servingPosition', fn () =>
                ServingPositionResource::make($this->servingPosition)
            ),
            'assignments' => $this->whenLoaded('assignments', fn () =>
                VolunteerAssignmentResource::collection($this->assignments)
            ),
        ];
    }
}
```

- [ ] **Step 4: Create ServicePlanResource**

```php
<?php
// app/Http/Resources/ServicePlanResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'created_by'  => $this->created_by,
            'published_by' => $this->published_by,

            // ISO 8601 dates
            'scheduled_at' => $this->scheduled_at?->toJSON(),
            'published_at' => $this->published_at?->toJSON(),
            'created_at'   => $this->created_at?->toJSON(),

            // Pre-formatted for display
            'scheduled_at_formatted' => $this->scheduled_at?->format('D, j M Y'),
            'scheduled_time'         => $this->scheduled_at?->format('g:i A'),

            // Stats — available when plan_positions and their assignments are loaded
            'total_positions'  => $this->total_positions  ?? null,
            'filled_positions' => $this->filled_positions ?? null,
            'fill_rate' => ($this->total_positions ?? 0) > 0
                ? (int) round(($this->filled_positions / $this->total_positions) * 100)
                : 0,

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id'     => $this->creator->id,
                'name'   => $this->creator->name,
                'avatar' => $this->creator->avatar,
            ] : null),

            'plan_positions' => $this->whenLoaded('planPositions', fn () =>
                ServicePlanPositionResource::collection($this->planPositions)
            ),
        ];
    }
}
```

---

## Task 6: Notification Classes

**Files:**
- Create: `app/Notifications/VolunteerAssigned.php`
- Create: `app/Notifications/VolunteerRemoved.php`
- Create: `app/Notifications/SchedulePublished.php`
- Create: `app/Notifications/VolunteerDeclined.php`

All use database channel only (email is Phase 2).

- [ ] **Step 1: Create VolunteerAssigned notification**

```php
<?php
// app/Notifications/VolunteerAssigned.php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VolunteerAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $plan     = $this->assignment->planPosition->plan;
        $position = $this->assignment->planPosition->servingPosition;

        return [
            'type'         => 'scheduling.assigned',
            'plan_id'      => $plan->id,
            'plan_title'   => $plan->title,
            'position'     => $position?->name,
            'scheduled_at' => $plan->scheduled_at?->toISOString(),
            'url'          => '/dashboard/scheduling/my-schedule',
        ];
    }
}
```

- [ ] **Step 2: Create VolunteerRemoved notification**

```php
<?php
// app/Notifications/VolunteerRemoved.php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VolunteerRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $plan     = $this->assignment->planPosition->plan;
        $position = $this->assignment->planPosition->servingPosition;

        return [
            'type'         => 'scheduling.removed',
            'plan_id'      => $plan->id,
            'plan_title'   => $plan->title,
            'position'     => $position?->name,
            'scheduled_at' => $plan->scheduled_at?->toISOString(),
            'url'          => '/dashboard/scheduling/my-schedule',
        ];
    }
}
```

- [ ] **Step 3: Create SchedulePublished notification**

```php
<?php
// app/Notifications/SchedulePublished.php

namespace App\Notifications;

use App\Models\ServicePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SchedulePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ServicePlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'scheduling.published',
            'plan_id'      => $this->plan->id,
            'plan_title'   => $this->plan->title,
            'scheduled_at' => $this->plan->scheduled_at?->toISOString(),
            'url'          => '/dashboard/scheduling/my-schedule',
        ];
    }
}
```

- [ ] **Step 4: Create VolunteerDeclined notification**

```php
<?php
// app/Notifications/VolunteerDeclined.php

namespace App\Notifications;

use App\Models\VolunteerAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VolunteerDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly VolunteerAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $plan      = $this->assignment->planPosition->plan;
        $position  = $this->assignment->planPosition->servingPosition;
        $volunteer = $this->assignment->volunteer;

        return [
            'type'           => 'scheduling.declined',
            'plan_id'        => $plan->id,
            'plan_title'     => $plan->title,
            'position'       => $position?->name,
            'volunteer_name' => $volunteer?->name,
            'scheduled_at'   => $plan->scheduled_at?->toISOString(),
            'url'            => '/dashboard/scheduling/plans/' . $plan->id,
        ];
    }
}
```

---

## Task 7: ServingPositionController + Feature Test

**Files:**
- Create: `app/Http/Controllers/Dashboard/ServingPositionController.php`
- Create: `tests/Feature/Scheduling/ServingPositionControllerTest.php`

- [ ] **Step 1: Write failing feature test**

```php
<?php
// tests/Feature/Scheduling/ServingPositionControllerTest.php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServingPosition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServingPositionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $member;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin  = User::factory()->create(['church_id' => $this->church->id]);
        $this->member = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');
        $this->member->assignRole('member');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media Team',
            'slug'      => 'media-team',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_positions(): void
    {
        ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/scheduling/positions');

        $response->assertOk();
    }

    public function test_admin_can_create_position(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/positions', [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
                'description'   => 'Operates the main camera',
                'sort_order'    => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('serving_positions', [
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);
    }

    public function test_admin_can_update_position(): void
    {
        $position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Old Name',
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/scheduling/positions/{$position->id}", [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('serving_positions', ['id' => $position->id, 'name' => 'Camera Operator']);
    }

    public function test_admin_can_delete_position(): void
    {
        $position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/positions/{$position->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('serving_positions', ['id' => $position->id]);
    }

    public function test_member_cannot_manage_positions(): void
    {
        $response = $this->actingAs($this->member)
            ->post('/dashboard/scheduling/positions', [
                'department_id' => $this->dept->id,
                'name'          => 'Camera Operator',
            ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to confirm it fails (routes don't exist yet)**

```bash
php artisan test tests/Feature/Scheduling/ServingPositionControllerTest.php
```

Expected: All tests fail with 404 (routes not registered yet).

- [ ] **Step 3: Create ServingPositionController**

```php
<?php
// app/Http/Controllers/Dashboard/ServingPositionController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServingPositionResource;
use App\Models\ServingPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServingPositionController extends Controller
{
    use ResolvesChurchData;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServingPosition::class);

        $positions = ServingPosition::with('department:id,name,icon,color')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Dashboard/Scheduling/Positions/Index', [
            'positions'   => ServingPositionResource::collection($positions),
            'departments' => $this->activeDepartments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ServingPosition::class);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ]);

        ServingPosition::create([
            ...$validated,
            'church_id'  => $this->resolvedChurchId(),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Position created.');
    }

    public function update(Request $request, ServingPosition $pos): RedirectResponse
    {
        $this->authorize('update', $pos);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $pos->update($validated);

        return back()->with('success', 'Position updated.');
    }

    public function destroy(ServingPosition $pos): RedirectResponse
    {
        $this->authorize('delete', $pos);

        $pos->delete();

        return back()->with('success', 'Position deleted.');
    }
}
```

- [ ] **Step 4: Add routes (minimal, just to make tests pass — full route group added in Task 11)**

Skip this step — routes will be added in Task 11. Re-run tests after Task 11.

---

## Task 8: ServicePlanController + Feature Test

**Files:**
- Create: `app/Http/Controllers/Dashboard/ServicePlanController.php`
- Create: `tests/Feature/Scheduling/ServicePlanControllerTest.php`

- [ ] **Step 1: Write failing feature test**

```php
<?php
// tests/Feature/Scheduling/ServicePlanControllerTest.php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServicePlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private Department $dept;
    private ServingPosition $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church   = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin    = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');

        $this->dept = Department::create([
            'church_id' => $this->church->id,
            'name'      => 'Media',
            'slug'      => 'media',
            'is_active' => true,
        ]);

        $this->position = ServingPosition::create([
            'church_id'     => $this->church->id,
            'department_id' => $this->dept->id,
            'name'          => 'Camera Operator',
        ]);
    }

    private function planData(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Sunday Service',
            'description'  => null,
            'scheduled_at' => '2026-07-06 09:00:00',
            'location'     => 'Main Hall',
            'notes'        => null,
        ], $overrides);
    }

    public function test_admin_can_list_plans(): void
    {
        ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard/scheduling/plans');

        $response->assertOk();
    }

    public function test_admin_can_create_a_plan(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/plans', $this->planData());

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', [
            'church_id' => $this->church->id,
            'title'     => 'Sunday Service',
            'status'    => 'draft',
        ]);
    }

    public function test_admin_can_show_a_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/dashboard/scheduling/plans/{$plan->id}");

        $response->assertOk();
    }

    public function test_admin_can_update_a_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Old Title',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/scheduling/plans/{$plan->id}", $this->planData(['title' => 'New Title']));

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', ['id' => $plan->id, 'title' => 'New Title']);
    }

    public function test_admin_can_delete_a_draft_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/plans/{$plan->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('service_plans', ['id' => $plan->id]);
    }

    public function test_admin_can_publish_a_plan_and_volunteers_are_notified(): void
    {
        Notification::fake();

        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $volunteer = User::factory()->create(['church_id' => $this->church->id]);
        $volunteer->assignRole('member');

        $planPos = ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);

        \App\Models\VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $planPos->id,
            'user_id'                 => $volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch("/dashboard/scheduling/plans/{$plan->id}/publish");

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plans', ['id' => $plan->id, 'status' => 'published']);
        Notification::assertSentTo($volunteer, \App\Notifications\SchedulePublished::class);
    }

    public function test_admin_can_add_position_to_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/scheduling/plans/{$plan->id}/positions", [
                'serving_position_id' => $this->position->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('service_plan_positions', [
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);
    }

    public function test_admin_can_remove_position_from_plan(): void
    {
        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'title'        => 'Sunday Service',
            'scheduled_at' => now()->addWeek(),
            'status'       => 'draft',
            'created_by'   => $this->admin->id,
        ]);

        $planPos = ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $this->position->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/plans/{$plan->id}/positions/{$planPos->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('service_plan_positions', ['id' => $planPos->id]);
    }
}
```

- [ ] **Step 2: Create ServicePlanController**

```php
<?php
// app/Http/Controllers/Dashboard/ServicePlanController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePlanResource;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Notifications\SchedulePublished;
use App\Notifications\VolunteerRemoved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicePlanController extends Controller
{
    use ResolvesChurchData;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServicePlan::class);

        $tab = $request->input('tab', 'upcoming');

        $query = ServicePlan::with('creator:id,name,avatar')
            ->withCount('planPositions as total_positions')
            ->withCount(['planPositions as filled_positions' => fn ($q) =>
                $q->whereHas('assignments', fn ($a) => $a->where('status', '!=', 'declined'))
            ])
            ->orderByDesc('scheduled_at');

        if ($tab === 'draft')    $query->draft();
        elseif ($tab === 'archived') $query->archived();
        elseif ($tab === 'upcoming') $query->published()->upcoming();
        elseif ($tab === 'past')  $query->published()->past();

        $plans = $query->paginate(20)->through(fn ($p) =>
            ServicePlanResource::make($p)->toArray($request)
        );

        return Inertia::render('Dashboard/Scheduling/Plans/Index', [
            'plans'   => $plans,
            'filters' => $request->only('tab'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ServicePlan::class);

        return Inertia::render('Dashboard/Scheduling/Plans/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ServicePlan::class);

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'location'     => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = ServicePlan::create([
            ...$validated,
            'church_id'  => $this->resolvedChurchId(),
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        return redirect()
            ->route('dashboard.scheduling.plans.show', $plan)
            ->with('success', "\"{$plan->title}\" created.");
    }

    public function show(Request $request, ServicePlan $plan): Response
    {
        $this->authorize('view', $plan);

        $plan->load([
            'creator:id,name,avatar',
            'planPositions.servingPosition.department:id,name,icon,color',
            'planPositions.assignments.volunteer:id,name,avatar',
        ]);

        $availablePositions = ServingPosition::with('department:id,name,icon,color')
            ->active()
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        $user = $request->user();

        return Inertia::render('Dashboard/Scheduling/Plans/Show', [
            'plan'               => ServicePlanResource::make($plan),
            'members'            => $this->churchMembers(),
            'availablePositions' => $availablePositions->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'department'  => ['id' => $p->department->id, 'name' => $p->department->name],
            ]),
            'canManage'  => $user->can('update', $plan),
            'canPublish' => $user->can('publish', $plan),
            'canDelete'  => $user->can('delete', $plan),
        ]);
    }

    public function update(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'location'     => ['nullable', 'string', 'max:200'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $plan->update($validated);

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(ServicePlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('dashboard.scheduling.plans.index')
            ->with('success', "\"{$plan->title}\" deleted.");
    }

    public function publish(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        abort_if(! $plan->isDraft(), 422, 'Only draft plans can be published.');

        $plan->update([
            'status'       => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        // Notify all assigned (non-declined) volunteers
        $volunteers = $plan->planPositions()
            ->with('assignments.volunteer')
            ->get()
            ->flatMap(fn ($pp) => $pp->assignments)
            ->where('status', '!=', 'declined')
            ->pluck('volunteer')
            ->filter()
            ->unique('id');

        foreach ($volunteers as $volunteer) {
            $volunteer->notify(new SchedulePublished($plan));
        }

        return back()->with('success', 'Plan published.');
    }

    public function archive(ServicePlan $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        abort_if($plan->isArchived(), 422, 'Plan is already archived.');

        $plan->update(['status' => 'archived']);

        return back()->with('success', 'Plan archived.');
    }

    public function addPosition(Request $request, ServicePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        abort_if($plan->isArchived(), 422, 'Cannot modify an archived plan.');

        $validated = $request->validate([
            'serving_position_id' => ['required', 'integer', 'exists:serving_positions,id'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        // Ensure the serving position belongs to this church
        $servingPosition = ServingPosition::findOrFail($validated['serving_position_id']);
        abort_if($servingPosition->church_id !== $this->resolvedChurchId(), 403);

        ServicePlanPosition::create([
            'service_plan_id'     => $plan->id,
            'serving_position_id' => $validated['serving_position_id'],
            'notes'               => $validated['notes'] ?? null,
            'sort_order'          => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Position added to plan.');
    }

    public function removePosition(Request $request, ServicePlan $plan, ServicePlanPosition $pp): RedirectResponse
    {
        $this->authorize('update', $plan);

        abort_if($plan->isArchived(), 422, 'Cannot modify an archived plan.');
        abort_if($pp->service_plan_id !== $plan->id, 404);

        // Notify any assigned volunteers that their slot is removed
        $pp->load('assignments.volunteer', 'servingPosition', 'plan');
        foreach ($pp->assignments()->where('status', '!=', 'declined')->with('volunteer')->get() as $a) {
            $a->volunteer?->notify(new VolunteerRemoved($a));
        }

        $pp->delete();

        return back()->with('success', 'Position removed from plan.');
    }
}
```

- [ ] **Step 3: Run test (will still fail until routes added in Task 11)**

Tests will pass after Task 11 completes route registration.

---

## Task 9: AssignmentController + Feature Test

**Files:**
- Create: `app/Http/Controllers/Dashboard/AssignmentController.php`
- Create: `tests/Feature/Scheduling/AssignmentControllerTest.php`

- [ ] **Step 1: Write failing feature test**

```php
<?php
// tests/Feature/Scheduling/AssignmentControllerTest.php

namespace Tests\Feature\Scheduling;

use App\Models\Church;
use App\Models\Department;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Models\User;
use App\Models\VolunteerAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;
    private User $admin;
    private User $volunteer;
    private ServicePlan $plan;
    private ServicePlanPosition $planPos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->church    = Church::create(['name' => 'Test Church', 'slug' => 'test-' . uniqid()]);
        $this->admin     = User::factory()->create(['church_id' => $this->church->id]);
        $this->volunteer = User::factory()->create(['church_id' => $this->church->id]);
        $this->admin->assignRole('church_admin');
        $this->volunteer->assignRole('member');

        $dept = Department::create([
            'church_id' => $this->church->id, 'name' => 'Media', 'slug' => 'media', 'is_active' => true,
        ]);
        $servingPos = ServingPosition::create([
            'church_id' => $this->church->id, 'department_id' => $dept->id, 'name' => 'Camera',
        ]);
        $this->plan = ServicePlan::create([
            'church_id' => $this->church->id, 'title' => 'Sunday', 'scheduled_at' => now()->addWeek(),
            'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
        $this->planPos = ServicePlanPosition::create([
            'service_plan_id' => $this->plan->id, 'serving_position_id' => $servingPos->id,
        ]);
    }

    public function test_admin_can_assign_a_volunteer(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', [
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                  => $this->volunteer->id,
            'status'                   => 'pending',
        ]);
        Notification::assertSentTo($this->volunteer, \App\Notifications\VolunteerAssigned::class);
    }

    public function test_cannot_assign_same_volunteer_twice(): void
    {
        VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/scheduling/assignments', [
                'service_plan_position_id' => $this->planPos->id,
                'user_id'                  => $this->volunteer->id,
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_admin_can_remove_an_assignment(): void
    {
        Notification::fake();

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete("/dashboard/scheduling/assignments/{$assignment->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('volunteer_assignments', ['id' => $assignment->id]);
        Notification::assertSentTo($this->volunteer, \App\Notifications\VolunteerRemoved::class);
    }

    public function test_volunteer_can_confirm_assignment(): void
    {
        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', [
            'id' => $assignment->id, 'status' => 'confirmed',
        ]);
    }

    public function test_volunteer_decline_notifies_plan_creator(): void
    {
        Notification::fake();

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($this->volunteer)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'declined',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('volunteer_assignments', ['id' => $assignment->id, 'status' => 'declined']);
        Notification::assertSentTo($this->admin, \App\Notifications\VolunteerDeclined::class);
    }

    public function test_volunteer_cannot_respond_for_another_persons_assignment(): void
    {
        $other = User::factory()->create(['church_id' => $this->church->id]);
        $other->assignRole('member');

        $assignment = VolunteerAssignment::create([
            'church_id'               => $this->church->id,
            'service_plan_position_id' => $this->planPos->id,
            'user_id'                 => $this->volunteer->id,
            'assigned_by'             => $this->admin->id,
            'status'                  => 'pending',
        ]);

        $response = $this->actingAs($other)
            ->patch("/dashboard/scheduling/assignments/{$assignment->id}/respond", [
                'status' => 'confirmed',
            ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Create AssignmentController**

```php
<?php
// app/Http/Controllers/Dashboard/AssignmentController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ServicePlanPosition;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Notifications\VolunteerAssigned;
use App\Notifications\VolunteerDeclined;
use App\Notifications\VolunteerRemoved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_plan_position_id' => ['required', 'integer', 'exists:service_plan_positions,id'],
            'user_id'                  => ['required', 'integer', 'exists:users,id'],
            'notes'                    => ['nullable', 'string', 'max:500'],
        ]);

        $planPosition = ServicePlanPosition::with('plan', 'servingPosition')->findOrFail($validated['service_plan_position_id']);

        $this->authorize('create', [VolunteerAssignment::class, $planPosition]);

        abort_if($planPosition->plan->isArchived(), 422, 'Cannot assign to an archived plan.');

        // Unique check — give a user-friendly error instead of letting the DB throw
        $alreadyExists = VolunteerAssignment::where('service_plan_position_id', $planPosition->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors(['user_id' => 'This volunteer is already assigned to this slot.']);
        }

        $volunteer   = User::findOrFail($validated['user_id']);
        $assignment  = VolunteerAssignment::create([
            'church_id'               => $planPosition->plan->church_id,
            'service_plan_position_id' => $planPosition->id,
            'user_id'                 => $validated['user_id'],
            'assigned_by'             => $request->user()->id,
            'status'                  => 'pending',
            'notes'                   => $validated['notes'] ?? null,
        ]);

        // Notify if plan is already published (late assignment)
        if ($planPosition->plan->isPublished()) {
            $assignment->load('planPosition.plan', 'planPosition.servingPosition');
            $volunteer->notify(new VolunteerAssigned($assignment));
        } elseif ($planPosition->plan->isDraft()) {
            // No notification during draft — volunteers notified on publish
        }

        return back()->with('success', 'Volunteer assigned.');
    }

    public function destroy(Request $request, VolunteerAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $assignment->load('planPosition.plan', 'planPosition.servingPosition', 'volunteer');

        $volunteer = $assignment->volunteer;
        $assignment->delete();

        $volunteer?->notify(new VolunteerRemoved($assignment));

        return back()->with('success', 'Assignment removed.');
    }

    public function respond(Request $request, VolunteerAssignment $assignment): RedirectResponse
    {
        $this->authorize('respond', $assignment);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);

        $assignment->update([
            'status'       => $validated['status'],
            'responded_at' => now(),
        ]);

        if ($validated['status'] === 'declined') {
            $assignment->load('planPosition.plan', 'planPosition.servingPosition', 'volunteer');

            // Notify plan creator
            $creator = $assignment->planPosition->plan->creator;
            $creator?->notify(new VolunteerDeclined($assignment));

            // Notify dept coordinator (if different from creator)
            $dept = $assignment->planPosition->servingPosition?->department;
            $dept?->load('coordinator');
            if ($dept?->coordinator_id && $dept->coordinator_id !== $creator?->id) {
                $dept->coordinator?->notify(new VolunteerDeclined($assignment));
            }
        }

        return back()->with('success', 'Response saved.');
    }
}
```

---

## Task 10: SchedulingController

**Files:**
- Create: `app/Http/Controllers/Dashboard/SchedulingController.php`

- [ ] **Step 1: Create SchedulingController**

```php
<?php
// app/Http/Controllers/Dashboard/SchedulingController.php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePlanResource;
use App\Http\Resources\VolunteerAssignmentResource;
use App\Models\ServicePlan;
use App\Models\VolunteerAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $churchId = $request->user()->church_id;

        $stats = [
            'total'     => ServicePlan::count(),
            'published' => ServicePlan::published()->count(),
            'draft'     => ServicePlan::draft()->count(),
            'archived'  => ServicePlan::archived()->count(),
        ];

        $upcoming = ServicePlan::with('creator:id,name,avatar')
            ->withCount('planPositions as total_positions')
            ->withCount(['planPositions as filled_positions' => fn ($q) =>
                $q->whereHas('assignments', fn ($a) => $a->where('status', '!=', 'declined'))
            ])
            ->published()
            ->upcoming()
            ->limit(6)
            ->get()
            ->map(fn ($p) => ServicePlanResource::make($p)->toArray($request));

        return Inertia::render('Dashboard/Scheduling/Dashboard', [
            'stats'    => $stats,
            'upcoming' => $upcoming,
        ]);
    }

    public function mySchedule(Request $request): Response
    {
        $user = $request->user();

        $assignments = VolunteerAssignment::where('user_id', $user->id)
            ->where('status', '!=', 'declined')
            ->with([
                'planPosition.plan',
                'planPosition.servingPosition.department:id,name,icon,color',
            ])
            ->whereHas('planPosition.plan', fn ($q) =>
                $q->where('status', 'published')
                  ->where('scheduled_at', '>=', now()->startOfDay())
            )
            ->get()
            ->sortBy('planPosition.plan.scheduled_at')
            ->map(fn ($a) => [
                'id'     => $a->id,
                'status' => $a->status,
                'plan'   => [
                    'id'                    => $a->planPosition->plan->id,
                    'title'                 => $a->planPosition->plan->title,
                    'scheduled_at'          => $a->planPosition->plan->scheduled_at?->toJSON(),
                    'scheduled_at_formatted' => $a->planPosition->plan->scheduled_at?->format('D, j M Y'),
                    'scheduled_time'        => $a->planPosition->plan->scheduled_at?->format('g:i A'),
                    'location'              => $a->planPosition->plan->location,
                ],
                'position' => [
                    'name'       => $a->planPosition->servingPosition?->name,
                    'department' => $a->planPosition->servingPosition?->department ? [
                        'id'    => $a->planPosition->servingPosition->department->id,
                        'name'  => $a->planPosition->servingPosition->department->name,
                        'icon'  => $a->planPosition->servingPosition->department->icon,
                        'color' => $a->planPosition->servingPosition->department->color,
                    ] : null,
                ],
            ])
            ->values();

        return Inertia::render('Dashboard/Scheduling/MySchedule', [
            'assignments' => $assignments,
        ]);
    }
}
```

---

## Task 11: Routes + Sidebar Nav

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/DashboardLayout.vue`

- [ ] **Step 1: Add scheduling routes to web.php**

Open `routes/web.php`. Add these imports at the top with the other Dashboard controller imports:

```php
use App\Http\Controllers\Dashboard\SchedulingController;
use App\Http\Controllers\Dashboard\ServicePlanController;
use App\Http\Controllers\Dashboard\ServingPositionController;
use App\Http\Controllers\Dashboard\AssignmentController;
```

Inside the `Route::middleware(['auth'])->group(function () {` block, add the scheduling group after the existing routes (e.g. after the Attendance block):

```php
// Scheduling
Route::prefix('dashboard/scheduling')->name('dashboard.scheduling.')->group(function () {

    Route::get('/',            [SchedulingController::class, 'dashboard'])  ->name('dashboard');
    Route::get('/my-schedule', [SchedulingController::class, 'mySchedule']) ->name('my-schedule');

    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/',                           [ServicePlanController::class, 'index'])          ->name('index');
        Route::get('/create',                     [ServicePlanController::class, 'create'])         ->name('create');
        Route::post('/',                          [ServicePlanController::class, 'store'])          ->name('store');
        Route::get('/{plan}',                     [ServicePlanController::class, 'show'])           ->name('show');
        Route::put('/{plan}',                     [ServicePlanController::class, 'update'])         ->name('update');
        Route::delete('/{plan}',                  [ServicePlanController::class, 'destroy'])        ->name('destroy');
        Route::patch('/{plan}/publish',           [ServicePlanController::class, 'publish'])        ->name('publish');
        Route::patch('/{plan}/archive',           [ServicePlanController::class, 'archive'])        ->name('archive');
        Route::post('/{plan}/positions',          [ServicePlanController::class, 'addPosition'])    ->name('positions.add');
        Route::delete('/{plan}/positions/{pp}',   [ServicePlanController::class, 'removePosition']) ->name('positions.remove');
    });

    Route::prefix('positions')->name('positions.')->group(function () {
        Route::get('/',         [ServingPositionController::class, 'index'])   ->name('index');
        Route::post('/',        [ServingPositionController::class, 'store'])   ->name('store');
        Route::put('/{pos}',    [ServingPositionController::class, 'update'])  ->name('update');
        Route::delete('/{pos}', [ServingPositionController::class, 'destroy']) ->name('destroy');
    });

    Route::prefix('assignments')->name('assignments.')->group(function () {
        Route::post('/',                       [AssignmentController::class, 'store'])   ->name('store');
        Route::delete('/{assignment}',         [AssignmentController::class, 'destroy']) ->name('destroy');
        Route::patch('/{assignment}/respond',  [AssignmentController::class, 'respond']) ->name('respond');
    });
});
```

- [ ] **Step 2: Verify routes are registered**

```bash
php artisan route:list --path=scheduling --columns=method,uri,name
```

Expected: Table showing all 14 scheduling routes with their names.

- [ ] **Step 3: Add scheduling nav item to DashboardLayout.vue**

Open `resources/js/Layouts/DashboardLayout.vue`.

In the `import { ... } from 'lucide-vue-next'` line, add `ClipboardList` to the import list.

In the `navGroups` computed array, find the `'Work'` group and add the Scheduling item after `Tasks`:

```typescript
{ label: 'Scheduling', href: '/dashboard/scheduling', icon: ClipboardList, perm: 'scheduling.view' },
```

- [ ] **Step 4: Run all three feature tests**

```bash
php artisan test tests/Feature/Scheduling/
```

Expected: All tests pass (green).

---

## Task 12: StatusBadge.vue + PlanCard.vue

**Files:**
- Create: `resources/js/Components/Scheduling/StatusBadge.vue`
- Create: `resources/js/Components/Scheduling/PlanCard.vue`

- [ ] **Step 1: Create StatusBadge.vue**

```vue
<!-- resources/js/Components/Scheduling/StatusBadge.vue -->
<script setup lang="ts">
const props = defineProps<{
    status: string
}>()

const colors: Record<string, string> = {
    // Plan statuses
    draft:     'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived:  'bg-slate-100 text-slate-500',
    // Assignment statuses
    pending:   'bg-yellow-100 text-yellow-700',
    confirmed: 'bg-green-100 text-green-700',
    declined:  'bg-red-100 text-red-600',
}

const labels: Record<string, string> = {
    draft:     'Draft',
    published: 'Published',
    archived:  'Archived',
    pending:   'Pending',
    confirmed: 'Confirmed',
    declined:  'Declined',
}
</script>

<template>
    <span
        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', colors[status] ?? 'bg-gray-100 text-gray-600']"
    >
        {{ labels[status] ?? status }}
    </span>
</template>
```

- [ ] **Step 2: Create PlanCard.vue**

```vue
<!-- resources/js/Components/Scheduling/PlanCard.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarDays, MapPin, Users } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { ServicePlan } from '@/types'

defineProps<{ plan: ServicePlan }>()
</script>

<template>
    <Link
        :href="`/dashboard/scheduling/plans/${plan.id}`"
        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md hover:border-indigo-300"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900">{{ plan.title }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ plan.scheduled_at_formatted }}
                        <span v-if="plan.scheduled_time" class="text-gray-400">· {{ plan.scheduled_time }}</span>
                    </span>
                    <span v-if="plan.location" class="flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" />
                        {{ plan.location }}
                    </span>
                </div>
            </div>
            <StatusBadge :status="plan.status" />
        </div>

        <!-- Fill rate bar -->
        <div v-if="plan.total_positions" class="mt-4">
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span class="flex items-center gap-1">
                    <Users class="h-3.5 w-3.5" />
                    {{ plan.filled_positions }} / {{ plan.total_positions }} filled
                </span>
                <span>{{ plan.fill_rate }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-100">
                <div
                    class="h-1.5 rounded-full bg-indigo-500 transition-all"
                    :style="{ width: `${plan.fill_rate}%` }"
                />
            </div>
        </div>
        <p v-else class="mt-3 text-xs text-gray-400">No positions added yet</p>
    </Link>
</template>
```

---

## Task 13: AssignmentCard.vue + AssignDrawer.vue

**Files:**
- Create: `resources/js/Components/Scheduling/AssignmentCard.vue`
- Create: `resources/js/Components/Scheduling/AssignDrawer.vue`

- [ ] **Step 1: Create AssignmentCard.vue**

```vue
<!-- resources/js/Components/Scheduling/AssignmentCard.vue -->
<script setup lang="ts">
import { X } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { VolunteerAssignment } from '@/types'

defineProps<{
    assignment: VolunteerAssignment
    canManage: boolean
}>()

defineEmits<{ remove: [assignment: VolunteerAssignment] }>()
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
        <!-- Avatar -->
        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
            <img
                v-if="assignment.volunteer?.avatar"
                :src="assignment.volunteer.avatar"
                :alt="assignment.volunteer.name"
                class="h-8 w-8 rounded-full object-cover"
            />
            <span v-else>{{ assignment.volunteer?.name?.slice(0, 1).toUpperCase() ?? '?' }}</span>
        </div>

        <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800">
            {{ assignment.volunteer?.name ?? 'Unknown' }}
        </span>

        <StatusBadge :status="assignment.status" />

        <button
            v-if="canManage"
            type="button"
            class="ml-1 rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500"
            title="Remove assignment"
            @click="$emit('remove', assignment)"
        >
            <X class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
```

- [ ] **Step 2: Create AssignDrawer.vue**

```vue
<!-- resources/js/Components/Scheduling/AssignDrawer.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { X, Search, UserCheck } from 'lucide-vue-next'
import type { VolunteerAssignment } from '@/types'

interface Member { id: number; name: string; avatar: string | null }

const props = defineProps<{
    open: boolean
    planPositionId: number
    members: Member[]
    existingAssignments: VolunteerAssignment[]
}>()

const emit = defineEmits<{
    close: []
    assign: [planPositionId: number, userId: number]
}>()

const search = ref('')

const filteredMembers = computed(() => {
    const q = search.value.toLowerCase()
    return props.members.filter(m => m.name.toLowerCase().includes(q))
})

function isAssigned(memberId: number): boolean {
    return props.existingAssignments.some(
        a => a.user_id === memberId && a.status !== 'declined'
    )
}

function initials(name: string): string {
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}
</script>

<template>
    <!-- Backdrop -->
    <Transition name="fade">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/30"
            @click="emit('close')"
        />
    </Transition>

    <!-- Drawer panel -->
    <Transition name="slide-right">
        <div
            v-if="open"
            class="fixed inset-y-0 right-0 z-50 flex w-80 flex-col bg-white shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Assign Volunteer</h2>
                <button @click="emit('close')" class="rounded p-1 text-gray-400 hover:text-gray-600">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Search -->
            <div class="border-b border-gray-100 px-4 py-2">
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5">
                    <Search class="h-3.5 w-3.5 text-gray-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search members…"
                        class="flex-1 bg-transparent text-sm outline-none placeholder:text-gray-400"
                    />
                </div>
            </div>

            <!-- Member list -->
            <ul class="flex-1 overflow-y-auto py-1">
                <li
                    v-for="member in filteredMembers"
                    :key="member.id"
                    class="flex items-center gap-3 px-4 py-2.5"
                    :class="isAssigned(member.id) ? 'opacity-50' : 'cursor-pointer hover:bg-gray-50'"
                    @click="!isAssigned(member.id) && emit('assign', planPositionId, member.id)"
                >
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                        <img v-if="member.avatar" :src="member.avatar" :alt="member.name" class="h-8 w-8 rounded-full object-cover" />
                        <span v-else>{{ initials(member.name) }}</span>
                    </div>
                    <span class="flex-1 text-sm text-gray-800">{{ member.name }}</span>
                    <UserCheck v-if="isAssigned(member.id)" class="h-4 w-4 text-green-500" />
                </li>
                <li v-if="filteredMembers.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    No members found
                </li>
            </ul>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.2s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>
```

---

## Task 14: Plans/Index.vue + Plans/Create.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Scheduling/Plans/Index.vue`
- Create: `resources/js/Pages/Dashboard/Scheduling/Plans/Create.vue`

- [ ] **Step 1: Create Plans/Index.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Index.vue -->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import PlanCard from '@/Components/Scheduling/PlanCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { Plus, ClipboardList } from 'lucide-vue-next'
import type { ServicePlan } from '@/types'

interface Props {
    plans: {
        data: ServicePlan[]
        links: { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page: number
        total: number
    }
    filters: { tab?: string }
}

const props = defineProps<Props>()
const auth = useAuthStore()

const tabs = [
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'draft',    label: 'Drafts' },
    { key: 'past',     label: 'Past' },
    { key: 'archived', label: 'Archived' },
]

const currentTab = ref(props.filters.tab ?? 'upcoming')

watch(currentTab, (tab) => {
    router.get('/dashboard/scheduling/plans', { tab }, { preserveState: true, replace: true })
})
</script>

<template>
    <DashboardLayout title="Service Plans">
        <PageHeader title="Service Plans">
            <template #actions>
                <AppButton
                    v-if="auth.can('scheduling.manage')"
                    :href="'/dashboard/scheduling/plans/create'"
                    variant="primary"
                >
                    <Plus class="h-4 w-4" />
                    New Plan
                </AppButton>
            </template>
        </PageHeader>

        <!-- Tabs -->
        <div class="mb-6 flex gap-1 border-b border-gray-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'px-4 py-2 text-sm font-medium transition',
                    currentTab === tab.key
                        ? 'border-b-2 border-indigo-600 text-indigo-600'
                        : 'text-gray-500 hover:text-gray-700',
                ]"
                @click="currentTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Plans grid -->
        <div v-if="plans.data.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <PlanCard v-for="plan in plans.data" :key="plan.id" :plan="plan" />
        </div>

        <EmptyState
            v-else
            :icon="ClipboardList"
            title="No plans yet"
            description="Create a service plan to start scheduling volunteers."
        >
            <AppButton
                v-if="auth.can('scheduling.manage')"
                :href="'/dashboard/scheduling/plans/create'"
                variant="primary"
            >
                <Plus class="h-4 w-4" />
                New Plan
            </AppButton>
        </EmptyState>

        <AppPagination v-if="plans.last_page > 1" :links="plans.links" class="mt-6" />
    </DashboardLayout>
</template>
```

- [ ] **Step 2: Create Plans/Create.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Create.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ArrowLeft } from 'lucide-vue-next'

const form = useForm({
    title:        '',
    description:  '',
    scheduled_at: '',
    location:     '',
    notes:        '',
})

function submit() {
    form.post('/dashboard/scheduling/plans')
}
</script>

<template>
    <DashboardLayout title="New Service Plan">
        <PageHeader title="New Service Plan">
            <template #actions>
                <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Plans
                </AppButton>
            </template>
        </PageHeader>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

                <!-- Title -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Sunday Morning Service"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.title }"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- Date & Time -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Date & Time <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.scheduled_at }"
                        required
                    />
                    <p v-if="form.errors.scheduled_at" class="mt-1 text-xs text-red-500">{{ form.errors.scheduled_at }}</p>
                </div>

                <!-- Location -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Location</label>
                    <input
                        v-model="form.location"
                        type="text"
                        placeholder="Main Sanctuary"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Brief description of this service…"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Internal Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Notes visible to coordinators only…"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost">Cancel</AppButton>
                    <AppButton type="submit" variant="primary" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create Plan' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
```

---

## Task 15: DeptSidebar.vue + PositionPanel.vue

**Files:**
- Create: `resources/js/Components/Scheduling/DeptSidebar.vue`
- Create: `resources/js/Components/Scheduling/PositionPanel.vue`

- [ ] **Step 1: Create DeptSidebar.vue**

```vue
<!-- resources/js/Components/Scheduling/DeptSidebar.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Building2, Plus } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { ServicePlan, ServicePlanPosition } from '@/types'

interface DeptGroup {
    id: number
    name: string
    icon: string | null
    color: string | null
    positions: ServicePlanPosition[]
}

const props = defineProps<{
    plan: ServicePlan
    deptGroups: DeptGroup[]
    selectedDeptId: number | null
    canManage: boolean
    canPublish: boolean
}>()

const emit = defineEmits<{
    selectDept: [id: number]
    addPosition: []
    publish: []
    archive: []
}>()

function filledCount(positions: ServicePlanPosition[]): number {
    return positions.filter(p => p.is_filled).length
}

function publishPlan() {
    router.patch(`/dashboard/scheduling/plans/${props.plan.id}/publish`)
}

function archivePlan() {
    if (confirm('Archive this plan? It will become read-only.')) {
        router.patch(`/dashboard/scheduling/plans/${props.plan.id}/archive`)
    }
}
</script>

<template>
    <aside class="flex w-60 flex-shrink-0 flex-col border-r border-gray-200 bg-white">
        <!-- Plan header -->
        <div class="border-b border-gray-100 px-4 py-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ plan.title }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ plan.scheduled_at_formatted }}
                        <span v-if="plan.scheduled_time"> · {{ plan.scheduled_time }}</span>
                    </p>
                    <p v-if="plan.location" class="text-xs text-gray-400">{{ plan.location }}</p>
                </div>
                <StatusBadge :status="plan.status" />
            </div>
        </div>

        <!-- Department list -->
        <nav class="flex-1 overflow-y-auto py-2">
            <button
                v-for="group in deptGroups"
                :key="group.id"
                type="button"
                :class="[
                    'flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm transition',
                    selectedDeptId === group.id
                        ? 'bg-indigo-50 text-indigo-700 font-medium'
                        : 'text-gray-700 hover:bg-gray-50',
                ]"
                @click="emit('selectDept', group.id)"
            >
                <Building2 class="h-4 w-4 flex-shrink-0 text-gray-400" />
                <span class="min-w-0 flex-1 truncate">{{ group.name }}</span>
                <span class="text-xs font-mono text-gray-400">
                    {{ filledCount(group.positions) }}/{{ group.positions.length }}
                </span>
            </button>

            <button
                v-if="canManage && !plan.isArchived"
                type="button"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50"
                @click="emit('addPosition')"
            >
                <Plus class="h-4 w-4" />
                Add position
            </button>
        </nav>

        <!-- Actions -->
        <div v-if="canManage || canPublish" class="border-t border-gray-100 px-4 py-3 space-y-2">
            <button
                v-if="canPublish && plan.status === 'draft'"
                type="button"
                class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                @click="publishPlan"
            >
                Publish Plan
            </button>
            <button
                v-if="canManage && plan.status !== 'archived'"
                type="button"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                @click="archivePlan"
            >
                Archive Plan
            </button>
        </div>
    </aside>
</template>
```

- [ ] **Step 2: Create PositionPanel.vue**

```vue
<!-- resources/js/Components/Scheduling/PositionPanel.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { UserPlus } from 'lucide-vue-next'
import AssignmentCard from '@/Components/Scheduling/AssignmentCard.vue'
import AssignDrawer from '@/Components/Scheduling/AssignDrawer.vue'
import type { ServicePlanPosition, VolunteerAssignment } from '@/types'

interface Member { id: number; name: string; avatar: string | null }

const props = defineProps<{
    positions: ServicePlanPosition[]
    members: Member[]
    canManage: boolean
    planStatus: string
}>()

// Track which slot the drawer is open for
const drawerPositionId = ref<number | null>(null)

function openDrawer(planPositionId: number) {
    drawerPositionId.value = planPositionId
}

function closeDrawer() {
    drawerPositionId.value = null
}

function existingFor(planPositionId: number): VolunteerAssignment[] {
    return props.positions.find(p => p.id === planPositionId)?.assignments ?? []
}

function assignVolunteer(planPositionId: number, userId: number) {
    router.post('/dashboard/scheduling/assignments', {
        service_plan_position_id: planPositionId,
        user_id: userId,
    }, {
        preserveScroll: true,
        onSuccess: () => closeDrawer(),
    })
}

function removeAssignment(assignment: VolunteerAssignment) {
    if (!confirm('Remove this volunteer from the slot?')) return
    router.delete(`/dashboard/scheduling/assignments/${assignment.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="flex flex-1 flex-col overflow-y-auto bg-gray-50 p-6">
        <div v-if="positions.length === 0" class="flex flex-1 items-center justify-center text-sm text-gray-400">
            No positions in this department yet.
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="pp in positions"
                :key="pp.id"
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <!-- Position header -->
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <p class="font-medium text-gray-900">{{ pp.serving_position?.name }}</p>
                        <p v-if="pp.notes" class="mt-0.5 text-xs text-gray-500">{{ pp.notes }}</p>
                    </div>
                    <span
                        :class="[
                            'text-xs font-medium',
                            pp.is_filled ? 'text-green-600' : 'text-gray-400',
                        ]"
                    >
                        {{ pp.is_filled ? 'Filled' : 'Open' }}
                    </span>
                </div>

                <!-- Assignments -->
                <div class="space-y-2">
                    <AssignmentCard
                        v-for="a in pp.assignments"
                        :key="a.id"
                        :assignment="a"
                        :can-manage="canManage && planStatus !== 'archived'"
                        @remove="removeAssignment"
                    />
                </div>

                <!-- Assign button -->
                <button
                    v-if="canManage && planStatus !== 'archived'"
                    type="button"
                    class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600"
                    @click="openDrawer(pp.id)"
                >
                    <UserPlus class="h-3.5 w-3.5" />
                    Assign volunteer
                </button>
            </div>
        </div>

        <!-- Assign drawer -->
        <AssignDrawer
            :open="drawerPositionId !== null"
            :plan-position-id="drawerPositionId ?? 0"
            :members="members"
            :existing-assignments="drawerPositionId ? existingFor(drawerPositionId) : []"
            @close="closeDrawer"
            @assign="assignVolunteer"
        />
    </div>
</template>
```

---

## Task 16: Plans/Show.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue`

- [ ] **Step 1: Create Plans/Show.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import DeptSidebar from '@/Components/Scheduling/DeptSidebar.vue'
import PositionPanel from '@/Components/Scheduling/PositionPanel.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ArrowLeft, Pencil, Trash2, X, Plus } from 'lucide-vue-next'
import type { ServicePlan, ServicePlanPosition, ServingPosition } from '@/types'

interface Member { id: number; name: string; avatar: string | null }
interface AvailablePosition { id: number; name: string; department: { id: number; name: string } }

const props = defineProps<{
    plan: ServicePlan & { plan_positions: ServicePlanPosition[] }
    members: Member[]
    availablePositions: AvailablePosition[]
    canManage: boolean
    canPublish: boolean
    canDelete: boolean
}>()

// ── Department grouping ────────────────────────────────────────────────────────

interface DeptGroup {
    id: number; name: string; icon: string | null; color: string | null
    positions: ServicePlanPosition[]
}

const deptGroups = computed<DeptGroup[]>(() => {
    const map = new Map<number, DeptGroup>()
    for (const pp of props.plan.plan_positions ?? []) {
        const dept = pp.serving_position?.department
        if (!dept) continue
        if (!map.has(dept.id)) map.set(dept.id, { ...dept, positions: [] })
        map.get(dept.id)!.positions.push(pp)
    }
    return Array.from(map.values())
})

const selectedDeptId = ref<number | null>(deptGroups.value[0]?.id ?? null)

const selectedPositions = computed<ServicePlanPosition[]>(() =>
    deptGroups.value.find(d => d.id === selectedDeptId.value)?.positions ?? []
)

// ── Edit plan modal ────────────────────────────────────────────────────────────

const showEditModal = ref(false)

const editForm = useForm({
    title:        props.plan.title,
    description:  props.plan.description ?? '',
    scheduled_at: props.plan.scheduled_at?.slice(0, 16) ?? '',
    location:     props.plan.location ?? '',
    notes:        props.plan.notes ?? '',
})

function submitEdit() {
    editForm.put(`/dashboard/scheduling/plans/${props.plan.id}`, {
        onSuccess: () => { showEditModal.value = false },
    })
}

// ── Add position modal ─────────────────────────────────────────────────────────

const showAddPosition = ref(false)
const selectedPositionId = ref<number | null>(null)

function addPosition() {
    if (!selectedPositionId.value) return
    router.post(`/dashboard/scheduling/plans/${props.plan.id}/positions`, {
        serving_position_id: selectedPositionId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddPosition.value = false
            selectedPositionId.value = null
        },
    })
}

// ── Delete plan ────────────────────────────────────────────────────────────────

function deletePlan() {
    if (!confirm('Delete this plan? This cannot be undone.')) return
    router.delete(`/dashboard/scheduling/plans/${props.plan.id}`)
}
</script>

<template>
    <DashboardLayout :title="plan.title">
        <!-- Top bar -->
        <div class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
            <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost" size="sm">
                <ArrowLeft class="h-4 w-4" />
                Plans
            </AppButton>
            <div class="flex items-center gap-2">
                <AppButton
                    v-if="canManage"
                    variant="ghost"
                    size="sm"
                    @click="showEditModal = true"
                >
                    <Pencil class="h-3.5 w-3.5" />
                    Edit
                </AppButton>
                <AppButton
                    v-if="canDelete"
                    variant="ghost"
                    size="sm"
                    class="text-red-500 hover:text-red-600"
                    @click="deletePlan"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    Delete
                </AppButton>
            </div>
        </div>

        <!-- Main split layout -->
        <div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 112px)">
            <DeptSidebar
                :plan="plan"
                :dept-groups="deptGroups"
                :selected-dept-id="selectedDeptId"
                :can-manage="canManage"
                :can-publish="canPublish"
                @select-dept="id => selectedDeptId = id"
                @add-position="showAddPosition = true"
            />

            <!-- Position panel or empty state -->
            <div v-if="selectedDeptId" class="flex flex-1 flex-col overflow-hidden">
                <div class="border-b border-gray-200 bg-white px-6 py-3">
                    <p class="font-semibold text-gray-900">
                        {{ deptGroups.find(d => d.id === selectedDeptId)?.name }}
                    </p>
                </div>
                <PositionPanel
                    :positions="selectedPositions"
                    :members="members"
                    :can-manage="canManage"
                    :plan-status="plan.status"
                />
            </div>

            <div v-else class="flex flex-1 items-center justify-center text-sm text-gray-400">
                <div class="text-center">
                    <p>No positions added to this plan yet.</p>
                    <button
                        v-if="canManage"
                        type="button"
                        class="mt-2 text-indigo-600 hover:underline"
                        @click="showAddPosition = true"
                    >
                        Add a position from the library
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Plan Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <h2 class="font-semibold text-gray-900">Edit Plan</h2>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4 px-5 py-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input v-model="editForm.title" type="text" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Date & Time</label>
                            <input v-model="editForm.scheduled_at" type="datetime-local" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Location</label>
                            <input v-model="editForm.location" type="text"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                            <textarea v-model="editForm.notes" rows="2"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" />
                        </div>
                        <div class="flex justify-end gap-3 pt-1">
                            <AppButton variant="ghost" type="button" @click="showEditModal = false">Cancel</AppButton>
                            <AppButton variant="primary" type="submit" :disabled="editForm.processing">Save</AppButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Add Position Modal -->
        <Teleport to="body">
            <div v-if="showAddPosition" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <h2 class="font-semibold text-gray-900">Add Position from Library</h2>
                        <button @click="showAddPosition = false" class="text-gray-400 hover:text-gray-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Select Position</label>
                        <select
                            v-model="selectedPositionId"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                        >
                            <option :value="null" disabled>Choose a position…</option>
                            <optgroup
                                v-for="dept in [...new Map(availablePositions.map(p => [p.department.id, p.department])).values()]"
                                :key="dept.id"
                                :label="dept.name"
                            >
                                <option
                                    v-for="pos in availablePositions.filter(p => p.department.id === dept.id)"
                                    :key="pos.id"
                                    :value="pos.id"
                                >
                                    {{ pos.name }}
                                </option>
                            </optgroup>
                        </select>
                        <div class="mt-4 flex justify-end gap-3">
                            <AppButton variant="ghost" type="button" @click="showAddPosition = false">Cancel</AppButton>
                            <AppButton variant="primary" type="button" :disabled="!selectedPositionId" @click="addPosition">
                                <Plus class="h-4 w-4" />
                                Add to Plan
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
```

---

## Task 17: Positions/Index.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Scheduling/Positions/Index.vue`

- [ ] **Step 1: Create Positions/Index.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/Positions/Index.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import type { ServingPosition } from '@/types'

interface Dept { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    positions: ServingPosition[]
    departments: Dept[]
}>()

// Group positions by department
function positionsFor(deptId: number) {
    return props.positions.filter(p => p.department_id === deptId)
}

// ── Create / Edit form ─────────────────────────────────────────────────────────

const showForm  = ref(false)
const editingId = ref<number | null>(null)

const form = useForm({
    department_id: null as number | null,
    name:          '',
    description:   '',
    sort_order:    0,
    is_active:     true,
})

function openCreate(deptId?: number) {
    editingId.value = null
    form.reset()
    form.department_id = deptId ?? null
    showForm.value = true
}

function openEdit(pos: ServingPosition) {
    editingId.value = pos.id
    form.department_id = pos.department_id
    form.name = pos.name
    form.description = pos.description ?? ''
    form.sort_order = pos.sort_order
    form.is_active = pos.is_active
    showForm.value = true
}

function submitForm() {
    if (editingId.value) {
        form.put(`/dashboard/scheduling/positions/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false },
        })
    } else {
        form.post('/dashboard/scheduling/positions', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false },
        })
    }
}

function deletePosition(pos: ServingPosition) {
    if (!confirm(`Delete "${pos.name}"?`)) return
    router.delete(`/dashboard/scheduling/positions/${pos.id}`, { preserveScroll: true })
}
</script>

<template>
    <DashboardLayout title="Position Library">
        <PageHeader title="Position Library">
            <template #actions>
                <AppButton variant="primary" @click="openCreate()">
                    <Plus class="h-4 w-4" />
                    New Position
                </AppButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <div v-for="dept in departments" :key="dept.id" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <!-- Dept header -->
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="font-medium text-gray-900">{{ dept.name }}</p>
                    <button
                        type="button"
                        class="flex items-center gap-1 text-xs text-indigo-600 hover:underline"
                        @click="openCreate(dept.id)"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Add position
                    </button>
                </div>

                <!-- Position rows -->
                <ul>
                    <li
                        v-for="pos in positionsFor(dept.id)"
                        :key="pos.id"
                        class="flex items-center gap-3 border-b border-gray-50 px-5 py-3 last:border-0"
                        :class="{ 'opacity-50': !pos.is_active }"
                    >
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-800">
                                {{ pos.name }}
                                <span v-if="!pos.is_active" class="ml-1 text-xs text-gray-400">(inactive)</span>
                            </p>
                            <p v-if="pos.description" class="text-xs text-gray-500">{{ pos.description }}</p>
                        </div>
                        <button @click="openEdit(pos)" class="rounded p-1 text-gray-400 hover:text-indigo-600">
                            <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button @click="deletePosition(pos)" class="rounded p-1 text-gray-400 hover:text-red-500">
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </li>
                    <li v-if="positionsFor(dept.id).length === 0" class="px-5 py-4 text-sm text-gray-400">
                        No positions yet.
                    </li>
                </ul>
            </div>

            <p v-if="departments.length === 0" class="text-center text-sm text-gray-400 py-12">
                No departments found. Create a department first.
            </p>
        </div>

        <!-- Create / Edit modal -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <h2 class="font-semibold text-gray-900">{{ editingId ? 'Edit Position' : 'New Position' }}</h2>
                        <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitForm" class="space-y-4 px-5 py-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                            <select
                                v-model="form.department_id"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                :class="{ 'border-red-400': form.errors.department_id }"
                            >
                                <option :value="null" disabled>Select department…</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-500">{{ form.errors.department_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Position Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Camera Operator"
                                required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-gray-300" />
                            <label for="is_active" class="text-sm text-gray-700">Active (available for new plans)</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-1">
                            <AppButton variant="ghost" type="button" @click="showForm = false">Cancel</AppButton>
                            <AppButton variant="primary" type="submit" :disabled="form.processing">
                                {{ editingId ? 'Save Changes' : 'Create Position' }}
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
```

---

## Task 18: MyAssignmentCard.vue + MySchedule.vue

**Files:**
- Create: `resources/js/Components/Scheduling/MyAssignmentCard.vue`
- Create: `resources/js/Pages/Dashboard/Scheduling/MySchedule.vue`

- [ ] **Step 1: Create MyAssignmentCard.vue**

```vue
<!-- resources/js/Components/Scheduling/MyAssignmentCard.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { CalendarDays, MapPin, CheckCircle2, XCircle } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'

interface AssignmentRow {
    id: number
    status: string
    plan: {
        id: number
        title: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
    position: {
        name: string | null
        department: { id: number; name: string; icon: string | null; color: string | null } | null
    }
}

defineProps<{ assignment: AssignmentRow }>()

function respond(id: number, status: 'confirmed' | 'declined') {
    router.patch(`/dashboard/scheduling/assignments/${id}/respond`, { status }, { preserveScroll: true })
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900">{{ assignment.plan.title }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ assignment.plan.scheduled_at_formatted }} · {{ assignment.plan.scheduled_time }}
                    </span>
                    <span v-if="assignment.plan.location" class="flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" />
                        {{ assignment.plan.location }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-indigo-600">
                    {{ assignment.position.name }}
                    <span v-if="assignment.position.department" class="text-gray-400">
                        · {{ assignment.position.department.name }}
                    </span>
                </p>
            </div>
            <StatusBadge :status="assignment.status" />
        </div>

        <!-- Confirm / Decline buttons (only when pending) -->
        <div v-if="assignment.status === 'pending'" class="mt-3 flex gap-2">
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-green-600 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                @click="respond(assignment.id, 'confirmed')"
            >
                <CheckCircle2 class="h-4 w-4" />
                Confirm
            </button>
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                @click="respond(assignment.id, 'declined')"
            >
                <XCircle class="h-4 w-4" />
                Can't make it
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Create MySchedule.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/MySchedule.vue -->
<script setup lang="ts">
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import MyAssignmentCard from '@/Components/Scheduling/MyAssignmentCard.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { CalendarDays } from 'lucide-vue-next'

interface AssignmentRow {
    id: number
    status: string
    plan: {
        id: number
        title: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
    position: {
        name: string | null
        department: { id: number; name: string; icon: string | null; color: string | null } | null
    }
}

defineProps<{ assignments: AssignmentRow[] }>()
</script>

<template>
    <DashboardLayout title="My Schedule">
        <PageHeader title="My Schedule" />

        <div v-if="assignments.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <MyAssignmentCard
                v-for="assignment in assignments"
                :key="assignment.id"
                :assignment="assignment"
            />
        </div>

        <EmptyState
            v-else
            :icon="CalendarDays"
            title="No upcoming assignments"
            description="You haven't been assigned to any upcoming services yet."
        />
    </DashboardLayout>
</template>
```

---

## Task 19: Scheduling/Dashboard.vue

**Files:**
- Create: `resources/js/Pages/Dashboard/Scheduling/Dashboard.vue`

- [ ] **Step 1: Create Scheduling/Dashboard.vue**

```vue
<!-- resources/js/Pages/Dashboard/Scheduling/Dashboard.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import PlanCard from '@/Components/Scheduling/PlanCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { ClipboardList, Layers, Clock3, Archive, Plus } from 'lucide-vue-next'
import type { ServicePlan } from '@/types'

defineProps<{
    stats: {
        total: number
        published: number
        draft: number
        archived: number
    }
    upcoming: ServicePlan[]
}>()

const auth = useAuthStore()
</script>

<template>
    <DashboardLayout title="Scheduling">
        <PageHeader title="Scheduling Dashboard">
            <template #actions>
                <AppButton
                    v-if="auth.can('scheduling.manage')"
                    :href="'/dashboard/scheduling/plans/create'"
                    variant="primary"
                >
                    <Plus class="h-4 w-4" />
                    New Plan
                </AppButton>
            </template>
        </PageHeader>

        <!-- Stats strip -->
        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-50 p-2">
                        <Layers class="h-5 w-5 text-indigo-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
                        <p class="text-xs text-gray-500">Total Plans</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-green-50 p-2">
                        <ClipboardList class="h-5 w-5 text-green-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.published }}</p>
                        <p class="text-xs text-gray-500">Published</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-yellow-50 p-2">
                        <Clock3 class="h-5 w-5 text-yellow-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.draft }}</p>
                        <p class="text-xs text-gray-500">Drafts</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-gray-100 p-2">
                        <Archive class="h-5 w-5 text-gray-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ stats.archived }}</p>
                        <p class="text-xs text-gray-500">Archived</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming published plans -->
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Upcoming Services</h2>
                <Link
                    href="/dashboard/scheduling/plans"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    View all
                </Link>
            </div>

            <div v-if="upcoming.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <PlanCard v-for="plan in upcoming" :key="plan.id" :plan="plan" />
            </div>

            <EmptyState
                v-else
                :icon="ClipboardList"
                title="No upcoming services"
                description="Publish a service plan to see it here."
            >
                <AppButton
                    v-if="auth.can('scheduling.manage')"
                    :href="'/dashboard/scheduling/plans'"
                    variant="primary"
                >
                    Go to Plans
                </AppButton>
            </EmptyState>
        </div>
    </DashboardLayout>
</template>
```

---

## Self-Review Checklist

- [x] **Spec coverage:** All 6 pages, 7 components, 4 controllers, 4 notifications, 4 models, 3 policies, 4 migrations covered.
- [x] **No placeholders:** Every step has complete code.
- [x] **Type consistency:** `ServicePlanPosition`, `VolunteerAssignment`, `ServingPosition` types defined in Task 2 and used in Tasks 12–19.
- [x] **No git commits:** Steps correctly omit git commands (no git repository in project).
- [x] **Auth gate flow:** `Gate::before` gives super_admin bypass; policies all check permissions first.
- [x] **Policy — coordinator update:** Checks `created_by = $user->id` OR dept membership to handle empty plans.
- [x] **Notifications load relations before dispatch:** `VolunteerDeclined` and others call `$assignment->load(...)` before notifying.
- [x] **`VolunteerAssignmentPolicy::create` signature:** Takes `[VolunteerAssignment::class, $planPosition]` to pass the position context to the policy.
- [x] **Test setup:** Uses `Church::create([...])` directly (no factory), seeds permissions, assigns roles explicitly.

