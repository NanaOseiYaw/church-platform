<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for tenant-scoped queries.
 *
 * Every paginated dashboard query starts with a church_id WHERE clause.
 * Adding composite indexes ensures these queries use an index seek instead
 * of a full table scan as data grows.
 *
 * Index naming convention: {table}_{columns}_idx
 * Works on MySQL, PostgreSQL, and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Events ─────────────────────────────────────────────────────────────
        Schema::table('events', function (Blueprint $table) {
            // Most common dashboard query: church events ordered by start time
            $table->index(['church_id', 'start_at'], 'events_church_start_idx');

            // Filter: upcoming / ongoing / past (also hits is_cancelled)
            $table->index(['church_id', 'is_cancelled', 'start_at'], 'events_church_cancelled_start_idx');

            // Department-only visibility filter
            $table->index(['church_id', 'visibility'], 'events_church_visibility_idx');
        });

        // ── Announcements ──────────────────────────────────────────────────────
        Schema::table('announcements', function (Blueprint $table) {
            // Feed query: published announcements ordered by published_at DESC
            $table->index(['church_id', 'published_at'], 'announcements_church_published_idx');

            // Pin filter + ordering
            $table->index(['church_id', 'is_pinned', 'published_at'], 'announcements_church_pinned_idx');

            // Audience filter (church-wide vs department-scoped)
            $table->index(['church_id', 'is_church_wide'], 'announcements_church_wide_idx');
        });

        // ── Tasks ──────────────────────────────────────────────────────────────
        Schema::table('tasks', function (Blueprint $table) {
            // Dashboard widget + "active tasks" tab
            $table->index(['church_id', 'status'], 'tasks_church_status_idx');

            // "My Tasks" filter
            $table->index(['church_id', 'assigned_to', 'status'], 'tasks_church_assignee_status_idx');

            // Overdue check (scheduled command + overdue tab)
            $table->index(['church_id', 'due_at', 'status'], 'tasks_church_due_status_idx');
        });

        // ── Departments ────────────────────────────────────────────────────────
        Schema::table('departments', function (Blueprint $table) {
            // Active departments dropdown — queried on every create/edit form
            $table->index(['church_id', 'is_active'], 'departments_church_active_idx');
        });

        // ── Announcement reads ─────────────────────────────────────────────────
        // AnnouncementService::unreadCount() fires on every page via Inertia share()
        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->index(['user_id', 'announcement_id'], 'announcement_reads_user_announcement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_church_start_idx');
            $table->dropIndex('events_church_cancelled_start_idx');
            $table->dropIndex('events_church_visibility_idx');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_church_published_idx');
            $table->dropIndex('announcements_church_pinned_idx');
            $table->dropIndex('announcements_church_wide_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_church_status_idx');
            $table->dropIndex('tasks_church_assignee_status_idx');
            $table->dropIndex('tasks_church_due_status_idx');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex('departments_church_active_idx');
        });

        Schema::table('announcement_reads', function (Blueprint $table) {
            $table->dropIndex('announcement_reads_user_announcement_idx');
        });
    }
};
