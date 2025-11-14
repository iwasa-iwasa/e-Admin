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
        Schema::table('event_recurrence', function (Blueprint $table) {
            $table->json('by_day')->nullable()->after('recurrence_interval'); // For storing weekdays, e.g., ['MO', 'TU']
            $table->integer('by_set_pos')->nullable()->after('by_day'); // For storing position, e.g., 1 for "first", -1 for "last"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_recurrence', function (Blueprint $table) {
            $table->dropColumn(['by_day', 'by_set_pos']);
        });
    }
};