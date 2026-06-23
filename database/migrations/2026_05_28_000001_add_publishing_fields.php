<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Announcements: add visibility + is_featured ────────────────────────
        Schema::table('announcements', function (Blueprint $table) {
            // 4-level visibility — replaces the binary is_church_wide logic
            // 'public'          → shown on public website + dashboard
            // 'members_only'    → any authenticated church member
            // 'department_only' → only that department's members
            // 'private'         → admins / creator only
            $table->string('visibility', 20)->default('public')->after('is_church_wide');

            // Surface on homepage hero / featured section
            $table->boolean('is_featured')->default(false)->after('visibility');
        });

        // Migrate existing announcement visibility from is_church_wide + department_id
        // is_church_wide = true                    → 'public'
        // is_church_wide = false && dept set       → 'department_only'
        // is_church_wide = false && no dept        → 'members_only'
        DB::statement("
            UPDATE announcements
            SET visibility = CASE
                WHEN is_church_wide = 1                              THEN 'public'
                WHEN is_church_wide = 0 AND department_id IS NOT NULL THEN 'department_only'
                ELSE                                                      'members_only'
            END
        ");

        // ── Events: add published_at + is_featured ─────────────────────────────
        Schema::table('events', function (Blueprint $table) {
            // NULL = draft (not yet published); past timestamp = live
            $table->timestamp('published_at')->nullable()->after('is_cancelled');

            // Surface on homepage featured events section
            $table->boolean('is_featured')->default(false)->after('published_at');

            // Add 'private' to the valid set by widening the column (no ENUM used —
            // column is string(20) so no DDL change required; only form/service logic
            // needs updating).
        });

        // All existing events were immediately live — backfill published_at = created_at
        DB::statement('UPDATE events SET published_at = created_at WHERE published_at IS NULL');

        // ── Performance indexes for public-feed queries ────────────────────────
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['church_id', 'visibility', 'published_at'], 'ann_public_feed');
            $table->index(['church_id', 'is_featured', 'published_at'], 'ann_featured_feed');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index(['church_id', 'visibility', 'published_at', 'start_at'], 'evt_public_feed');
            $table->index(['church_id', 'is_featured', 'published_at'], 'evt_featured_feed');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('ann_public_feed');
            $table->dropIndex('ann_featured_feed');
            $table->dropColumn(['visibility', 'is_featured']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('evt_public_feed');
            $table->dropIndex('evt_featured_feed');
            $table->dropColumn(['published_at', 'is_featured']);
        });
    }
};
