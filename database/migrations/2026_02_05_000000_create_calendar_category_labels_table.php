<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_category_labels', function (Blueprint $table) {
            $table->id();
            $table->string('category_key', 50)->unique();
            $table->string('label', 100);
            $table->timestamps();
        });

        // デフォルト値を挿入
        DB::table('calendar_category_labels')->insert([
            ['category_key' => '会議', 'label' => '会議', 'created_at' => now(), 'updated_at' => now()],
            ['category_key' => '業務', 'label' => '業務', 'created_at' => now(), 'updated_at' => now()],
            ['category_key' => '来客', 'label' => '来客', 'created_at' => now(), 'updated_at' => now()],
            ['category_key' => '出張・外出', 'label' => '出張・外出', 'created_at' => now(), 'updated_at' => now()],
            ['category_key' => '休暇', 'label' => '休暇', 'created_at' => now(), 'updated_at' => now()],
            ['category_key' => 'その他', 'label' => 'その他', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_category_labels');
    }
};
