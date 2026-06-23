<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title');
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft');
            $table->enum('audience_type', [
                'all_members', 'role', 'department', 'event_attendees', 'volunteers', 'saved_audience',
            ]);
            $table->json('audience_config')->nullable();
            $table->unsignedBigInteger('announcement_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('broadcast_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
