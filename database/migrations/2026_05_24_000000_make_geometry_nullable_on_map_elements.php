<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_elements', function (Blueprint $table) {
            $table->json('geometry')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('map_elements', function (Blueprint $table) {
            $table->json('geometry')->nullable(false)->change();
        });
    }
};
