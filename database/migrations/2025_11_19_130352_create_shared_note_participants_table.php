<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_note_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('note_id')->references('note_id')->on('shared_notes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_note_participants');
    }
};