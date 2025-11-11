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
        Schema::table('calendars', function (Blueprint $table) {
            // 外部キー制約を削除 (制約名が異なる場合は要確認)
            // 一般的な命名規則 'calendars_owner_id_foreign' を想定
            try {
                $table->dropForeign(['owner_id']);
            } catch (\Exception $e) {
                // 制約が存在しない場合のエラーを無視
            }
            // カラムを削除
            $table->dropColumn('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};