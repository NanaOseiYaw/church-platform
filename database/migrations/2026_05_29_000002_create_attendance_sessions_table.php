<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance sessions represent a single instance of attendance tracking —
 * a Sunday service, a department meeting, a rehearsal, etc.
 *
 * Individual per-member records live in `attendances`.
 * Sessions group those records so you can ask "who attended the 1 Jun service?".
 *
 * Future extensions:
 *   – check_in_token  → QR/self-check-in flow
 *   – metadata JSON   → location coordinates, device context, QR payload
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();

            // ── Tenant + optional context ───────────────────────────────────────
            $table->foreignId('church_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('event_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── Descriptive fields ─────────────────────────────────────────────
            $table->string('title');

            // service | meeting | rehearsal | outreach | volunteer | other
            $table->string('type')->default('meeting');

            $table->text('description')->nullable();

            // ── Timing ────────────────────────────────────────────────────────
            $table->dateTime('scheduled_at');
            $table->dateTime('ended_at')->nullable();

            // ── Lifecycle ─────────────────────────────────────────────────────
            // planned | active | completed | cancelled
            $table->string('status')->default('planned');

            // Enables self check-in / QR flows (future)
            $table->boolean('check_in_enabled')->default(false);

            // Unique token for QR-based or URL-based self check-in (future)
            $table->string('check_in_token')->nullable()->unique();

            // Free-form JSON bucket: location coordinates, device metadata, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // ── Indexes for common filters ─────────────────────────────────────
            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'scheduled_at']);
            $table->index(['church_id', 'department_id']);
            $table->index(['church_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
