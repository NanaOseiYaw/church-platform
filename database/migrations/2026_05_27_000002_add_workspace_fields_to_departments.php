<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // ->after() is a MySQL-only column-positioning hint; silently ignored
            // on SQLite and PostgreSQL — columns are appended in declaration order.

            // Workspace visibility: public | members_only | private
            $table->string('visibility')->default('public')->after('is_active');

            // Flexible JSON settings bag for future department-specific config.
            // Stored as native JSON on MySQL/PostgreSQL; TEXT on SQLite.
            // Always access via the Eloquent 'array' cast — never via raw JSON_EXTRACT().
            $table->json('settings')->nullable()->after('visibility');

            // Track who created the department
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['visibility', 'settings', 'created_by']);
        });
    }
};
