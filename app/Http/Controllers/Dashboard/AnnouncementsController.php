<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\AnnouncementWasPublished;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementsController extends Controller
{
    use ResolvesChurchData;
    use LogsAuditEvents;

    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly \App\Services\BroadcastService $broadcastService,
    ) {}

    // ── Resource actions ───────────────────────────────────────────────────────

    /** GET /dashboard/announcements */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Announcement::class);

        $user     = $request->user();
        $churchId = $this->resolvedChurchId();

        $announcements = $this->announcements->paginate(
            churchId: $churchId,
            user:     $user,
            filter:   $request->filter,
            search:   $request->search,
        )->through(fn ($a) => AnnouncementResource::make($a)->toArray($request));

        return Inertia::render('Dashboard/Announcements/Index', [
            'announcements' => $announcements,
            'departments'   => $this->activeDepartments(),
            'filters'       => $request->only('filter', 'search'),
            'canCreate'     => $user->can('announcements.create'),
            'canPin'        => $user->can('announcements.pin'),
            'canPublish'    => $user->can('announcements.publish'),
        ]);
    }

    /** GET /dashboard/announcements/create */
    public function create(Request $request): Response
    {
        $this->authorize('create', Announcement::class);

        return Inertia::render('Dashboard/Announcements/Create', [
            'departments' => $this->activeDepartments(),
        ]);
    }

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

    /** GET /dashboard/announcements/{announcement} */
    public function show(Request $request, Announcement $announcement): Response
    {
        $this->authorize('view', $announcement);

        $announcement->load([
            'creator:id,name,avatar',
            'department:id,name,icon,color',
            'files.uploader:id,name,avatar',
        ]);
        $announcement->loadCount('reads');

        $user = $request->user();

        // Auto-mark as read for published announcements
        if ($announcement->status === 'published') {
            $this->announcements->markRead($announcement, $user);
        }

        return Inertia::render('Dashboard/Announcements/Show', [
            'announcement' => AnnouncementResource::make($announcement),
            'isRead'       => true,   // auto-marked above
            'canEdit'      => $user->can('update', $announcement),
            'canDelete'    => $user->can('delete', $announcement),
            'canPublish'   => $user->can('publish', $announcement),
            'canPin'       => $user->can('pin', $announcement),
            'canUpload'    => $user->can('create', \App\Models\File::class),
        ]);
    }

    /** GET /dashboard/announcements/{announcement}/edit */
    public function edit(Request $request, Announcement $announcement): Response
    {
        $this->authorize('update', $announcement);

        $announcement->load('department:id,name');

        return Inertia::render('Dashboard/Announcements/Edit', [
            'announcement' => AnnouncementResource::make($announcement),
            'departments'  => $this->activeDepartments(),
        ]);
    }

    /** PUT /dashboard/announcements/{announcement} */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->announcements->update($announcement, $request->validated());

        $this->auditLog(
            'announcement.updated',
            $announcement,
            [],
            [],
            ['department_id' => $announcement->department_id],
        );

        return redirect()
            ->route('dashboard.announcements.show', $announcement)
            ->with('success', 'Announcement updated.');
    }

    /** DELETE /dashboard/announcements/{announcement} */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $deptId = $announcement->department_id;
        $title  = $announcement->title;
        $this->auditLog('announcement.deleted', $announcement, [], [], ['department_id' => $deptId]);
        $this->announcements->delete($announcement);

        return redirect()
            ->route('dashboard.announcements.index')
            ->with('success', "\u{201c}{$title}\u{201d} deleted.");
    }

    // ── Publishing ─────────────────────────────────────────────────────────────

    /** POST /dashboard/announcements/{announcement}/publish */
    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('publish', $announcement);

        if ($announcement->status === 'published') {
            $this->announcements->unpublish($announcement);
            $this->auditLog('announcement.unpublished', $announcement, [], [], ['department_id' => $announcement->department_id]);
            return back()->with('success', 'Announcement unpublished.');
        }

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
    }

    // ── Pin ────────────────────────────────────────────────────────────────────

    /** POST /dashboard/announcements/{announcement}/pin */
    public function pin(Announcement $announcement): RedirectResponse
    {
        $this->authorize('pin', $announcement);

        $this->announcements->togglePin($announcement);

        $pinned = $announcement->fresh()->is_pinned;
        $action = $pinned ? 'announcement.pinned' : 'announcement.unpinned';
        $this->auditLog($action, $announcement, [], [], ['department_id' => $announcement->department_id]);

        $msg = $pinned ? 'Announcement pinned.' : 'Announcement unpinned.';
        return back()->with('success', $msg);
    }

    // ── Read tracking ──────────────────────────────────────────────────────────

    /** POST /dashboard/announcements/{announcement}/read */
    public function markRead(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('view', $announcement);
        $this->announcements->markRead($announcement, $request->user());
        return back();
    }
}
