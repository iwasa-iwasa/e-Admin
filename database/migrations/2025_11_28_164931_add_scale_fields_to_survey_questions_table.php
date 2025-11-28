<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->integer('scale_min')->nullable()->after('is_required');
            $table->integer('scale_max')->nullable()->after('scale_min');
            $table->string('scale_min_label')->nullable()->after('scale_max');
            $table->string('scale_max_label')->nullable()->after('scale_min_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->dropColumn(['scale_min', 'scale_max', 'scale_min_label', 'scale_max_label']);
        });
    }
};
