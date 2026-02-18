<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('event_id')->nullable()->constrained('events', 'event_id');
            $table->foreignId('calendar_id')->nullable()->constrained('calendars', 'calendar_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('action', 'idx_action');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
