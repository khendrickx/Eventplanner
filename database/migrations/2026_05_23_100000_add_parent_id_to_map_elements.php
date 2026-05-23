<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change type from enum to string so new types (e.g. 'group') can be added
        // without a schema change. Validation is handled by form requests.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE map_elements MODIFY COLUMN type VARCHAR(50) NOT NULL");
        }

        Schema::table('map_elements', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('event_plan_id')
                ->constrained('map_elements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('map_elements', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
