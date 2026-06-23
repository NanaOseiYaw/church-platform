<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Relax existing NOT NULL constraints ─────────────────────────────
        Schema::table('sermons', function (Blueprint $table) {
            // Synced sermons don't have an uploader or a fixed preached date
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
            $table->string('speaker')->nullable()->change();
            $table->timestamp('preached_at')->nullable()->change();
        });

        // ── 2. Add provider-based media fields ─────────────────────────────────
        Schema::table('sermons', function (Blueprint $table) {
            // Provider architecture
            // 'manual' | 'youtube' | 'vimeo' | 'podcast'
            $table->string('provider', 50)->default('manual')->after('church_id');
            $table->string('provider_video_id')->nullable()->after('provider'); // YouTube video ID
            $table->unsignedBigInteger('provider_channel_id')->nullable()->after('provider_video_id');
            $table->foreign('provider_channel_id')
                  ->references('id')
                  ->on('church_channel_connections')
                  ->nullOnDelete();

            // Media URLs
            // embed_url  — iframe embed src (YouTube/Vimeo embed URLs)
            // thumbnail_url — full public URL (YouTube CDN, etc.); replaces local thumbnail
            $table->text('embed_url')->nullable()->after('audio_url');
            $table->text('thumbnail_url')->nullable()->after('embed_url');

            // Organisation
            $table->string('slug')->nullable()->after('title');
            $table->foreignId('series_id')
                  ->nullable()
                  ->after('series')
                  ->constrained('sermon_series')
                  ->nullOnDelete();

            // Visibility: 'public' | 'members_only' | 'unlisted'
            // NULL = fall back to is_public boolean (backward compat)
            $table->string('visibility', 50)->nullable()->after('is_public');

            // Featured flag for homepage hero / pinned sections
            $table->boolean('is_featured')->default(false)->after('visibility');

            // Sync metadata
            $table->timestamp('synced_at')->nullable()->after('preached_at');

            // Future-ready JSON metadata (raw API response, captions, etc.)
            $table->json('metadata')->nullable();

            // Indexes
            $table->index(['church_id', 'provider', 'provider_video_id']);
            $table->index(['church_id', 'is_featured']);
            $table->index(['church_id', 'visibility']);
            $table->unique(['church_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('sermons', function (Blueprint $table) {
            $table->dropForeign(['provider_channel_id']);
            $table->dropForeign(['series_id']);
            $table->dropIndex(['church_id', 'provider', 'provider_video_id']);
            $table->dropIndex(['church_id', 'is_featured']);
            $table->dropIndex(['church_id', 'visibility']);
            $table->dropUnique(['church_id', 'slug']);

            $table->dropColumn([
                'provider', 'provider_video_id', 'provider_channel_id',
                'embed_url', 'thumbnail_url', 'slug', 'series_id',
                'visibility', 'is_featured', 'synced_at', 'metadata',
            ]);

            // Restore NOT NULL on reverted columns
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
            $table->string('speaker')->nullable(false)->change();
            $table->timestamp('preached_at')->nullable(false)->change();
        });
    }
};
