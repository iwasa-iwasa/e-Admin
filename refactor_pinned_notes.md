# `shared_notes`のピン留め機能をユーザー個別対応にする改修計画

## 1. 概要

現在の`shared_notes`テーブルに存在する`pinned`カラムは、ノートが全てのユーザーに対してピン留めされているか否かを示すグローバルな状態です。これを廃止し、ユーザーが自分専用にノートをピン留めできる機能を実装します。

実現のために、以下の手順で改修を行います。

-   **新テーブル作成**: どのユーザー(`user_id`)がどのノート(`note_id`)をピン留めしているかを管理する中間テーブル `pinned_notes` を作成します。
-   **既存カラム削除**: `shared_notes`テーブルから不要になる `pinned` カラムを削除します。
-   **モデルの更新**: `User`モデルと`SharedNote`モデルにリレーションを定義します。
-   **バックエンド改修**: ノート一覧取得時に、現在の認証ユーザーがピン留めしているかどうかの情報（例: `is_pinned`属性）を付与します。ピン留め・解除を行うAPIエンドポイントを実装します。
-   **フロントエンド改修**: バックエンドからの新しいデータ構造に合わせて、ピンの状態表示とピン留め・解除のクリックイベントを更新します。

---

## 2. タスク一覧

-   [ ] 新しいマイグレーションファイルの作成
-   [ ] `User`モデルと`SharedNote`モデルのリレーション設定
-   [ ] `NoteController`にピン留め状態を付与するロジックと、ピン留め/解除メソッドを追加
-   [ ] `routes/web.php`にピン留め/解除用のルートを追加
-   [ ] Vueコンポーネント (`SharedNotes.vue`など) を修正
-   [ ] マイグレーションの実行

---

## 3. 詳細な手順

### ステップ1: マイグレーションの作成

新しいマイグレーションファイルを作成し、`pinned_notes`テーブルの作成と`shared_notes`テーブルの`pinned`カラム削除を定義します。

1.  以下のコマンドを実行してマイグレーションファイルを作成します。
    ```bash
    php artisan make:migration create_pinned_notes_table_and_remove_pinned_column_from_shared_notes
    ```

2.  作成されたマイグレーションファイルの中身を以下のように編集します。

    ```php
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
                $table->foreignId('note_id')->constrained('shared_notes')->onDelete('cascade');
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
    ```

### ステップ2: モデルの更新

`User`モデルと`SharedNote`モデルに多対多のリレーションを定義します。

1.  **`app/Models/User.php`**
    `pinnedNotes()`リレーションを追加します。

    ```php
    // ... existing code

    use Illuminate\Database\Eloquent\Relations\BelongsToMany;

    class User extends Authenticatable
    {
        // ... existing code

        /**
         * ユーザーがピン留めした共有ノートを取得します。
         */
        public function pinnedNotes(): BelongsToMany
        {
            return $this->belongsToMany(SharedNote::class, 'pinned_notes', 'user_id', 'note_id')->withTimestamps();
        }
    }
    ```

2.  **`app/Models/SharedNote.php`**
    `pinnedByUsers()`リレーションを追加します。

    ```php
    // ... existing code

    use Illuminate\Database\Eloquent\Relations\BelongsToMany;

    class SharedNote extends Model
    {
        // ... existing code

        /**
         * この共有ノートをピン留めしたユーザーを取得します。
         */
        public function pinnedByUsers(): BelongsToMany
        {
            return $this->belongsToMany(User::class, 'pinned_notes', 'note_id', 'user_id')->withTimestamps();
        }
    }
    ```

### ステップ3: コントローラーの改修 (`NoteController.php`)

ノート一覧を返す際に、認証ユーザーが各ノートをピン留めしているかどうかの情報を付与します。また、ピン留めと解除を行うためのメソッドを実装します。

```php
<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NoteController extends Controller
{
    /**
     * 共有ノートの一覧を表示します。
     */
    public function index()
    {
        $user = Auth::user();
        $notes = SharedNote::with('user')->latest()->get();

        // 認証ユーザーがピン留めしたノートのIDリストを取得
        $pinnedNoteIds = $user->pinnedNotes()->pluck('shared_notes.id')->flip();

        // 各ノートにis_pinned属性を付与
        $notes->each(function ($note) use ($pinnedNoteIds) {
            $note->is_pinned = $pinnedNoteIds->has($note->id);
        });

        return Inertia::render('Notes', [
            'notes' => $notes,
        ]);
    }

    // ... 他メソッド

    /**
     * 指定されたノートをピン留めします。
     */
    public function pin(Request $request, SharedNote $note)
    {
        $request->user()->pinnedNotes()->attach($note->id);

        return back()->with('success', 'ノートをピン留めしました。');
    }

    /**
     * 指定されたノートのピン留めを解除します。
     */
    public function unpin(Request $request, SharedNote $note)
    {
        $request->user()->pinnedNotes()->detach($note->id);

        return back()->with('success', 'ノートのピン留めを解除しました。');
    }
}
```

### ステップ4: ルーティングの追加 (`routes/web.php`)

ピン留めと解除のためのルートを定義します。

```php
// ... existing routes

use App\Http...NoteController;

Route::middleware(['auth', 'verified'])->group(function () {
    // ...
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    
    // ピン留め/解除ルートを追加
    Route::post('/notes/{note}/pin', [NoteController::class, 'pin'])->name('notes.pin');
    Route::delete('/notes/{note}/unpin', [NoteController::class, 'unpin'])->name('notes.unpin');

    // ...
});

// ...
```

### ステップ5: フロントエンドの改修 (例: `resources/js/Pages/Notes.vue`)

propsで渡される`note`オブジェクトの`pinned`プロパティが`is_pinned`に変わります。これに合わせてUIの表示と、ピン留めアイコンクリック時の動作を修正します。

```vue
<script setup>
import { defineProps } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    notes: Array,
});

// ピン留め/解除をトグルする関数
const togglePin = (note) => {
    if (note.is_pinned) {
        // ピン留め解除APIを叩く
        router.delete(route('notes.unpin', note.id), {
            preserveScroll: true, // スクロール位置を維持
            preserveState: true,  // コンポーネントの状態を維持
        });
    } else {
        // ピン留めAPIを叩く
        router.post(route('notes.pin', note.id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }
};
</script>

<template>
    <!-- ... -->
    <div v-for="note in notes" :key="note.id">
        <!-- ... note content ... -->

        <!-- ピン留めボタン -->
        <button @click="togglePin(note)">
            <!-- is_pinnedの値に応じてアイコンやスタイルを切り替える -->
            <svg v-if="note.is_pinned" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <!-- Solid pin icon -->
            </svg>
            <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <!-- Outline pin icon -->
            </svg>
        </button>
    </div>
    <!-- ... -->
</template>
```

### ステップ6: マイグレーションの実行

全てのコード修正が完了したら、データベースに新しい構造を適用します。

```bash
php artisan migrate
```

これで、ユーザーごとのピン留め機能が実装されます。
