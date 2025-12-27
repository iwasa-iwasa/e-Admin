<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trash_auto_delete_logs', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->integer('deleted_count');
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trash_auto_delete_logs');
    }
};