<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make uploaded_by nullable so that deleting a user does not cause an FK
 * violation on rows in the files table (the constraint uses SET NULL).
 *
 * SQLite does not support dropping/re-adding FK constraints inline — the
 * column change is still applied, but FK management is skipped on that driver
 * (SQLite ignores FK constraints unless PRAGMA foreign_keys = ON anyway).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $isSqlite = DB::getDriverName() === 'sqlite';

            if (! $isSqlite) {
                // Drop the existing RESTRICT/NO ACTION constraint before changing nullability
                $table->dropForeign(['uploaded_by']);
            }

            // Allow NULL so a deleted user's uploads keep their DB rows
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();

            if (! $isSqlite) {
                // Re-add FK: SET NULL when the referenced user is deleted
                $table->foreign('uploaded_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $isSqlite = DB::getDriverName() === 'sqlite';

            if (! $isSqlite) {
                $table->dropForeign(['uploaded_by']);
            }

            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();

            if (! $isSqlite) {
                $table->foreign('uploaded_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            }
        });
    }
};
