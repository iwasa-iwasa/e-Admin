<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // テーブルが存在しない場合は作成
        if (!Schema::hasTable('user_preferences')) {
            Schema::create('user_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
                $table->boolean('show_company_calendar_in_trash')->default(false);
                $table->enum('notification_scope', ['department', 'company', 'both'])->default('both');
                $table->timestamps();
                
                $table->index('user_id', 'idx_user_preferences_user');
            });
        } else {
            // テーブルが存在する場合は不足しているカラムを追加
            Schema::table('user_preferences', function (Blueprint $table) {
                if (!Schema::hasColumn('user_preferences', 'show_company_calendar_in_trash')) {
                    $table->boolean('show_company_calendar_in_trash')->default(false)->after('user_id');
                }
                
                if (!Schema::hasColumn('user_preferences', 'notification_scope')) {
                    $table->enum('notification_scope', ['department', 'company', 'both'])->default('both')->after('show_company_calendar_in_trash');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_preferences')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                if (Schema::hasColumn('user_preferences', 'show_company_calendar_in_trash')) {
                    $table->dropColumn('show_company_calendar_in_trash');
                }
                if (Schema::hasColumn('user_preferences', 'notification_scope')) {
                    $table->dropColumn('notification_scope');
                }
            });
        }
    }
};
