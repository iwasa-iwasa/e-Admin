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
        Schema::create('trash_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('item_type'); // 'shared_note', 'event', 'survey' etc.
            $table->string('item_id'); // 元のアイテムのID
            $table->string('original_title'); // 元のタイトル
            $table->timestamp('deleted_at');
            $table->timestamp('permanent_delete_at'); // 完全削除予定日
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trash_items');
    }
};
