<?php

/**
 * Centralized permission registry for the Church Platform.
 *
 * This file is the SINGLE SOURCE OF TRUTH for:
 *   - Every permission name that exists in the system
 *   - Which roles receive which permissions
 *
 * The RolesAndPermissionsSeeder reads this config exclusively — never
 * hardcode permission strings anywhere else.
 *
 * Naming convention: {resource}.{action}
 * Use '*' in a role's permission list to grant every seeded permission.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Definitions
    |--------------------------------------------------------------------------
    | Grouped by resource. Every string here becomes a Spatie permission row.
    */

    'permissions' => [

        'church' => [
            'church.view',   // View church profile / branding
            'church.edit',   // Edit church settings
        ],

        'departments' => [
            'departments.view',           // Browse department list and details
            'departments.create',         // Create a new department
            'departments.edit',           // Edit any department
            'departments.delete',         // Delete a department
            'departments.manage_members', // Add / remove / change member roles
        ],

        'events' => [
            'events.view',            // Browse event list and details
            'events.create',          // Create a new event
            'events.edit',            // Edit events (coordinator: own events only)
            'events.delete',          // Delete events (admin-level; also gates admin edit)
            'events.rsvp',            // RSVP to events
            'events.track_attendance',// Mark attendance against an event (legacy hook)
        ],

        'announcements' => [
            'announcements.view',    // Read announcements
            'announcements.create',  // Draft new announcements
            'announcements.edit',    // Edit announcements (coordinator: own only)
            'announcements.delete',  // Delete announcements (admin-level; also gates admin edit/publish)
            'announcements.publish', // Publish / unpublish announcements
            'announcements.pin',     // Pin an announcement to the top
        ],

        'tasks' => [
            'tasks.view_own',  // View tasks assigned to or created by self
            'tasks.view_all',  // View all tasks in the church
            'tasks.create',    // Create new tasks
            'tasks.edit',      // Edit tasks
            'tasks.delete',    // Delete tasks
            'tasks.assign',    // Assign tasks to other members
        ],

        'sermons' => [
            'sermons.view',            // Browse sermon archive
            'sermons.upload',          // Upload / create a sermon (maps to Policy::create)
            'sermons.edit',            // Edit sermon metadata (feature, visibility, etc.)
            'sermons.delete',          // Delete sermons
            'sermons.manage_channels', // Connect/disconnect YouTube channels + trigger syncs
        ],

        'media' => [
            'media.view',   // Browse attached files
            'media.upload', // Upload files to any attachable resource
            'media.delete', // Delete any file in the church (own files are always deletable)
        ],

        'members' => [
            'members.view',   // View member list and profiles
            'members.edit',   // Edit member profiles / roles
        ],

        'attendance' => [
            'attendance.view',   // View attendance sessions and records
            'attendance.manage', // Create sessions, mark attendance, change lifecycle status
            'attendance.delete', // Hard-delete a session and all its records (admin only)
        ],

        'reports' => [
            'reports.view', // Access analytics and report pages
        ],

        'audit' => [
            'audit.view', // Read the audit log
        ],

        'scheduling' => [
            'scheduling.manage',
            'scheduling.view',
        ],

        'communication' => [
            'communication.view',      // See Communication Center
            'communication.send',      // Send to any audience
            'communication.send_dept', // Send to own department only
            'communication.manage',    // Templates + saved audiences
            'communication.delete',    // Delete broadcasts
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Assignments
    |--------------------------------------------------------------------------
    | Each key is a Spatie role name.
    | Use '*' to grant every permission defined above (super_admin pattern).
    | Otherwise list exactly the permission strings to assign to that role.
    |
    | The seeder calls syncPermissions() so changes here are applied on re-seed.
    */

    'roles' => [

        // Has every permission; also bypassed globally by Gate::before in AppServiceProvider.
        'super_admin' => '*',

        // Full control of one church tenant.
        'church_admin' => [
            'church.view', 'church.edit',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete', 'departments.manage_members',
            'events.view', 'events.create', 'events.edit', 'events.delete', 'events.rsvp', 'events.track_attendance',
            'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete', 'announcements.publish', 'announcements.pin',
            'tasks.view_own', 'tasks.view_all', 'tasks.create', 'tasks.edit', 'tasks.delete', 'tasks.assign',
            'sermons.view', 'sermons.upload', 'sermons.edit', 'sermons.delete', 'sermons.manage_channels',
            'media.view', 'media.upload', 'media.delete',
            'members.view', 'members.edit',
            'attendance.view', 'attendance.manage', 'attendance.delete',
            'reports.view',
            'audit.view',
            'scheduling.manage', 'scheduling.view',
            'communication.view', 'communication.send', 'communication.send_dept', 'communication.manage', 'communication.delete',
        ],

        // Department-level staff. Can manage their own department's sessions/content.
        // Attendance: can view all sessions, manage sessions for their own departments.
        // Cannot delete sessions, members, or sermons.
        // NOTE: tasks.view_own is included because view_all implies view_own, and
        //       TaskPolicy::viewAny checks for tasks.view_own.
        'coordinator' => [
            'departments.view', 'departments.manage_members',
            'events.view', 'events.create', 'events.edit', 'events.rsvp', 'events.track_attendance',
            'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.publish',
            'tasks.view_own', 'tasks.view_all', 'tasks.create', 'tasks.edit', 'tasks.assign',
            'sermons.view', 'sermons.edit',
            'media.view', 'media.upload',
            'members.view',
            'attendance.view', 'attendance.manage',
            'scheduling.manage', 'scheduling.view',
            'audit.view',
            'reports.view',
            'communication.view', 'communication.send_dept', 'communication.manage',
        ],

        // Sub-coordinator: can create and view own content, attends events, limited management.
        // departments.manage_members is included because assistant_coordinators hold a
        // leadership pivot role and should be able to manage their department's membership.
        // The DepartmentPolicy::manageMembers gate enforces the pivot-role scope, so this
        // permission does not grant church-wide member management.
        'assistant_coordinator' => [
            'departments.view', 'departments.manage_members',
            'events.view', 'events.rsvp', 'events.create',
            'announcements.view', 'announcements.create',
            'tasks.view_own', 'tasks.create',
            'sermons.view',
            'media.view', 'media.upload',
            'members.view',
            'attendance.view',
            'scheduling.view',
            'communication.view',
        ],

        // Standard authenticated church member.
        // Members access their own attendance history via policy logic, not a permission gate.
        // scheduling.view is required so that assigned volunteers can reach /dashboard/scheduling/my-schedule
        // and so the sidebar "My Schedule" link is visible to them after being assigned.
        'member' => [
            'departments.view',   // Browse departments they belong to; required for notification links
            'events.view', 'events.rsvp',
            'announcements.view',
            'tasks.view_own',
            'sermons.view',
            'media.view',
            'members.view',
            'scheduling.view',    // View own assignments + My Schedule page
        ],
    ],

];
