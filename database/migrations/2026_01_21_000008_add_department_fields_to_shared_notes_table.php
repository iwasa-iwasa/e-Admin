<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('shared_notes', 'visibility_type')) {
                $table->enum('visibility_type', ['public', 'department', 'custom', 'private'])
                    ->default('custom')->after('content');
            }
            if (!Schema::hasColumn('shared_notes', 'owner_department_id')) {
                $table->foreignId('owner_department_id')->nullable()->after('visibility_type')
                    ->constrained('departments')->onDelete('set null');
            }
            if (!Schema::hasColumn('shared_notes', 'version')) {
                $table->integer('version')->default(0)->after('author_id');
            }
        });
        
        if (DB::getDriverName() !== 'sqlite') {
            $indexesFound = [];
            if (DB::getDriverName() === 'mysql') {
                $indexesFound = collect(DB::select('SHOW INDEX FROM shared_notes'))->pluck('Key_name')->toArray();
            }

            Schema::table('shared_notes', function (Blueprint $table) use ($indexesFound) {
                if (!in_array('idx_notes_visibility_dept', $indexesFound)) {
                    $table->index(['visibility_type', 'owner_department_id'], 'idx_notes_visibility_dept');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('shared_notes', function (Blueprint $table) {
            $table->dropForeign(['owner_department_id']);
            $table->dropIndex('idx_notes_visibility_dept');
            $table->dropColumn(['visibility_type', 'owner_department_id', 'version']);
        });
    }
};
