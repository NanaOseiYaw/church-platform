<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ->after() is a MySQL-only column-positioning hint.
            // SQLite and PostgreSQL silently ignore it; columns are appended in
            // declaration order. Do not rely on physical column order in queries.
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->string('avatar')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('timezone')->default('UTC')->after('phone');
            $table->string('two_factor_secret')->nullable()->after('timezone');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
            $table->dropColumn(['church_id', 'avatar', 'phone', 'timezone', 'two_factor_secret', 'deleted_at']);
        });
    }
};
