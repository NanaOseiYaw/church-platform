<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\File;
use App\Models\ChannelConnection;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\GalleryImage;
use App\Models\PrayerRequest;
use App\Models\ServicePlan;
use App\Models\ServingPosition;
use App\Models\VolunteerAssignment;
use App\Models\ServicePlanPosition;
use App\Policies\AnnouncementPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\ChurchPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EventPolicy;
use App\Policies\FilePolicy;
use App\Policies\GalleryImagePolicy;
use App\Policies\PrayerRequestPolicy;
use App\Policies\SermonPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\ServicePlanPolicy;
use App\Policies\ServingPositionPolicy;
use App\Policies\VolunteerAssignmentPolicy;
use App\Observers\AuditableObserver;
use App\Listeners\Notifications\AnnouncementNotificationSubscriber;
use App\Listeners\Notifications\DepartmentNotificationSubscriber;
use App\Listeners\Notifications\EventNotificationSubscriber;
use App\Listeners\Notifications\TaskNotificationSubscriber;
use App\Listeners\Broadcast\BroadcastAnnouncementSubscriber;
use App\Listeners\Broadcast\BroadcastOnNotificationSent;
use App\Listeners\Broadcast\BroadcastTaskSubscriber;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Event as EventDispatcher;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends AuthServiceProvider
{
    /**
     * Model → Policy map.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Church::class           => ChurchPolicy::class,
        User::class             => UserPolicy::class,
        Department::class       => DepartmentPolicy::class,
        Event::class            => EventPolicy::class,
        Announcement::class     => AnnouncementPolicy::class,
        Task::class             => TaskPolicy::class,
        TaskComment::class      => TaskPolicy::class,
        Sermon::class           => SermonPolicy::class,
        File::class             => FilePolicy::class,
        AttendanceSession::class => AttendancePolicy::class,
        ServicePlan::class         => ServicePlanPolicy::class,
        ServingPosition::class     => ServingPositionPolicy::class,
        VolunteerAssignment::class => VolunteerAssignmentPolicy::class,
        GalleryImage::class        => GalleryImagePolicy::class,
        PrayerRequest::class       => PrayerRequestPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();

        // ── Audit observers (created + deleted only) ───────────────────────────
        foreach ([
            Announcement::class,
            Event::class,
            Task::class,
            Department::class,
            ServingPosition::class,
            ServicePlan::class,
            AttendanceSession::class,
            Sermon::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        // ── JsonResource — remove the "data" envelope ──────────────────────────
        // Inertia v3's PropsResolver handles JsonResource as Responsable, calling
        // toResponse()->getData(true). The default response is { "data": {...} },
        // which makes every prop land in Vue as { data: {...} } instead of the
        // flat object. Disabling the wrapper causes toResponse() to return the
        // flat JSON, so Inertia passes props directly without the data envelope.
        // This is correct for an Inertia app — "data" wrapping is a JSON:API
        // convention and is not needed here.
        JsonResource::withoutWrapping();

        // Super-admins bypass every Gate check
        Gate::before(function (\App\Models\User $user, string $ability): ?bool {
            if ($user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        // ── Polymorphic morph map ───────────────────────────────────────────────
        // Short aliases stored in fileable_type instead of full class names.
        Relation::morphMap([
            'announcement' => Announcement::class,
            'event'        => Event::class,
            'task'         => Task::class,
            'department'   => Department::class,
        ]);

        // ── Notification event subscribers ─────────────────────────────────────
        EventDispatcher::subscribe(TaskNotificationSubscriber::class);
        EventDispatcher::subscribe(AnnouncementNotificationSubscriber::class);
        EventDispatcher::subscribe(EventNotificationSubscriber::class);
        EventDispatcher::subscribe(DepartmentNotificationSubscriber::class);

        // ── Broadcast event subscribers ────────────────────────────────────────
        // These run alongside notification subscribers — same domain events,
        // independent responsibilities (broadcast vs. notify).
        EventDispatcher::subscribe(BroadcastTaskSubscriber::class);
        EventDispatcher::subscribe(BroadcastAnnouncementSubscriber::class);

        // Broadcast a WebSocket event whenever a database notification is persisted
        EventDispatcher::listen(NotificationSent::class, BroadcastOnNotificationSent::class);
    }
}
