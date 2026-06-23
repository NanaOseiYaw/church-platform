<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index();
            $table->string('name')->nullable();          // null when anonymous
            $table->string('email')->nullable();
            $table->text('request');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_private')->default(false);   // admin-only visibility
            $table->boolean('is_answered')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
