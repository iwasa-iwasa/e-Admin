<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('email')
                ->constrained('departments')->onDelete('set null');
            $table->enum('role_type', ['member', 'department_admin', 'company_admin'])
                ->default('member')->after('role');
            
            $table->index('department_id', 'idx_users_department');
            $table->index('role_type', 'idx_users_role_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex('idx_users_department');
            $table->dropIndex('idx_users_role_type');
            $table->dropColumn(['department_id', 'role_type']);
        });
    }
};
