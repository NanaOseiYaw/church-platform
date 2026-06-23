<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an AttendanceSession to a ServicePlan (optional, one-to-one).
 *
 * When linked:
 *   – The session's expected-attendee list is seeded from the plan's
 *     non-declined volunteer assignments instead of the whole church.
 *   – Both pages (plan Show + attendance Show) cross-link to each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('service_plan_id')
                  ->nullable()
                  ->after('event_id')
                  ->constrained('service_plans')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\ServicePlan::class);
            $table->dropColumn('service_plan_id');
        });
    }
};
