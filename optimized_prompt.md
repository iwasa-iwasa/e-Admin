# Laravel + Vue.js SPA 開発タスク

## 概要
現在開発中のLaravel + Vue.js + Inertia.js SPAにおいて、以下のタスクを実行し、アプリケーションをより完成に近づけてください。
現状、データはデータベースと連携しておらず、Vueコンポーネント内にダミーデータとしてハードコーディングされています。

---

## 1. 認証とリダイレクト処理の実装

ルートパス (`/`) へのアクセス時に、ユーザーの認証状態でリダイレクト先を振り分けるように `routes/web.php` を修正してください。

- **未認証ユーザー:**
    - ログインページ (`/login`) へリダイレクトしてください。
    - 現在の `Welcome.vue` は、ログインページと新規登録ページへのリンクを持つシンプルなランディングページとして機能するように修正してください。
- **認証済みユーザー:**
    - ダッシュボードページ (`/dashboard`) へリダイレクトしてください。

---

## 2. データベースへの移行

Vueコンポーネントにハードコーディングされているダミーデータを、データベースから取得するように変更してください。

### 2.1. データベースとモデルの分析
- `database/migrations` ディレクトリ内のマイグレーションファイルをすべて読み込み、テーブル構造を把握してください。
- `app/Models` ディレクトリ内のモデルファイルを確認し、リレーションシップを理解してください。

### 2.2. Seederの作成と実行
- `resources/js/Pages` 内の各Vueコンポーネント（`Calendar.vue`, `Notes.vue`, `Reminders.vue` など）に含まれるダミーデータを参考に、対応するSeederを作成してください。
- **作成するSeeder:**
    - `database/seeders/UserSeeder.php`: ログインテスト用のユーザーを1件作成してください。
        - **メールアドレス:** `test@example.com`
        - **パスワード:** `password`
    - `database/seeders/EventSeeder.php`: カレンダーイベントのダミーデータ用。
    - `database/seeders/NoteSeeder.php`: 共有メモのダミーデータ用。
    - その他、アプリケーションに必要なモデルに対応するSeederを作成してください。
- `database/seeders/DatabaseSeeder.php` を更新し、作成したすべてのSeederを呼び出すように設定してください。
- データベースをマイグレーションし、作成したSeederを実行してください。

### 2.3. データ取得ロジックの実装
- 各ページに対応するLaravelのコントローラー（必要であれば新規作成）で、データベースからデータを取得し、Inertia.jsを介してVueコンポーネントにPropsとして渡すように実装してください。
- **例 (`routes/web.php`):**
  ```php
  use App\Http\Controllers\DashboardController;

  Route::get('/dashboard', [DashboardController::class, 'index'])
      ->middleware(['auth', 'verified'])
      ->name('dashboard');
  ```
- **例 (`app/Http/Controllers/DashboardController.php` を作成):**
  ```php
  public function index()
  {
      return Inertia::render('Dashboard', [
          'events' => Auth::user()->events()->get(), // 例
          'notes' => Auth::user()->notes()->get(),   // 例
      ]);
  }
  ```

### 2.4. Vueコンポーネントの修正
- すべてのVueページコンポーネント (`resources/js/Pages/**/*.vue`) を確認してください。
- ハードコーディングされたダミーデータを削除し、Propsから渡されたデータを表示するように各コンポーネントを修正してください。
- プロフィール設定ページ (`resources/js/Pages/Profile/Edit.vue`) も同様に、データベースの情報を表示・更新できるように修正してください。

---

## 3. UI/UXの改善

添付の画像を参考に、アプリケーション全体のレイアウトとスタイルを修正してください。

- **参照画像:**
    - 修正前の状態: `@現在のレイアウト画像.png`
    - 目指すべき状態: `@正しいレイアウト画像.png`

### 3.1. レイアウトの背景修正
- `@現在のレイアウト画像.png` で見られる、モーダルダイアログやドロップダウンなどの背景が黒く透明になっている問題を修正してください。
- 対象となる可能性のあるファイル: `AuthenticatedLayout.vue`, `Modal.vue`, `Dropdown.vue` など。
- Tailwind CSSのユーティリティクラス（`bg-opacity`, `backdrop-filter`など）を適切に調整・修正してください。

### 3.2. アクティブなナビゲーションリンクの強調
- `@正しいレイアウト画像.png` を参考に、サイドバーのナビゲーションリンク (`resources/js/components/NavMain.vue` など) で、現在表示しているページに対応するリンクがアクティブ（背景が黒、テキストが白）になるようにスタイルを適用してください。
- Inertia.jsの `$page.url` プロパティとリンクの `href` を比較して、アクティブ状態を判定してください。
- **対象ファイル:** `NavLink.vue`, `ResponsiveNavLink.vue`

---

すべてのタスクが完了したら、変更をコミットする準備ができたことを報告してください。
