<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the communication and audit tables added after
 * the initial 2026-05-27 performance migration.
 *
 * broadcasts:          church_id + status  (status-filter tabs on Broadcasts/Index)
 *                      church_id + created_at (ordering)
 * broadcast_recipients: broadcast_id + status (delivery count queries in Show page)
 * audit_logs:          church_id + action  (action-filter on Audit/Index)
 *                      church_id + created_at (default ordering)
 * sermons:             church_id + is_public (public sermons listing on /sermons)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Broadcasts ─────────────────────────────────────────────────────────
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->index(['church_id', 'status'],     'broadcasts_church_status_idx');
            $table->index(['church_id', 'created_at'], 'broadcasts_church_created_idx');
        });

        // ── Broadcast Recipients ───────────────────────────────────────────────
        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->index(['broadcast_id', 'status'], 'broadcast_recipients_broadcast_status_idx');
        });

        // ── Audit Logs ─────────────────────────────────────────────────────────
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['church_id', 'action'],     'audit_logs_church_action_idx');
            $table->index(['church_id', 'created_at'], 'audit_logs_church_created_idx');
        });

        // ── Sermons ────────────────────────────────────────────────────────────
        Schema::table('sermons', function (Blueprint $table) {
            $table->index(['church_id', 'is_public'], 'sermons_church_public_idx');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropIndex('broadcasts_church_status_idx');
            $table->dropIndex('broadcasts_church_created_idx');
        });

        Schema::table('broadcast_recipients', function (Blueprint $table) {
            $table->dropIndex('broadcast_recipients_broadcast_status_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_church_action_idx');
            $table->dropIndex('audit_logs_church_created_idx');
        });

        Schema::table('sermons', function (Blueprint $table) {
            $table->dropIndex('sermons_church_public_idx');
        });
    }
};
