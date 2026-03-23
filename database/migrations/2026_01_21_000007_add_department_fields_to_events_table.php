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
            if (! Schema::hasColumn('events', 'visibility_type')) {
                $table->enum('visibility_type', ['public', 'department', 'custom', 'private'])
                    ->default('custom')->after('category');
            }
            if (! Schema::hasColumn('events', 'owner_department_id')) {
                $table->foreignId('owner_department_id')->nullable()->after('visibility_type')
                    ->constrained('departments')->onDelete('set null');
            }
            if (! Schema::hasColumn('events', 'version')) {
                $table->integer('version')->default(0)->after('created_by');
            }
        });
        if (DB::getDriverName() !== 'sqlite') {
            $indexes = Schema::getIndexes('events');
            $indexNames = array_column($indexes, 'name');

            Schema::table('events', function (Blueprint $table) use ($indexNames) {
                if (! in_array('idx_events_visibility_dept', $indexNames)) {
                    $table->index(['visibility_type', 'owner_department_id'], 'idx_events_visibility_dept');
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
