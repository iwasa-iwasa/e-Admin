<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 既存カラムをチェック
        $columns = DB::select("SHOW COLUMNS FROM events WHERE Field IN ('visibility_type', 'owner_department_id', 'version')");
        $existingColumns = collect($columns)->pluck('Field')->toArray();
        
        Schema::table('events', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('visibility_type', $existingColumns)) {
                $table->enum('visibility_type', ['public', 'department', 'custom', 'private'])
                    ->default('custom')->after('category');
            }
            if (!in_array('owner_department_id', $existingColumns)) {
                $table->foreignId('owner_department_id')->nullable()->after('visibility_type')
                    ->constrained('departments')->onDelete('set null');
            }
            if (!in_array('version', $existingColumns)) {
                $table->integer('version')->default(0)->after('created_by');
            }
        });
        
        // インデックスをチェックして追加
        $indexes = DB::select("SHOW INDEX FROM events WHERE Key_name IN ('idx_events_visibility_dept', 'idx_events_dates', 'idx_events_created_by', 'idx_events_calendar')");
        $existingIndexes = collect($indexes)->pluck('Key_name')->unique()->toArray();
        
        Schema::table('events', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_events_visibility_dept', $existingIndexes)) {
                $table->index(['visibility_type', 'owner_department_id'], 'idx_events_visibility_dept');
            }
            if (!in_array('idx_events_dates', $existingIndexes)) {
                $table->index(['start_date', 'end_date'], 'idx_events_dates');
            }
            if (!in_array('idx_events_created_by', $existingIndexes)) {
                $table->index('created_by', 'idx_events_created_by');
            }
            if (!in_array('idx_events_calendar', $existingIndexes)) {
                $table->index('calendar_id', 'idx_events_calendar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['owner_department_id']);
            $table->dropIndex('idx_events_visibility_dept');
            $table->dropColumn(['visibility_type', 'owner_department_id', 'version']);
        });
    }
};
