<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'visibility_type')) {
                $table->enum('visibility_type', ['public', 'department', 'custom', 'private'])
                    ->default('custom')->after('category');
            }
            if (!Schema::hasColumn('events', 'owner_department_id')) {
                $table->foreignId('owner_department_id')->nullable()->after('visibility_type')
                    ->constrained('departments')->onDelete('set null');
            }
            if (!Schema::hasColumn('events', 'version')) {
                $table->integer('version')->default(0)->after('created_by');
            }
        });
        
        // SQLiteではインデックスチェックが煩雑なため、テスト環境ではインデックス作成をスキップ
        if (DB::getDriverName() !== 'sqlite') {
            $indexesFound = [];
            if (DB::getDriverName() === 'mysql') {
                $indexesFound = collect(DB::select('SHOW INDEX FROM events'))->pluck('Key_name')->toArray();
            }

            Schema::table('events', function (Blueprint $table) use ($indexesFound) {
                if (!in_array('idx_events_visibility_dept', $indexesFound)) {
                    $table->index(['visibility_type', 'owner_department_id'], 'idx_events_visibility_dept');
                }
                if (!in_array('idx_events_dates', $indexesFound)) {
                    $table->index(['start_date', 'end_date'], 'idx_events_dates');
                }
                if (!in_array('idx_events_created_by', $indexesFound)) {
                    $table->index('created_by', 'idx_events_created_by');
                }
                if (!in_array('idx_events_calendar', $indexesFound)) {
                    $table->index('calendar_id', 'idx_events_calendar');
                }
            });
        }
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
