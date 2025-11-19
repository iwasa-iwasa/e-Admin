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
        // 既存のdateデータを23:59:59付きのdatetimeに変換
        DB::statement("UPDATE shared_notes SET deadline = CONCAT(deadline, ' 23:59:59') WHERE deadline IS NOT NULL");
        
        // カラムの型をdatetimeに変更
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->datetime('deadline')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->date('deadline')->nullable()->change();
        });
    }
};