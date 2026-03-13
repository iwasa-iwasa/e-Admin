<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // visibility_typeカラムが存在しない場合はスキップ
        if (!Schema::hasColumn('events', 'visibility_type')) {
            return;
        }

        // カレンダー予定: privateをdepartmentに変更
        DB::statement("
            UPDATE events 
            SET visibility_type = 'department', 
                owner_department_id = (SELECT department_id FROM users WHERE id = events.created_by)
            WHERE visibility_type = 'private'
        ");

        // カレンダー予定: customをdepartmentに変更（カレンダーの種別に応じて）
        DB::statement("
            UPDATE events e
            INNER JOIN calendars c ON e.calendar_id = c.calendar_id
            SET e.visibility_type = CASE 
                WHEN c.owner_type = 'company' THEN 'public'
                WHEN c.owner_type = 'department' THEN 'department'
                ELSE e.visibility_type
            END,
            e.owner_department_id = CASE 
                WHEN c.owner_type = 'department' THEN c.owner_id
                ELSE e.owner_department_id
            END
            WHERE e.visibility_type = 'custom'
        ");

        // 共有メモのvisibility_typeカラムが存在する場合のみ実行
        if (Schema::hasColumn('shared_notes', 'visibility_type')) {
            // 共有メモ: privateをdepartmentに変更
            DB::statement("
                UPDATE shared_notes 
                SET visibility_type = 'department',
                    owner_department_id = (SELECT department_id FROM users WHERE id = shared_notes.author_id)
                WHERE visibility_type = 'private'
            ");

            // 共有メモ: publicをdepartmentに変更（予定に紐づかないもの）
            DB::statement("
                UPDATE shared_notes 
                SET visibility_type = 'department',
                    owner_department_id = (SELECT department_id FROM users WHERE id = shared_notes.author_id)
                WHERE visibility_type = 'public' AND linked_event_id IS NULL
            ");
        }

        // アンケートのvisibility_typeカラムが存在する場合のみ実行
        if (Schema::hasColumn('surveys', 'visibility_type')) {
            // アンケート: privateをdepartmentに変更
            DB::statement("
                UPDATE surveys 
                SET visibility_type = 'department',
                    owner_department_id = (SELECT department_id FROM users WHERE id = surveys.created_by)
                WHERE visibility_type = 'private'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバックは行わない（データの整合性を保つため）
        // 必要に応じて手動で修正
    }
};
