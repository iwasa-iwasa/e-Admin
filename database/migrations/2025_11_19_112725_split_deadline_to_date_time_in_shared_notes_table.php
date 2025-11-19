<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->date('deadline_date')->nullable()->after('deadline');
            $table->time('deadline_time')->nullable()->after('deadline_date');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL用: キャスト構文 (::date, ::time) を使用
            DB::statement("
                UPDATE shared_notes 
                SET 
                    deadline_date = deadline::date,
                    deadline_time = deadline::time
                WHERE deadline IS NOT NULL
            ");
        } else {
            // MySQL用: 関数 (DATE(), TIME()) を使用
            DB::statement("
                UPDATE shared_notes 
                SET 
                    deadline_date = DATE(deadline),
                    deadline_time = TIME(deadline)
                WHERE deadline IS NOT NULL
            ");
        }

        Schema::table('shared_notes', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->datetime('deadline')->nullable()->after('priority');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL用: 文字列結合演算子 (||) とキャストを使用
            DB::statement("
                UPDATE shared_notes 
                SET deadline = (deadline_date::text || ' ' || COALESCE(deadline_time::text, '23:59:59'))::timestamp
                WHERE deadline_date IS NOT NULL
            ");
        } else {
            // MySQL用: CONCAT関数を使用
            DB::statement("
                UPDATE shared_notes 
                SET deadline = CONCAT(deadline_date, ' ', COALESCE(deadline_time, '23:59:59'))
                WHERE deadline_date IS NOT NULL
            ");
        }

        Schema::table('shared_notes', function (Blueprint $table) {
            $table->dropColumn(['deadline_date', 'deadline_time']);
        });
    }
};