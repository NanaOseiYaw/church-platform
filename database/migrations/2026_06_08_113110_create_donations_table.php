<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('fund_id', 50)->default('general');
            $table->string('stripe_session_id')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 10)->default('usd');
            $table->string('donor_email')->nullable();
            $table->string('donor_name')->nullable();
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
