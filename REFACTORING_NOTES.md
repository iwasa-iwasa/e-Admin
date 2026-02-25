# ファイル整理・リファクタリング記録

## 実施日: 2024年

## 重複ファイルの整理

### 1. CreateNoteDialog.vue と NoteDetailDialog.vue

#### 削除したファイル
- ❌ `resources/js/components/Notes/CreateNoteDialog.vue` (古いバージョン)
- ❌ `resources/js/components/Notes/NoteDetailDialog.vue` (古いバージョン)
- ❌ `resources/js/components/Notes/` ディレクトリ全体

#### 保持したファイル
- ✅ `resources/js/components/CreateNoteDialog.vue` (最新版)
- ✅ `resources/js/components/NoteDetailDialog.vue` (最新版)

#### 理由
最新版には以下の高度な機能が実装されており、古いバージョンは不要：
- タブUI（基本情報/その他設定）
- ピン留め機能
- 共有メンバー選択（個人/全体公開の切り替え）
- 下書き自動保存・復元機能
- 日時ピッカー（VueDatePicker使用）
- 進捗バー（0-100%）
- タグ管理
- 編集権限チェック
- Undo機能（削除の取り消し）
- リンクされたカレンダーイベント表示
- ダークモード対応

### 2. Notes.vue のリネーム

#### 変更内容
- 🔄 `resources/js/components/Notes.vue` → `resources/js/components/NotesCard.vue`

#### 理由
- `Pages/Notes.vue` - フルページ版（独立したページとして機能）
- `components/NotesCard.vue` - カードコンポーネント版（ダッシュボードに埋め込み用）
- 名前の衝突を避け、用途を明確化

#### 影響範囲
- `NotesCard.vue`内のインポートパスは既に正しいため修正不要
- Dashboard.vueは`SharedNotes.vue`を使用しているため影響なし

### 3. その他の重複ファイル（対応不要）

以下のファイルは用途が異なるため、そのまま保持：

#### Calendar.vue
- `Pages/Calendar.vue` - カレンダーページ
- `components/ui/calendar/Calendar.vue` - UIコンポーネント

#### Checkbox.vue
- `components/Checkbox.vue` - カスタムチェックボックス
- `components/ui/checkbox/Checkbox.vue` - UIライブラリのチェックボックス

#### TextInput.vue
- `components/TextInput.vue` - 汎用テキスト入力
- `features/survey/components/inputs/TextInput.vue` - アンケート専用

## ファイル構成（整理後）

```
resources/js/
├── components/
│   ├── CreateNoteDialog.vue          # メモ作成ダイアログ（最新版）
│   ├── NoteDetailDialog.vue          # メモ詳細ダイアログ（最新版）
│   ├── NotesCard.vue                 # メモカードコンポーネント（リネーム）
│   └── SharedNotes.vue               # 共有メモコンポーネント
└── Pages/
    └── Notes.vue                     # メモページ（フルページ版）
```

## 今後の注意点

1. **新規機能追加時**
   - `CreateNoteDialog.vue`と`NoteDetailDialog.vue`は`components/`直下のファイルを編集
   - 古い`Notes/`ディレクトリは削除済み

2. **インポート時**
   - メモダイアログ: `@/components/CreateNoteDialog.vue`
   - メモカード: `@/components/NotesCard.vue`
   - 共有メモ: `@/components/SharedNotes.vue`
   - メモページ: `@/Pages/Notes.vue`

3. **命名規則**
   - Pageコンポーネント: 機能名のみ（例: `Notes.vue`）
   - 埋め込みコンポーネント: 用途を明示（例: `NotesCard.vue`, `SharedNotes.vue`）
