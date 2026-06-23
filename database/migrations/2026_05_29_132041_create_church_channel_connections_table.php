<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();

            // Provider identifier — 'youtube' | 'vimeo' | 'podcast' (extensible)
            $table->string('provider', 50)->default('youtube');

            // Provider-side channel identifiers
            $table->string('channel_id');          // e.g. UCxxxxxxxxxxxxxxxxxxxxxxxx
            $table->string('channel_title')->nullable();
            $table->string('channel_thumbnail')->nullable();
            $table->text('channel_description')->nullable();
            $table->string('uploads_playlist_id')->nullable(); // YouTube "uploads" playlist

            // Cached stats (refreshed on each sync)
            $table->unsignedInteger('subscriber_count')->nullable();
            $table->unsignedInteger('video_count')->nullable();

            // Sync control
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->unsignedSmallInteger('sync_frequency_hours')->default(24);

            // Extensible settings (custom API key override, etc.)
            $table->json('settings')->nullable();

            $table->timestamps();

            // Each church may have one connection per provider+channel
            $table->unique(['church_id', 'provider', 'channel_id']);
            $table->index(['church_id', 'provider', 'is_active']);
            $table->index('next_sync_at'); // for the scheduler query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_channel_connections');
    }
};
