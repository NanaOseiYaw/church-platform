<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // All-day flag (no time component)
            $table->boolean('all_day')->default(false);

            // Granular visibility control
            // 'public'          – anyone can see (logged-in or not, if is_public is also true)
            // 'members_only'    – any authenticated church member
            // 'department_only' – only members of the linked department
            $table->string('visibility', 20)->default('public');

            // Explicit cancellation (status is otherwise derived from timestamps)
            $table->boolean('is_cancelled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['all_day', 'visibility', 'is_cancelled']);
        });
    }
};
