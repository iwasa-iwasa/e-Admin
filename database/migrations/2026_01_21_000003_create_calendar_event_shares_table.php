<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('calendars', 'calendar_id')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events', 'event_id')->onDelete('cascade');
            $table->foreignId('shared_by')->constrained('users');
            $table->timestamp('shared_at')->useCurrent();
            
            $table->unique(['calendar_id', 'event_id']);
            $table->index('calendar_id', 'idx_calendar');
            $table->index('event_id', 'idx_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_shares');
    }
};
