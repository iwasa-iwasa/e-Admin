<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columns = DB::select("SHOW COLUMNS FROM surveys WHERE Field IN ('visibility_type', 'owner_department_id', 'version')");
        $existingColumns = collect($columns)->pluck('Field')->toArray();
        
        Schema::table('surveys', function (Blueprint $table) use ($existingColumns) {
            if (!in_array('visibility_type', $existingColumns)) {
                $table->enum('visibility_type', ['public', 'department', 'custom', 'private'])
                    ->default('custom')->after('description');
            }
            if (!in_array('owner_department_id', $existingColumns)) {
                $table->foreignId('owner_department_id')->nullable()->after('visibility_type')
                    ->constrained('departments')->onDelete('set null');
            }
            if (!in_array('version', $existingColumns)) {
                $table->integer('version')->default(0)->after('created_by');
            }
        });
        
        $indexes = DB::select("SHOW INDEX FROM surveys WHERE Key_name = 'idx_surveys_visibility_dept'");
        if (empty($indexes)) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->index(['visibility_type', 'owner_department_id'], 'idx_surveys_visibility_dept');
            });
        }
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropForeign(['owner_department_id']);
            $table->dropIndex('idx_surveys_visibility_dept');
            $table->dropColumn(['visibility_type', 'owner_department_id', 'version']);
        });
    }
};
