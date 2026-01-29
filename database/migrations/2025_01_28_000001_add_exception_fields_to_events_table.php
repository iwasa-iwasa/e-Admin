<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_event_id')->nullable()->after('event_id');
            $table->date('original_date')->nullable()->after('parent_event_id');
            $table->boolean('is_exception')->default(false)->after('original_date');
            
            $table->foreign('parent_event_id')->references('event_id')->on('events')->onDelete('cascade');
            $table->index(['parent_event_id', 'original_date']);
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['parent_event_id']);
            $table->dropIndex(['parent_event_id', 'original_date']);
            $table->dropColumn(['parent_event_id', 'original_date', 'is_exception']);
        });
    }
};