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
            // ユーザーとノートのピン留め関係を管理する中間テーブルを作成
            Schema::create('pinned_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('note_id')->constrained(table: 'shared_notes', column: 'note_id')->onDelete('cascade');
                $table->timestamps();

                // 同一ユーザーが同じノートを複数回ピン留めできないようにユニーク制約を設定
                $table->unique(['user_id', 'note_id']);
            });

            // shared_notesテーブルからpinnedカラムを削除
            Schema::table('shared_notes', function (Blueprint $table) {
                $table->dropColumn('pinned');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            // shared_notesテーブルにpinnedカラムを再追加
            Schema::table('shared_notes', function (Blueprint $table) {
                $table->boolean('pinned')->default(false)->after('content');
            });

            // pinned_notesテーブルを削除
            Schema::dropIfExists('pinned_notes');
        }
    };