<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_audiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('audience_type', [
                'all_members', 'role', 'department', 'event_attendees', 'volunteers',
            ]);
            $table->json('audience_config')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_audiences');
    }
};
