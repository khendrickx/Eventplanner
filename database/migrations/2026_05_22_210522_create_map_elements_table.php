<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('map_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_plan_id')->nullable()->constrained('event_plans')->nullOnDelete();
            $table->enum('type', ['route', 'marker', 'zone', 'infrastructure']);
            $table->string('subtype')->nullable();
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->json('geometry');
            $table->json('properties')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_elements');
    }
};
