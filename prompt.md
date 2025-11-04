## 概要

既存のReactプロジェクトを、以下の技術スタックを使用してVue.jsプロジェクトに変換してください。

- **バックエンド:** Laravel
- **フロントエンド:** Vue.js 3 (Composition API)
- **連携:** Inertia.js
- **UIコンポーネント:** shadcn-vue

## 全体的な変換ルール

1.  **Vue 3 Composition API:** すべてのVueコンポーネントは `<script setup lang="ts">` を使用したComposition APIで記述してください。
2.  **TypeScript:** 全面的にTypeScriptを使用してください。Reactの`Props`や`State`の型定義は、Vueの`defineProps`や`ref`, `reactive`の型付けに変換します。
3.  **状態管理:** `useState`, `useEffect`, `useContext` などのReact Hooksは、Vueの `ref`, `reactive`, `onMounted`, `computed`, `provide`/`inject` などに置き換えてください。
4.  **ルーティング:** React Routerに相当する機能はInertia.jsの `<Link>` コンポーネントや `router.get()` などに置き換えます。Laravel側で対応するルート定義が必要になります。
5.  **CSS:** `globals.css` の内容は、Laravelプロジェクトの `resources/css/app.css` に移動し、Tailwind CSSの`@layer`ディレクティブ内に適切に配置してください。
6.  **`shadcn-vue`の積極的な利用:** 最も重要なルールです。既存のUIコンポーネントは、可能な限り`shadcn-vue`のコンポーネントに置き換えてください。これにより、UIの一貫性を保ち、クリーンなコードを維持します。

---

## ファイルごとの変換指示

### 1. `components/ui` ディレクトリの扱い

**重要:** `components/ui` 内のReactコンポーネント（`button.tsx`, `card.tsx`など）は、**変換せず、すべて破棄してください。**
これらの代わりに、`npx shadcn-vue@latest add` コマンドでインストールした公式の`shadcn-vue`コンポーネントを直接利用します。

### 2. 主要ページの変換

Inertia.jsの規約に従い、これらのページコンポーネントは `resources/js/Pages/` ディレクトリに配置します。

| 元ファイル | 新ファイル（例） | 変換指示 |
| :--- | :--- | :--- |
| `Dashboard.tsx` | `resources/js/Pages/Dashboard.vue` | ・`DashboardHeader`と`DashboardSidebar`をslotまたはコンポーネントとしてレイアウトに組み込む。<br>・`PersonalReminders`, `SharedCalendar`, `SharedNotes` を子コンポーネントとして読み込む。<br>・`Card`や`Button`など、`shadcn-vue`のコンポーネントでUIを再構築する。 |
| `CalendarPage.tsx` | `resources/js/Pages/Calendar.vue` | ・`shadcn-vue`の`<Calendar />`コンポーネントをメインに使用する。<br>・イベント作成ボタンには`<Button />`、イベント詳細は`<Dialog />`を使用する。 |
| `NotesPage.tsx` | `resources/js/Pages/Notes.vue` | ・ノート一覧を`<Table />`または`<Card />`のリストで表示する。<br>・ノート作成には`<Dialog />`と`<Textarea />`を使用する。 |
| `SurveysPage.tsx` | `resources/js/Pages/Surveys.vue` | ・アンケート一覧を`<Table />`で表示する。<br>・各行に「結果を見る」「削除」などの操作を`<Button />`や`<DropdownMenu />`で配置する。 |
| `RemindersPage.tsx` | `resources/js/Pages/Reminders.vue` | ・リマインダー一覧を`<Card />`や`<Accordion />`で表示する。<br>・`<Checkbox />`を使って完了ステータスを管理する。 |
| `TrashPage.tsx` | `resources/js/Pages/Trash.vue` | ・削除済みアイテムを`<Table />`で表示する。<br>・「復元」や「完全削除」ボタンを各行に配置する。 |

### 3. ダイアログ・モーダルコンポーネントの変換

これらは `resources/js/Components/` ディレクトリに配置し、`shadcn-vue`の`<Dialog />`または`<AlertDialog />`をベースに作成します。

| 元ファイル | 新コンポーネント（例） | 変換指示 |
| :--- | :--- | :--- |
| `CreateEventDialog.tsx` | `resources/js/Components/CreateEventDialog.vue` | ・`<Dialog>`, `<DialogTrigger>`, `<DialogContent>`, `<DialogHeader>`, `<DialogTitle>`, `<DialogDescription>`, `<DialogFooter>` を使用して構成する。<br>・フォーム部分には`<Input />`, `<Label />`, `<Button />`, `<Calendar />` (日付選択用) を使用する。 |
| `CreateNoteDialog.tsx` | `resources/js/Components/CreateNoteDialog.vue` | ・上記と同様に`<Dialog />`で構成する。<br>・ノート入力エリアには`<Textarea />`を使用する。 |
| `EventDetailDialog.tsx` | `resources/js/Components/EventDetailDialog.vue` | ・イベント情報を表示するためのダイアログ。<br>・編集や削除ボタンを`<DialogFooter />`に配置する。 |
| `ProfileSettingsDialog.tsx` | `resources/js/Components/ProfileSettingsDialog.vue` | ・`<Tabs />`を使用して「プロフィール」「通知」などのセクションを分ける。<br>・フォーム要素には`<Input />`, `<Switch />`などを使用する。 |

### 4. 共通・再利用コンポーネントの変換

これらも `resources/js/Components/` ディレクトリに配置します。

| 元ファイル | 新コンポーネント（例） | 変換指示 |
| :--- | :--- | :--- |
| `DashboardHeader.tsx` | `resources/js/Layouts/Partials/DashboardHeader.vue` | ・ユーザーアイコンに`<Avatar />`と`<DropdownMenu />`を組み合わせる。<br>・検索バーに`<Input />`を使用する。<br>・通知アイコンに`<Button variant="ghost" />`と`<Popover />`を組み合わせる。 |
| `DashboardSidebar.tsx` | `resources/js/Layouts/Partials/DashboardSidebar.vue` | ・ナビゲーションリンクを`<Button variant="ghost" />`を使い、Inertia.jsの`<Link>`コンポーネントをラップして作成する。<br>・アクティブなリンクのスタイルをInertia.jsの`page.url`を元に動的に適用する。 |
| `SharedCalendar.tsx` | `resources/js/Components/SharedCalendar.vue` | ・`<Card />`の中に`<Calendar />`を配置する。<br>・共有イベントのデータを`props`として受け取る。 |
| `PersonalReminders.tsx` | `resources/js/Components/PersonalReminders.vue` | ・`<Card />`と`<CardHeader />`で囲む。<br>・リマインダーリストを`<Accordion />`または`<Checkbox />`付きのリストで表示する。 |
| `CreateEvent.tsx` | `resources/js/Components/CreateEventForm.vue` | ・`<Input />`, `<Textarea />`, `<Popover />`+`<Calendar />` (日付選択), `<Select />` (カテゴリ選択) など、`shadcn-vue`のフォームコンポーネントをフル活用してフォームを再構築する。 |

### 5. データとユーティリティ

| 元ファイル | 新ファイル（例） | 変換指示 |
| :--- | :--- | :--- |
| `shared-events-data.ts` | `resources/js/data/events.ts` | ・そのままTypeScriptファイルとして流用可能。必要に応じて型定義を調整する。 |
| `App.tsx` | `resources/js/Layouts/AppLayout.vue` | ・メインのレイアウトファイルとして作成する。<br>・`DashboardSidebar`と`DashboardHeader`を配置し、メインコンテンツを`<slot />`で受け取るようにする。<br>・Inertia.jsの`createInertiaApp`の`resolve`オプションでこのレイアウトを指定する。 |

---