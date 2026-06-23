<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: table may already exist from a prior seed or deployment.
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // e.g. 'settings.branding.updated', 'member.role.changed', 'department.created'
            $table->string('action', 100);

            // The Eloquent model class name (optional)
            $table->string('model_type', 80)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();

            // Changed values snapshot
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['church_id', 'created_at']);
            $table->index(['church_id', 'action']);
            $table->index(['church_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
