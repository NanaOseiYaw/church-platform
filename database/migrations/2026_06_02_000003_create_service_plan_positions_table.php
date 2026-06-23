<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_plan_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serving_position_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('service_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_plan_positions');
    }
};
