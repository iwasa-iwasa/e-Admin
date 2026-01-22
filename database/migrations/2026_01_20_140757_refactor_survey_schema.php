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
        Schema::disableForeignKeyConstraints();

        // 1. Drop all survey-related tables to ensure a clean slate
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_respondents');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');

        Schema::enableForeignKeyConstraints();

        // 2. Create `surveys` table
        Schema::create('surveys', function (Blueprint $table) {
            $table->id('survey_id'); // Primary Key
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by'); // FK to users
            $table->date('deadline_date')->nullable();
            $table->time('deadline_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1); // Versioning
            $table->boolean('is_deleted')->default(false);
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();

            // Foreign Key Constraint (assuming users.id exists)
            // We verify user table existence first to avoid hard crashes if running in isolation,
            // though in a full app users should exist.
            if (Schema::hasTable('users')) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            }
        });

        // 3. Create `survey_questions` table
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id('question_id'); // Primary Key
            $table->uuid('uuid')->unique(); // Stable ID
            $table->unsignedBigInteger('survey_id');
            $table->text('question_text');
            $table->string('question_type');
            $table->boolean('is_required')->default(true);
            $table->integer('display_order');
            
            // Scale fields (restoring from previous migrations)
            $table->integer('scale_min')->nullable();
            $table->integer('scale_max')->nullable();
            $table->string('scale_min_label')->nullable();
            $table->string('scale_max_label')->nullable();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->onDelete('cascade');
        });

        // 4. Create `survey_question_options` table
        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id('option_id'); // Primary Key
            $table->uuid('uuid')->unique(); // Stable ID
            $table->unsignedBigInteger('question_id');
            $table->text('option_text');
            $table->integer('display_order');

            $table->foreign('question_id')->references('question_id')->on('survey_questions')->onDelete('cascade');
        });

        // 5. Create `survey_respondents` table (Restoring assignment functionality)
        Schema::create('survey_respondents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            $table->foreign('survey_id')->references('survey_id')->on('surveys')->onDelete('cascade');
            if (Schema::hasTable('users')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
            $table->unique(['survey_id', 'user_id']);
        });

        // 6. Create `survey_responses` table (New structure)
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id('response_id'); // Primary Key
            $table->unsignedBigInteger('survey_id');
            $table->unsignedBigInteger('respondent_id'); // User ID
            $table->json('answers')->nullable(); // JSON storage for answers
            $table->string('status')->default('draft'); // draft, submitted
            $table->integer('survey_version')->default(1); // Track which version was answered
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps(); // created_at, updated_at

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->onDelete('cascade');
            if (Schema::hasTable('users')) {
                $table->foreign('respondent_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_respondents');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('survey_answers'); // In case strict rollback
        Schema::enableForeignKeyConstraints();
    }
};
