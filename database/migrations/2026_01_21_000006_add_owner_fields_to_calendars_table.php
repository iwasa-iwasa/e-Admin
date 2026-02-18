<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->enum('owner_type', ['department', 'company'])
                ->default('company')->after('calendar_type');
            $table->foreignId('owner_id')->nullable()->after('owner_type')
                ->constrained('departments')->onDelete('cascade');
            
            $table->index(['owner_type', 'owner_id'], 'idx_calendars_owner');
        });
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex('idx_calendars_owner');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
