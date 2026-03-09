<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $indexesFound = [];
            if (DB::getDriverName() === 'mysql') {
                $indexesFound = collect(DB::select('SHOW INDEX FROM event_participants'))->pluck('Key_name')->toArray();
            }

            Schema::table('event_participants', function (Blueprint $table) use ($indexesFound) {
                if (!in_array('idx_participants_user', $indexesFound)) {
                    $table->index('user_id', 'idx_participants_user');
                }
                if (!in_array('idx_participants_event', $indexesFound)) {
                    $table->index('event_id', 'idx_participants_event');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropIndex('idx_participants_user');
            $table->dropIndex('idx_participants_event');
        });
    }
};
