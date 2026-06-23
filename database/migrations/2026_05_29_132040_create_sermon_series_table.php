<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermon_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable(); // storage path or external URL

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->timestamps();

            $table->unique(['church_id', 'slug']);
            $table->index(['church_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sermon_series');
    }
};
