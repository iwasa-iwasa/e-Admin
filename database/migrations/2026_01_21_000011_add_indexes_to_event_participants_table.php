<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 既存のインデックスをチェック
        $indexes = DB::select("SHOW INDEX FROM event_participants WHERE Key_name IN ('idx_participants_user', 'idx_participants_event')");
        $existingIndexes = collect($indexes)->pluck('Key_name')->unique()->toArray();
        
        Schema::table('event_participants', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_participants_user', $existingIndexes)) {
                $table->index('user_id', 'idx_participants_user');
            }
            if (!in_array('idx_participants_event', $existingIndexes)) {
                $table->index('event_id', 'idx_participants_event');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropIndex('idx_participants_user');
            $table->dropIndex('idx_participants_event');
        });
    }
};
