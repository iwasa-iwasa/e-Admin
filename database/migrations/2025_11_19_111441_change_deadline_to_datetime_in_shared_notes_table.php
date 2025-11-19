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
        // 現在のDBドライバー名を取得 (例: 'mysql' または 'pgsql')
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // -------------------------------------------
            // PostgreSQL (Render本番環境) 用の処理
            // -------------------------------------------
            // USING句を使って型変換とデータ加工を同時に行う
            DB::statement("
                ALTER TABLE shared_notes 
                ALTER COLUMN deadline TYPE TIMESTAMP 
                USING (deadline::text || ' 23:59:59')::timestamp
            ");
        } else {
            // -------------------------------------------
            // MySQL / MariaDB (ローカル環境) 用の処理
            // -------------------------------------------
            
            // 1. まず型を DATETIME に変更する (この時点で時刻は 00:00:00 になる)
            Schema::table('shared_notes', function (Blueprint $table) {
                $table->dateTime('deadline')->nullable()->change();
            });

            // 2. その後、時刻を 23:59:59 に更新する
            DB::statement("
                UPDATE shared_notes 
                SET deadline = ADDTIME(deadline, '23:59:59') 
                WHERE deadline IS NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL用: USING句でDateに戻す
            DB::statement("
                ALTER TABLE shared_notes 
                ALTER COLUMN deadline TYPE DATE 
                USING deadline::date
            ");
        } else {
            // MySQL用: 普通に型変更すれば時間は切り捨てられる
            Schema::table('shared_notes', function (Blueprint $table) {
                $table->date('deadline')->nullable()->change();
            });
        }
    }
};