<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->foreignId('linked_event_id')->nullable()->after('author_id')->constrained('events', 'event_id')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->dropForeign(['linked_event_id']);
            $table->dropColumn('linked_event_id');
        });
    }
};
