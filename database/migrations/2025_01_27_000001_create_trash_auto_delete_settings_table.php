<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trash_auto_delete_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('auto_delete_period', ['disabled', '1_minute', '1_month', '3_months', '6_months', '1_year'])->default('disabled');
            $table->timestamps();
        });
        
        // デフォルト設定を挿入
        DB::table('trash_auto_delete_settings')->insert([
            'auto_delete_period' => 'disabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trash_auto_delete_settings');
    }
};