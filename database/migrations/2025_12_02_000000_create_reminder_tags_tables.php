<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 個人リマインダー用タグテーブル
        Schema::create('reminder_tags', function (Blueprint $table) {
            $table->id('tag_id');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->string('tag_name', 50);
            $table->timestamps();
            $table->unique(['user_id', 'tag_name']);
        });

        // リマインダーとタグの中間テーブル
        Schema::create('reminder_tag_relations', function (Blueprint $table) {
            $table->foreignId('reminder_id')->constrained('reminders', 'reminder_id')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('reminder_tags', 'tag_id')->onDelete('cascade');
            $table->primary(['reminder_id', 'tag_id']);
        });

        // remindersテーブルからcategoryカラムを削除
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('category', 50)->default('業務');
        });
        
        Schema::dropIfExists('reminder_tag_relations');
        Schema::dropIfExists('reminder_tags');
    }
};
