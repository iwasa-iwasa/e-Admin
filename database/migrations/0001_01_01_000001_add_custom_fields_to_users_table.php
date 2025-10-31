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
        // 既存の 'users' テーブルにカラムを追加
        Schema::table('users', function (Blueprint $table) {
            // カスタムスキーマの 'username' の代わりにデフォルトの 'name' を使用
            // カスタムスキーマの 'password_hash' の代わりにデフォルトの 'password' を使用
            
            $table->string('department', 100)->default('総務部')->after('password');
            $table->string('role', 50)->default('member')->after('department');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'role', 'is_active']);
        });
    }
};