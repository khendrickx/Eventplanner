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
        Schema::create('map_overlays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_plan_id')->nullable()->constrained('event_plans')->nullOnDelete();
            $table->string('name');
            $table->string('image_path');
            $table->json('bounds'); // [[sw_lng, sw_lat], [ne_lng, ne_lat]]
            $table->float('opacity')->default(1.0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_overlays');
    }
};
