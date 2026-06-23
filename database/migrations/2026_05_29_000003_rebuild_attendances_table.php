<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the old polymorphic `attendance` table and replace it with a
 * properly normalised `attendances` table.
 *
 * Each row = one person's attendance at one session.
 * Grouped by session → attendance_sessions table.
 *
 * UUID primary key:
 *   – Future-proof for distributed / import flows.
 *   – Compatible with self-check-in and QR scanning.
 *
 * Future-ready fields:
 *   – check_out_at      → duration / dwell-time analytics
 *   – attendance_score  → engagement scoring
 *   – metadata          → device info, QR payload, GPS coordinates
 */
return new class extends Migration
{
    public function up(): void
    {
        // Remove the old polymorphic table — it had no application data yet.
        Schema::dropIfExists('attendance');

        Schema::create('attendances', function (Blueprint $table) {
            // ── Identity ─────────────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Tenant ───────────────────────────────────────────────────────
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            // ── Session grouping ─────────────────────────────────────────────
            $table->foreignId('session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnDelete();

            // ── Attendee ─────────────────────────────────────────────────────
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // For non-member guests (walk-ins, visitors)
            $table->string('guest_name')->nullable();

            // ── Attendance data ──────────────────────────────────────────────
            // present | absent | late | excused
            $table->string('status')->default('present');

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();    // future dwell-time analytics

            $table->text('notes')->nullable();

            // ── Audit ────────────────────────────────────────────────────────
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // manual | qr | self_checkin | imported
            $table->string('source')->default('manual');

            // ── Future analytics ─────────────────────────────────────────────
            $table->unsignedSmallInteger('attendance_score')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // ── Constraints ──────────────────────────────────────────────────
            // One record per user per session (duplicates become updates)
            $table->unique(['session_id', 'user_id']);

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(['church_id', 'user_id']);
            $table->index(['church_id', 'session_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');

        // Recreate the old table so `migrate:rollback` does not break other rollbacks
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->morphs('attendable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('method')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
