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
        Schema::table('surveys', function (Blueprint $table) {
            $table->date('deadline_date')->nullable()->after('deadline');
            $table->time('deadline_time')->nullable()->after('deadline_date');
        });
        
        // 既存のdeadlineデータを分割
        DB::statement("
            UPDATE surveys 
            SET deadline_date = DATE(deadline),
                deadline_time = TIME(deadline)
            WHERE deadline IS NOT NULL
        ");
        
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->timestamp('deadline')->nullable()->after('created_by');
        });
        
        // deadline_dateとdeadline_timeを結合
        DB::statement("
            UPDATE surveys 
            SET deadline = TIMESTAMP(deadline_date, COALESCE(deadline_time, '23:59:59'))
            WHERE deadline_date IS NOT NULL
        ");
        
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['deadline_date', 'deadline_time']);
        });
    }
};
