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
        // Add new columns
        Schema::table('reminders', function (Blueprint $table) {
            $table->date('deadline_date')->nullable()->after('description');
            $table->time('deadline_time')->nullable()->after('deadline_date');
        });

        // Migrate existing data: deadline -> deadline_date with 23:59 time
        \DB::table('reminders')
            ->whereNotNull('deadline')
            ->update([
                'deadline_date' => \DB::raw('deadline'),
                'deadline_time' => '23:59:00'
            ]);

        // Drop old column
        Schema::table('reminders', function (Blueprint $table) {
            if (\DB::connection()->getDriverName() === 'sqlite') {
                return;
            }
            $table->dropColumn('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back old column
        Schema::table('reminders', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('description');
        });

        // Migrate data back: deadline_date -> deadline
        \DB::table('reminders')
            ->whereNotNull('deadline_date')
            ->update([
                'deadline' => \DB::raw('deadline_date')
            ]);

        // Drop new columns
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['deadline_date', 'deadline_time']);
        });
    }
};
