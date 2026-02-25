<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trash_items', function (Blueprint $table) {
            $table->foreignId('owner_department_id')->nullable()->after('item_id')
                ->constrained('departments')->onDelete('set null');
            $table->string('visibility_type', 20)->nullable()->after('owner_department_id');
            
            $table->index('owner_department_id', 'idx_trash_department');
        });
    }

    public function down(): void
    {
        Schema::table('trash_items', function (Blueprint $table) {
            $table->dropForeign(['owner_department_id']);
            $table->dropIndex('idx_trash_department');
            $table->dropColumn(['owner_department_id', 'visibility_type']);
        });
    }
};
