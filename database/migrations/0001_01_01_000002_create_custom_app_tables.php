<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 依存されるテーブル (note_tags) から先に作成します。
     * users, password_reset_tokens, sessions はデフォルトマイグレーションで作成済みとします。
     */
    public function up(): void
    {
        // 1. note_tags
        Schema::create('note_tags', function (Blueprint $table) {
            $table->id('tag_id');
            $table->string('tag_name', 50)->unique();
        });

        // 2. shared_notes (depends on users)
        Schema::create('shared_notes', function (Blueprint $table) {
            $table->id('note_id');
            $table->string('title');
            $table->text('content')->nullable();
            // 'users' テーブルの 'id' を参照
            $table->foreignId('author_id')->constrained('users', 'id'); 
            $table->string('color', 50)->default('yellow');
            $table->string('priority', 20)->default('medium');
            $table->date('deadline')->nullable();
            $table->boolean('pinned')->default(false);
            $table->boolean('is_deleted')->default(false)->index();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->index('created_at');
        });

        // 3. note_tag_relations (depends on shared_notes, note_tags)
        Schema::create('note_tag_relations', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained('shared_notes', 'note_id')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('note_tags', 'tag_id')->onDelete('cascade');
            $table->primary(['note_id', 'tag_id']);
        });

        // 4. calendars (depends on users)
        Schema::create('calendars', function (Blueprint $table) {
            $table->id('calendar_id');
            $table->string('calendar_name', 100);
            $table->string('calendar_type', 50);
            // 'users' テーブルの 'id' を参照
            $table->foreignId('owner_id')->nullable()->constrained('users', 'id');
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. events (depends on calendars, users)
        Schema::create('events', function (Blueprint $table) {
            $table->id('event_id');
            $table->foreignId('calendar_id')->constrained('calendars', 'calendar_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('category', 50)->default('会議');
            $table->string('importance', 20)->default('中');
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->date('end_date');
            $table->time('end_time')->nullable();
            $table->boolean('is_all_day')->default(false);
            // 'users' テーブルの 'id' を参照
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->boolean('is_deleted')->default(false)->index();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['start_date', 'end_date'], 'idx_events_dates');
        });

        // 6. event_participants (depends on events, users)
        Schema::create('event_participants', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('events', 'event_id')->onDelete('cascade');
            // 'users' テーブルの 'id' を参照 (カラム名が 'user_id' のため constrained() のみでOK)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('response_status', 20)->default('pending');
            $table->primary(['event_id', 'user_id']);
        });

        // 7. event_recurrence (depends on events)
        Schema::create('event_recurrence', function (Blueprint $table) {
            $table->id('recurrence_id');
            $table->foreignId('event_id')->unique()->constrained('events', 'event_id')->onDelete('cascade');
            $table->string('recurrence_type', 20);
            $table->integer('recurrence_interval')->default(1);
            $table->string('recurrence_unit', 20)->nullable();
            $table->date('end_date')->nullable();
        });

        // 8. event_attachments (depends on events)
        Schema::create('event_attachments', function (Blueprint $table) {
            $table->id('attachment_id');
            $table->foreignId('event_id')->constrained('events', 'event_id')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->integer('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });

        // 9. reminders (depends on users)
        Schema::create('reminders', function (Blueprint $table) {
            $table->id('reminder_id');
            // 'users' テーブルの 'id' を参照
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('deadline')->index();
            $table->string('category', 50)->default('業務');
            $table->boolean('completed')->default(false)->index();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        // 10. surveys (depends on users)
        Schema::create('surveys', function (Blueprint $table) {
            $table->id('survey_id');
            $table->string('title');
            $table->text('description')->nullable();
            // 'users' テーブルの 'id' を参照
            $table->foreignId('created_by')->constrained('users', 'id');
            $table->timestamp('deadline')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        // 11. survey_questions (depends on surveys)
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->foreignId('survey_id')->constrained('surveys', 'survey_id')->onDelete('cascade');
            $table->text('question_text');
            $table->string('question_type', 50);
            $table->boolean('is_required')->default(false);
            $table->integer('display_order');
        });

        // 12. survey_question_options (depends on survey_questions)
        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id('option_id');
            $table->foreignId('question_id')->constrained('survey_questions', 'question_id')->onDelete('cascade');
            $table->string('option_text');
            $table->integer('display_order');
        });

        // 13. survey_responses (depends on surveys, users)
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id('response_id');
            $table->foreignId('survey_id')->constrained('surveys', 'survey_id')->onDelete('cascade');
            // 'users' テーブルの 'id' を参照
            $table->foreignId('respondent_id')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamp('submitted_at')->useCurrent();
            $table->unique(['survey_id', 'respondent_id'], 'unique_survey_respondent');
        });

        // 14. survey_answers (depends on survey_responses, survey_questions, survey_question_options)
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->foreignId('response_id')->constrained('survey_responses', 'response_id')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('survey_questions', 'question_id')->onDelete('cascade');
            $table->text('answer_text')->nullable();
            $table->foreignId('selected_option_id')->nullable()->constrained('survey_question_options', 'option_id')->onDelete('set null');
        });

        // 15. notifications (depends on users)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            // 'users' テーブルの 'id' を参照
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade')->index();
            $table->string('notification_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->string('title');
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 17. activity_logs (depends on users)
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id('log_id');
            // 'users' テーブルの 'id' を参照
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->string('action_type', 50);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('details')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 18. user_preferences (depends on users)
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id('preference_id');
            // 'users' テーブルの 'id' を参照
            $table->foreignId('user_id')->unique()->constrained('users', 'id')->onDelete('cascade');
            $table->string('calendar_view_mode', 20)->default('month');
            $table->string('note_sort_order', 50)->default('priority-deadline');
            $table->string('theme', 20)->default('light');
            $table->boolean('notifications_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * 外部キー制約に違反しないよう、依存しているテーブルから逆順に削除します。
     */
    public function down(): void
    {
        // 18. user_preferences
        Schema::dropIfExists('user_preferences');
        
        // 17. activity_logs
        Schema::dropIfExists('activity_logs');
        
        // 15. notifications
        Schema::dropIfExists('notifications');
        
        // 14. survey_answers
        Schema::dropIfExists('survey_answers');
        
        // 13. survey_responses
        Schema::dropIfExists('survey_responses');
        
        // 12. survey_question_options
        Schema::dropIfExists('survey_question_options');
        
        // 11. survey_questions
        Schema::dropIfExists('survey_questions');
        
        // 10. surveys
        Schema::dropIfExists('surveys');
        
        // 9. reminders
        Schema::dropIfExists('reminders');
        
        // 8. event_attachments
        Schema::dropIfExists('event_attachments');
        
        // 7. event_recurrence
        Schema::dropIfExists('event_recurrence');
        
        // 6. event_participants
        Schema::dropIfExists('event_participants');
        
        // 5. events
        Schema::dropIfExists('events');
        
        // 4. calendars
        Schema::dropIfExists('calendars');
        
        // 3. note_tag_relations
        Schema::dropIfExists('note_tag_relations');
        
        // 2. shared_notes
        Schema::dropIfExists('shared_notes');
        
        // 1. note_tags
        Schema::dropIfExists('note_tags');
    }
};