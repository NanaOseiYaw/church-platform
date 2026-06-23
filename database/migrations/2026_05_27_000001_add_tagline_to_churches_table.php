<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            // ->after() is a MySQL-only positioning hint; ignored on SQLite/PostgreSQL.
            $table->string('tagline')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('tagline');
        });
    }
};
