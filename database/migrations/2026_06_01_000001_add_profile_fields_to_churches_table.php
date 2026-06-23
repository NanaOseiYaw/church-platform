<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            // Extended identity fields
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('tagline');
            $table->text('mission')->nullable()->after('description');
            $table->text('vision')->nullable()->after('mission');
            $table->unsignedSmallInteger('founded_year')->nullable()->after('vision');
            $table->string('registration_number')->nullable()->after('founded_year');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'description',
                'mission',
                'vision',
                'founded_year',
                'registration_number',
            ]);
        });
    }
};
