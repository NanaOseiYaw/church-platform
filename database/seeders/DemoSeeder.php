<?php

namespace Database\Seeders;

use App\Enums\DepartmentVisibility;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Models\BroadcastTemplate;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\GalleryImage;
use App\Models\MemberProfile;
use App\Models\PrayerRequest;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\ServicePlan;
use App\Models\ServicePlanPosition;
use App\Models\ServingPosition;
use App\Models\Task;
use App\Models\User;
use App\Models\VolunteerAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Rich, demonstration-ready content for the Church of Pentecost "Grace Assembly"
 * tenant created by ChurchSeeder. Populates EVERY content module so no dashboard
 * or public screen renders an empty state during a demo.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Safe to run after ChurchSeeder. Users are upserted by email; content is created
 * once (guarded on the presence of departments) so re-running won't duplicate it.
 */
class DemoSeeder extends Seeder
{
    private ?Church $church = null;

    /** @var array<string, User> */
    private array $u = [];

    public function run(): void
    {
        // Self-sufficient: ensure roles + the baseline tenant exist so
        // `php artisan db:seed --class=DemoSeeder` works end-to-end on a fresh DB.
        $this->callOnce(RolesAndPermissionsSeeder::class);

        $this->church = Church::where('slug', 'cop-grace-assembly')->first();

        if (! $this->church) {
            $this->callOnce(ChurchSeeder::class);
            $this->church = Church::where('slug', 'cop-grace-assembly')->first();
        }

        if (! $this->church) {
            $this->command->warn('DemoSeeder: baseline tenant (cop-grace-assembly) could not be created. Skipping.');
            return;
        }

        $this->seedMembers();

        if ($this->church->departments()->exists()) {
            $this->command->info('DemoSeeder: content already present — members ensured, skipping content.');
            return;
        }

        $this->seedDepartments();
        $this->seedEvents();
        $this->seedAnnouncements();
        $this->seedSermons();
        $this->seedTasks();
        $this->seedScheduling();
        $this->seedAttendance();
        $this->seedGallery();
        $this->seedPrayerRequests();
        $this->seedBroadcasts();

        $this->command->info('DemoSeeder: Grace Assembly populated across all modules.');
    }

    // ── Members ────────────────────────────────────────────────────────────────

    private function seedMembers(): void
    {
        // Pull in the accounts ChurchSeeder already created.
        $this->u['admin']  = User::where('email', 'admin@cop-grace.org')->first();
        $this->u['member'] = User::where('email', 'member@cop-grace.org')->first();

        $people = [
            ['key' => 'kwame',   'name' => 'Kwame Mensah',    'email' => 'kwame@cop-grace.org',   'role' => 'coordinator',           'gender' => 'male'],
            ['key' => 'abena',   'name' => 'Abena Osei',      'email' => 'abena@cop-grace.org',   'role' => 'coordinator',           'gender' => 'female'],
            ['key' => 'yaw',     'name' => 'Yaw Boateng',     'email' => 'yaw@cop-grace.org',     'role' => 'assistant_coordinator', 'gender' => 'male'],
            ['key' => 'akosua',  'name' => 'Akosua Asante',   'email' => 'akosua@cop-grace.org',  'role' => 'assistant_coordinator', 'gender' => 'female'],
            ['key' => 'kofi',    'name' => 'Kofi Owusu',      'email' => 'kofi@cop-grace.org',    'role' => 'member',                'gender' => 'male'],
            ['key' => 'ama',     'name' => 'Ama Darko',       'email' => 'ama@cop-grace.org',     'role' => 'member',                'gender' => 'female'],
            ['key' => 'esi',     'name' => 'Esi Appiah',      'email' => 'esi@cop-grace.org',     'role' => 'member',                'gender' => 'female'],
            ['key' => 'kwabena', 'name' => 'Kwabena Adjei',   'email' => 'kwabena@cop-grace.org', 'role' => 'member',                'gender' => 'male'],
            ['key' => 'adwoa',   'name' => 'Adwoa Agyeman',   'email' => 'adwoa@cop-grace.org',   'role' => 'member',                'gender' => 'female'],
        ];

        foreach ($people as $i => $p) {
            $user = User::firstOrCreate(
                ['email' => $p['email']],
                [
                    'church_id'         => $this->church->id,
                    'name'              => $p['name'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'phone'             => '+233 24 ' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                ]
            );
            $user->assignRole($p['role']);
            $this->u[$p['key']] = $user;

            MemberProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'church_id'       => $this->church->id,
                    'gender'          => $p['gender'],
                    'marital_status'  => $i % 2 === 0 ? 'married' : 'single',
                    'date_of_birth'   => now()->subYears(24 + $i)->subDays($i * 37)->toDateString(),
                    'address'         => 'Accra, Ghana',
                    'membership_date' => now()->subYears(min($i + 1, 8))->toDateString(),
                    'baptism_date'    => now()->subYears(min($i + 1, 8))->subMonths(2)->toDateString(),
                ]
            );
        }

        // Give the two base accounts simple profiles too.
        foreach (['admin', 'member'] as $key) {
            if ($this->u[$key]) {
                MemberProfile::firstOrCreate(
                    ['user_id' => $this->u[$key]->id],
                    [
                        'church_id'       => $this->church->id,
                        'gender'          => $key === 'admin' ? 'male' : 'female',
                        'marital_status'  => 'married',
                        'membership_date' => now()->subYears(10)->toDateString(),
                    ]
                );
            }
        }
    }

    // ── Departments + serving positions ──────────────────────────────────────────

    /** @var array<string, Department> */
    private array $d = [];

    private function seedDepartments(): void
    {
        $defs = [
            ['key' => 'worship',  'name' => 'Worship & Music',          'icon' => 'Music',          'color' => '#3b8ac8', 'coordinator' => 'kwame',  'desc' => 'Leading the assembly into the presence of God through Spirit-filled worship.'],
            ['key' => 'media',    'name' => 'Media & Communications',   'icon' => 'Radio',          'color' => '#0ea5e9', 'coordinator' => 'abena',  'desc' => 'Sound, livestream, photography and online presence for every service.'],
            ['key' => 'ushering', 'name' => 'Ushering & Protocol',      'icon' => 'Users',          'color' => '#f59e0b', 'coordinator' => 'yaw',    'desc' => 'Welcoming members and guests and keeping order during services.'],
            ['key' => 'children', 'name' => "Children's Ministry",      'icon' => 'Baby',           'color' => '#ec4899', 'coordinator' => 'akosua', 'desc' => 'Nurturing the next generation in the knowledge of Christ.'],
            ['key' => 'evangel',  'name' => 'Evangelism & Missions',    'icon' => 'Megaphone',      'color' => '#10b981', 'coordinator' => null,     'desc' => 'Taking the gospel to our community and the nations.'],
            ['key' => 'prayer',   'name' => 'Prayer & Intercession',    'icon' => 'HeartHandshake', 'color' => '#8b5cf6', 'coordinator' => null,     'desc' => 'Standing in the gap for the church, the nation and the lost.'],
        ];

        foreach ($defs as $def) {
            $coordinator = $def['coordinator'] ? $this->u[$def['coordinator']] : null;

            $dept = Department::create([
                'church_id'      => $this->church->id,
                'name'           => $def['name'],
                'slug'           => $def['key'],
                'description'    => $def['desc'],
                'icon'           => $def['icon'],
                'color'          => $def['color'],
                'is_active'      => true,
                'visibility'     => DepartmentVisibility::PUBLIC,
                'coordinator_id' => $coordinator?->id,
                'created_by'     => $this->u['admin']?->id,
            ]);
            $this->d[$def['key']] = $dept;

            if ($coordinator) {
                $dept->members()->syncWithoutDetaching([
                    $coordinator->id => ['role' => 'coordinator', 'joined_at' => now()->subYears(2)],
                ]);
            }
        }

        // Spread members across departments.
        $this->attachMembers('worship',  ['kwame', 'ama', 'esi', 'member']);
        $this->attachMembers('media',    ['abena', 'kwabena', 'kofi']);
        $this->attachMembers('ushering', ['yaw', 'kofi', 'adwoa']);
        $this->attachMembers('children', ['akosua', 'esi', 'ama']);
        $this->attachMembers('evangel',  ['kwabena', 'adwoa', 'kofi']);
        $this->attachMembers('prayer',   ['adwoa', 'esi', 'member']);

        // Serving positions used by the scheduling module.
        $positions = [
            'worship'  => ['Worship Leader', 'Lead Vocalist', 'Keyboardist', 'Drummer', 'Bass Guitarist'],
            'media'    => ['Sound Engineer', 'Livestream Operator', 'Camera Operator', 'Slides / ProPresenter'],
            'ushering' => ['Head Usher', 'Usher', 'Greeter'],
            'children' => ['Lead Teacher', 'Assistant Teacher'],
        ];

        foreach ($positions as $deptKey => $names) {
            foreach ($names as $order => $name) {
                ServingPosition::create([
                    'church_id'     => $this->church->id,
                    'department_id' => $this->d[$deptKey]->id,
                    'name'          => $name,
                    'sort_order'    => $order,
                    'is_active'     => true,
                ]);
            }
        }
    }

    /** @param list<string> $memberKeys */
    private function attachMembers(string $deptKey, array $memberKeys): void
    {
        // Don't disturb members already attached (e.g. the coordinator, whose
        // pivot role we set earlier) — only add the ones not yet in the department.
        $existing = $this->d[$deptKey]->members()->pluck('users.id')->all();

        $rows = [];
        foreach ($memberKeys as $key) {
            $id = $this->u[$key]->id ?? null;
            if ($id && ! in_array($id, $existing, true)) {
                $rows[$id] = ['role' => 'member', 'joined_at' => now()->subMonths(rand(1, 24))];
            }
        }

        if ($rows) {
            $this->d[$deptKey]->members()->syncWithoutDetaching($rows);
        }
    }

    // ── Events ───────────────────────────────────────────────────────────────────

    private function seedEvents(): void
    {
        $admin = $this->u['admin']?->id;

        $events = [
            ['title' => 'Sunday Worship Service',          'days' => +3,  'cat' => 'Service',     'feat' => true,  'dept' => 'worship',  'rsvp' => false, 'cap' => null, 'loc' => 'Main Auditorium', 'desc' => 'Join us this Sunday for Spirit-filled worship, the preaching of the Word, and warm fellowship.'],
            ['title' => 'Easter Convention 2026',          'days' => +21, 'cat' => 'Convention',  'feat' => true,  'dept' => null,       'rsvp' => true,  'cap' => 800, 'loc' => 'Grace Assembly Grounds', 'desc' => 'Three days of revival, teaching and celebration of the resurrection of our Lord Jesus Christ.'],
            ['title' => 'Pentecost Youth Camp',            'days' => +35, 'cat' => 'Youth',       'feat' => true,  'dept' => null,       'rsvp' => true,  'cap' => 200, 'loc' => 'Pentecost Convention Centre', 'desc' => 'A weekend retreat for the youth — worship, workshops, sports and encounters with God.'],
            ['title' => "Women's Ministry Breakfast",      'days' => +10, 'cat' => 'Fellowship',  'feat' => false, 'dept' => null,       'rsvp' => true,  'cap' => 120, 'loc' => 'Fellowship Hall', 'desc' => 'A morning of food, fellowship and an encouraging word for the women of Grace Assembly.'],
            ['title' => "Men's Prayer Summit",             'days' => +14, 'cat' => 'Prayer',      'feat' => false, 'dept' => 'prayer',   'rsvp' => true,  'cap' => 150, 'loc' => 'Prayer Chapel', 'desc' => 'Men gathering to seek the face of God for their families, the church and the nation.'],
            ['title' => 'Baptism & Confirmation Service',  'days' => +28, 'cat' => 'Service',     'feat' => false, 'dept' => null,       'rsvp' => false, 'cap' => null, 'loc' => 'Main Auditorium', 'desc' => 'Celebrating new believers as they publicly declare their faith through water baptism.'],
            ['title' => 'Watchnight Service 2025',         'days' => -160,'cat' => 'Service',     'feat' => false, 'dept' => null,       'rsvp' => false, 'cap' => null, 'loc' => 'Main Auditorium', 'desc' => 'We crossed over into the new year in worship, thanksgiving and prayer.'],
        ];

        foreach ($events as $e) {
            $start = $e['days'] >= 0
                ? now()->addDays($e['days'])->setTime(10, 0)
                : now()->addDays($e['days'])->setTime(22, 0);

            Event::create([
                'church_id'     => $this->church->id,
                'department_id' => $e['dept'] ? $this->d[$e['dept']]->id : null,
                'created_by'    => $admin,
                'title'         => $e['title'],
                'description'   => $e['desc'],
                'location'      => $e['loc'],
                'category'      => $e['cat'],
                'start_at'      => $start,
                'end_at'        => (clone $start)->addHours(2),
                'all_day'       => false,
                'visibility'    => 'public',
                'is_public'     => true,
                'is_cancelled'  => false,
                'published_at'  => now()->subDays(2),
                'is_featured'   => $e['feat'],
                'rsvp_enabled'  => $e['rsvp'],
                'capacity'      => $e['cap'],
            ]);
        }

        // A few RSVPs on the convention so the count isn't zero.
        $convention = Event::where('church_id', $this->church->id)->where('title', 'Easter Convention 2026')->first();
        if ($convention) {
            foreach (['kwame', 'abena', 'kofi', 'ama', 'esi', 'adwoa'] as $key) {
                if (! empty($this->u[$key])) {
                    $convention->rsvps()->syncWithoutDetaching([$this->u[$key]->id => ['status' => 'going']]);
                }
            }
        }
    }

    // ── Announcements ────────────────────────────────────────────────────────────

    private function seedAnnouncements(): void
    {
        $admin = $this->u['admin']?->id;

        $items = [
            ['title' => 'Welcome to Grace Assembly',                  'cat' => 'General',   'pin' => true,  'feat' => true,  'prio' => 'high',   'dept' => null,      'body' => 'We are delighted to have you worship with us. If you are new, please visit the welcome desk after service so we can connect with you.'],
            ['title' => 'Online Giving Now Available',                'cat' => 'Giving',    'pin' => false, 'feat' => true,  'prio' => 'medium', 'dept' => null,      'body' => 'You can now give your tithes and offerings securely online. Visit the Giving page on our website or use the mobile money short-code announced in service.'],
            ['title' => 'New Members Class Starts Next Sunday',       'cat' => 'Discipleship','pin' => false,'feat' => false, 'prio' => 'medium', 'dept' => null,      'body' => 'Our four-week New Members Class begins next Sunday at 9:00 AM in the Fellowship Hall. All new members are encouraged to attend.'],
            ['title' => 'Choir Auditions This Saturday',              'cat' => 'Worship',   'pin' => false, 'feat' => false, 'prio' => 'low',    'dept' => 'worship', 'body' => 'The Worship & Music department invites singers and instrumentalists to auditions this Saturday at 4:00 PM. Come and serve with your gift!'],
            ['title' => 'Harvest Thanksgiving — Save the Date',       'cat' => 'Events',    'pin' => false, 'feat' => true,  'prio' => 'high',   'dept' => null,      'body' => 'Our annual Harvest Thanksgiving will be held on the last Sunday of next month. Begin to prepare your seed and invite your loved ones.'],
        ];

        foreach ($items as $i => $a) {
            Announcement::create([
                'church_id'      => $this->church->id,
                'department_id'  => $a['dept'] ? $this->d[$a['dept']]->id : null,
                'created_by'     => $admin,
                'title'          => $a['title'],
                'body'           => $a['body'],
                'category'       => $a['cat'],
                'is_pinned'      => $a['pin'],
                'is_church_wide' => $a['dept'] === null,
                'visibility'     => 'public',
                'is_featured'    => $a['feat'],
                'priority'       => $a['prio'],
                'published_at'   => now()->subDays($i + 1),
            ]);
        }
    }

    // ── Sermons + series ─────────────────────────────────────────────────────────

    private function seedSermons(): void
    {
        $uploader = $this->u['admin']?->id;

        $series = [
            'pentecost' => SermonSeries::create([
                'church_id' => $this->church->id, 'title' => 'The Power of Pentecost', 'slug' => 'the-power-of-pentecost',
                'description' => 'Rediscovering the person and work of the Holy Spirit in the life of the believer.',
                'is_active' => true, 'sort_order' => 1, 'started_at' => now()->subMonths(3)->toDateString(),
            ]),
            'faith' => SermonSeries::create([
                'church_id' => $this->church->id, 'title' => 'Faith That Moves Mountains', 'slug' => 'faith-that-moves-mountains',
                'description' => 'A study on living by faith in every season of life.',
                'is_active' => true, 'sort_order' => 2, 'started_at' => now()->subMonths(2)->toDateString(),
            ]),
            'kingdom' => SermonSeries::create([
                'church_id' => $this->church->id, 'title' => 'Kingdom Living', 'slug' => 'kingdom-living',
                'description' => 'What it means to live as citizens of the Kingdom of God here and now.',
                'is_active' => true, 'sort_order' => 3, 'started_at' => now()->subMonths(1)->toDateString(),
            ]),
        ];

        $sermons = [
            ['title' => 'Receiving the Promise of the Father', 'speaker' => 'Apostle Samuel Mensah', 'series' => 'pentecost', 'weeks' => 1,  'mins' => 47, 'feat' => true],
            ['title' => 'Power to Be Witnesses',               'speaker' => 'Apostle Samuel Mensah', 'series' => 'pentecost', 'weeks' => 2,  'mins' => 52, 'feat' => false],
            ['title' => 'The Gifts of the Spirit',             'speaker' => 'Pastor Emmanuel Boadi', 'series' => 'pentecost', 'weeks' => 3,  'mins' => 41, 'feat' => false],
            ['title' => 'When You Pray, Believe',              'speaker' => 'Apostle Samuel Mensah', 'series' => 'faith',     'weeks' => 5,  'mins' => 44, 'feat' => false],
            ['title' => 'The Substance of Things Hoped For',   'speaker' => 'Pastor Grace Owusu',    'series' => 'faith',     'weeks' => 7,  'mins' => 39, 'feat' => false],
            ['title' => 'Seek First the Kingdom',              'speaker' => 'Apostle Samuel Mensah', 'series' => 'kingdom',   'weeks' => 9,  'mins' => 49, 'feat' => false],
        ];

        foreach ($sermons as $i => $s) {
            $title = $s['title'];
            Sermon::create([
                'church_id'        => $this->church->id,
                'uploaded_by'      => $uploader,
                'provider'         => 'youtube',
                'title'            => $title,
                'slug'             => \Illuminate\Support\Str::slug($title),
                'speaker'          => $s['speaker'],
                'description'      => 'A message from the ' . $series[$s['series']]->title . ' series, preached at Grace Assembly.',
                'video_url'        => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                'embed_url'        => 'https://www.youtube.com/embed/ScMzIvxBSi4?rel=0&modestbranding=1',
                'thumbnail_url'    => 'https://picsum.photos/seed/grace-sermon-' . ($i + 1) . '/800/450',
                'duration_seconds' => $s['mins'] * 60,
                'series'           => $series[$s['series']]->title,
                'series_id'        => $series[$s['series']]->id,
                'is_public'        => true,
                'visibility'       => 'public',
                'is_featured'      => $s['feat'],
                'preached_at'      => now()->subWeeks($s['weeks']),
            ]);
        }
    }

    // ── Tasks ────────────────────────────────────────────────────────────────────

    private function seedTasks(): void
    {
        $admin = $this->u['admin']?->id;

        $tasks = [
            ['title' => 'Prepare Sunday service order of worship', 'to' => 'kwame',   'dept' => 'worship',  'prio' => 'high',   'status' => 'in_progress', 'due' => +2,  'by' => 'admin'],
            ['title' => "Edit and upload last week's sermon",      'to' => 'abena',   'dept' => 'media',    'prio' => 'medium', 'status' => 'pending',     'due' => +4,  'by' => 'admin'],
            ['title' => 'Confirm ushers rota for convention',      'to' => 'yaw',     'dept' => 'ushering', 'prio' => 'high',   'status' => 'pending',     'due' => +6,  'by' => 'admin'],
            ['title' => 'Order communion elements',                'to' => 'kofi',    'dept' => null,       'prio' => 'urgent', 'status' => 'pending',     'due' => -1,  'by' => 'kwame'],
            ['title' => 'Follow up with first-time visitors',      'to' => 'akosua',  'dept' => null,       'prio' => 'medium', 'status' => 'completed',   'due' => -3,  'by' => 'admin'],
            ['title' => 'Update the church notice board',          'to' => 'ama',     'dept' => null,       'prio' => 'low',    'status' => 'pending',     'due' => +9,  'by' => 'abena'],
        ];

        foreach ($tasks as $t) {
            Task::create([
                'church_id'     => $this->church->id,
                'department_id' => $t['dept'] ? $this->d[$t['dept']]->id : null,
                'assigned_to'   => $this->u[$t['to']]?->id,
                'assigned_by'   => $this->u[$t['by']]?->id ?? $admin,
                'title'         => $t['title'],
                'description'   => 'Auto-generated demo task to illustrate the task workflow.',
                'priority'      => $t['prio'],
                'status'        => $t['status'],
                'due_at'        => now()->addDays($t['due'])->setTime(17, 0),
                'completed_at'  => $t['status'] === 'completed' ? now()->subDays(2) : null,
            ]);
        }
    }

    // ── Scheduling (published service plan + assignments) ────────────────────────

    private function seedScheduling(): void
    {
        $admin = $this->u['admin']?->id;
        $sunday = now()->next(Carbon::SUNDAY)->setTime(10, 0);

        $plan = ServicePlan::create([
            'church_id'    => $this->church->id,
            'created_by'   => $admin,
            'published_by' => $admin,
            'title'        => 'Sunday Worship — ' . $sunday->format('M j'),
            'description'  => 'Service plan for the main Sunday worship service.',
            'scheduled_at' => $sunday,
            'location'     => 'Main Auditorium',
            'status'       => 'published',
            'notes'        => 'Theme: Possessing the Nations. Communion Sunday.',
            'published_at' => now(),
        ]);

        // Pick a handful of positions to fill, with volunteers.
        $fill = [
            ['position' => 'Worship Leader',      'volunteer' => 'kwame',   'status' => 'accepted'],
            ['position' => 'Keyboardist',         'volunteer' => 'ama',     'status' => 'accepted'],
            ['position' => 'Sound Engineer',      'volunteer' => 'abena',   'status' => 'accepted'],
            ['position' => 'Livestream Operator', 'volunteer' => 'kwabena', 'status' => 'pending'],
            ['position' => 'Head Usher',          'volunteer' => 'yaw',     'status' => 'accepted'],
        ];

        foreach ($fill as $order => $f) {
            $position = ServingPosition::where('church_id', $this->church->id)
                ->where('name', $f['position'])->first();

            if (! $position) {
                continue;
            }

            $planPosition = ServicePlanPosition::create([
                'service_plan_id'     => $plan->id,
                'serving_position_id' => $position->id,
                'sort_order'          => $order,
            ]);

            $volunteer = $this->u[$f['volunteer']] ?? null;
            if ($volunteer) {
                VolunteerAssignment::create([
                    'church_id'                => $this->church->id,
                    'service_plan_position_id' => $planPosition->id,
                    'user_id'                  => $volunteer->id,
                    'assigned_by'              => $admin,
                    'status'                   => $f['status'],
                    'responded_at'             => $f['status'] === 'accepted' ? now()->subDay() : null,
                ]);
            }
        }
    }

    // ── Attendance ───────────────────────────────────────────────────────────────

    private function seedAttendance(): void
    {
        $lastSunday = now()->previous(Carbon::SUNDAY)->setTime(10, 0);

        $session = AttendanceSession::create([
            'church_id'    => $this->church->id,
            'created_by'   => $this->u['admin']?->id,
            'title'        => 'Sunday Worship Service',
            'type'         => 'service',
            'description'  => 'Main Sunday service attendance.',
            'scheduled_at' => $lastSunday,
            'ended_at'     => (clone $lastSunday)->addHours(2),
            'status'       => 'completed',
        ]);

        $roster = [
            'admin' => 'present', 'member' => 'present', 'kwame' => 'present', 'abena' => 'present',
            'yaw' => 'late', 'akosua' => 'present', 'kofi' => 'present', 'ama' => 'present',
            'esi' => 'excused', 'kwabena' => 'present', 'adwoa' => 'late',
        ];

        foreach ($roster as $key => $status) {
            $user = $this->u[$key] ?? null;
            if (! $user) {
                continue;
            }

            Attendance::create([
                'church_id'     => $this->church->id,
                'session_id'    => $session->id,
                'user_id'       => $user->id,
                'status'        => $status,
                'checked_in_at' => $status === 'excused' ? null : (clone $lastSunday)->addMinutes($status === 'late' ? 25 : rand(-10, 5)),
                'source'        => 'manual',
                'recorded_by'   => $this->u['admin']?->id,
            ]);
        }
    }

    // ── Gallery (branded SVG placeholder tiles on the public disk) ───────────────

    private function seedGallery(): void
    {
        $tiles = [
            ['title' => 'Sunday Worship',        'caption' => 'The congregation in worship.',           'color' => '#1e5aa8', 'pub' => true],
            ['title' => 'Baptism Service',       'caption' => 'New believers baptised in water.',       'color' => '#0ea5e9', 'pub' => true],
            ['title' => 'Youth Camp',            'caption' => 'Highlights from the youth retreat.',      'color' => '#10b981', 'pub' => true],
            ['title' => 'Harvest Thanksgiving',  'caption' => 'Celebrating the goodness of God.',        'color' => '#f59e0b', 'pub' => true],
            ['title' => 'Choir Ministration',    'caption' => 'The mass choir leading praise.',          'color' => '#8b5cf6', 'pub' => false],
            ['title' => 'Community Outreach',    'caption' => 'Reaching our neighbourhood with love.',   'color' => '#ec4899', 'pub' => false],
        ];

        foreach ($tiles as $i => $t) {
            $path = "gallery/demo-" . ($i + 1) . ".svg";
            Storage::disk('public')->put($path, $this->placeholderSvg($t['title'], $t['color']));

            GalleryImage::create([
                'church_id'     => $this->church->id,
                'uploaded_by'   => $this->u['admin']?->id,
                'title'         => $t['title'],
                'caption'       => $t['caption'],
                'image_path'    => $path,
                'disk'          => 'public',
                'display_order' => $i,
                'is_published'  => $t['pub'],
            ]);
        }
    }

    private function placeholderSvg(string $label, string $color): string
    {
        $safe = htmlspecialchars($label, ENT_QUOTES);
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
          <defs>
            <radialGradient id="g" cx="50%" cy="35%" r="75%">
              <stop offset="0%" stop-color="#ffffff" stop-opacity="0.35"/>
              <stop offset="100%" stop-color="{$color}" stop-opacity="0"/>
            </radialGradient>
          </defs>
          <rect width="800" height="600" fill="{$color}"/>
          <rect width="800" height="600" fill="url(#g)"/>
          <text x="400" y="300" font-family="Inter, Arial, sans-serif" font-size="42" font-weight="700" fill="#ffffff" text-anchor="middle">{$safe}</text>
          <text x="400" y="348" font-family="Inter, Arial, sans-serif" font-size="20" fill="#ffffff" fill-opacity="0.75" text-anchor="middle">Grace Assembly</text>
        </svg>
        SVG;
    }

    // ── Prayer requests ──────────────────────────────────────────────────────────

    private function seedPrayerRequests(): void
    {
        $items = [
            ['name' => 'Daniel Ofori',  'email' => 'daniel@example.com', 'request' => 'Please pray for my mother who is unwell. Believing God for her complete healing.', 'anon' => false, 'priv' => false, 'ans' => false],
            ['name' => null,            'email' => null,                  'request' => 'Pray for me, I am seeking direction concerning a major decision.',                'anon' => true,  'priv' => false, 'ans' => false],
            ['name' => 'Patience Adu',  'email' => 'patience@example.com','request' => 'Thanksgiving — I got the job! Thank you for standing with me in prayer.',        'anon' => false, 'priv' => true,  'ans' => true],
            ['name' => 'Michael Tetteh','email' => 'michael@example.com', 'request' => 'Praying for my family and our finances in this season.',                          'anon' => false, 'priv' => false, 'ans' => false],
            ['name' => null,            'email' => null,                  'request' => 'Pray for my marriage; we need restoration and peace at home.',                   'anon' => true,  'priv' => true,  'ans' => false],
        ];

        foreach ($items as $i => $p) {
            PrayerRequest::create([
                'church_id'    => $this->church->id,
                'name'         => $p['anon'] ? null : $p['name'],
                'email'        => $p['anon'] ? null : $p['email'],
                'request'      => $p['request'],
                'is_anonymous' => $p['anon'],
                'is_private'   => $p['priv'],
                'is_answered'  => $p['ans'],
                'answered_at'  => $p['ans'] ? now()->subDays(2) : null,
                'admin_notes'  => $p['ans'] ? 'Praise report shared in service. Glory to God!' : null,
                'created_at'   => now()->subDays($i + 1),
            ]);
        }
    }

    // ── Communication (templates, audiences, broadcasts) ─────────────────────────

    private function seedBroadcasts(): void
    {
        $admin = $this->u['admin']?->id;

        BroadcastTemplate::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'name' => 'Weekly Newsletter', 'subject' => 'This Week at Grace Assembly',
            'category' => 'Newsletter', 'usage_count' => 12,
            'body' => "Dear {name},\n\nHere is what is happening at Grace Assembly this week...\n\nGrace and peace,\nThe Pastoral Team",
        ]);
        BroadcastTemplate::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'name' => 'Event Reminder', 'subject' => 'Reminder: {event} is coming up',
            'category' => 'Events', 'usage_count' => 5,
            'body' => "Hello {name},\n\nThis is a friendly reminder about {event}. We look forward to seeing you there!",
        ]);
        BroadcastTemplate::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'name' => 'Welcome New Member', 'subject' => 'Welcome to the family!',
            'category' => 'Onboarding', 'usage_count' => 3,
            'body' => "Hi {name},\n\nWe are so glad you have chosen to make Grace Assembly your church home...",
        ]);

        BroadcastAudience::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'name' => 'All Members', 'description' => 'Every active member of the assembly.',
            'audience_type' => 'all_members', 'member_count' => User::where('church_id', $this->church->id)->count(),
        ]);
        BroadcastAudience::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'name' => 'Worship Team', 'description' => 'Members of the Worship & Music department.',
            'audience_type' => 'department', 'audience_config' => ['department_id' => $this->d['worship']->id],
            'member_count' => $this->d['worship']->members()->count(),
        ]);

        Broadcast::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'title' => 'October Newsletter', 'subject' => 'This Week at Grace Assembly',
            'body' => 'Highlights, upcoming events and a word of encouragement for the week ahead.',
            'status' => 'sent', 'audience_type' => 'all_members',
            'recipient_count' => 11, 'delivered_count' => 11, 'failed_count' => 0,
            'sent_at' => now()->subDays(6),
        ]);
        Broadcast::create([
            'church_id' => $this->church->id, 'created_by' => $admin,
            'title' => 'Easter Convention Invitation', 'subject' => 'You are invited: Easter Convention 2026',
            'body' => 'Join us for three glorious days as we celebrate the resurrection of our Lord.',
            'status' => 'draft', 'audience_type' => 'all_members',
        ]);
    }
}
