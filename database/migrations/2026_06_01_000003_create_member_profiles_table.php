<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();

            // Personal — self-editable
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();         // male|female|other|prefer_not_to_say
            $table->string('marital_status')->nullable(); // single|married|widowed|divorced
            $table->text('address')->nullable();

            // Emergency contact — self-editable
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Church journey — admin-only
            $table->date('membership_date')->nullable();
            $table->date('baptism_date')->nullable();
            $table->date('salvation_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
