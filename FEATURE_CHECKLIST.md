# 機能実装チェックリスト

## 【機能追加】

### ✅ ①共有メモ(Pages/Notes.vue)からカレンダーの情報を見れるように
- **実装状況**: 完了
- **実装内容**:
  - Notes.vueにlinked_event_id, linkedEventフィールド追加
  - ExternalLinkアイコンとCreateEventDialogをインポート
  - fetchLinkedEvent関数でリンクされたイベントを取得
  - openEventDialog関数でイベント編集ダイアログを表示
  - UIに「リンクされたカレンダー予定」セクションを追加
  - ボタンクリックでイベント詳細ダイアログを開く機能実装

### ✅ ②共有メモ（Pages/Notes.vue）に進捗バー追加
- **実装状況**: 完了
- **実装内容**:
  - SharedNoteModelインターフェースにprogress, linked_event_idフィールド追加
  - editedProgress refを追加
  - 進捗バーUIを実装（グラデーション表示、SharedCalendarスタイルに合わせる）
  - 休暇ジャンル(pink)の場合は進捗バーを非表示
  - SharedNotes.vueでも進捗バー表示（休暇除く）
  - SharedCalendar.vueのホバーツールチップでも進捗バー表示（休暇除く）
  - **進捗の双方向同期**:
    - NoteController.php: メモ更新時にリンクされたイベントの進捗も更新
    - EventService.php: イベント更新時にリンクされたメモの進捗も更新
    - NoteDetailDialog.vue: updateDataにprogressを追加

### ✅ ③共有カレンダー日表示(DayViewGantt.vue)での前中後タブで時間範囲を表示
- **実装状況**: 完了
- **実装内容**:
  - サマリーテーブルのヘッダーセルを更新
  - 「前」に「7:00-11:00」を追加
  - 「中」に「11:00-15:00」を追加
  - 「後」に「15:00-19:00」を追加
  - font-mediumでラベル、text-xsで時間範囲を表示

### ❌ ④共有カレンダーで個人の空き時間を確認できるようにしたい
- **実装状況**: 未実装
- **理由**: 要件が不明確（日表示における前中後に何時間空きがあるのかを検索できるように？）
- **推奨**: 具体的な仕様を確認後に実装

### ✅ ⑤デフォルトのカレンダー年月週日の選択できるように
- **実装状況**: 完了
- **実装内容**:
  - web.php: calendar.settingsルート追加（GET/POST）
  - CalendarController.php: settings()とupdateSettings()メソッド追加
  - マイグレーション: usersテーブルにcalendar_default_viewカラム追加
  - Settings.vue: 設定ページ作成（yearView/dayGridMonth/timeGridWeek/timeGridDay選択）
  - DashboardHeader.vue: 「カレンダー表示設定」メニュー項目追加
  - CalendarController.php index(): defaultViewをInertiaに渡す
  - Calendar.vue: defaultViewをpropsで受け取りSharedCalendarに渡す
  - SharedCalendar.vue: defaultViewをpropsで受け取りuseCalendarViewに渡す
  - useCalendarView.ts: initialView引数を追加してviewModeの初期値に設定

### ❌ ⑦共有カレンダーの日付表示から特定の日付へジャンプ（VueDatePicker使用）できる機能を実装
- **実装状況**: 未実装
- **理由**: 要件の詳細が不明
- **推奨**: VueDatePickerの配置場所と動作仕様を確認後に実装

## 【仕様変更】

### ✅ ①ダークモード時ホーム画面のリサイズバーが白すぎるのでダークモードに相応しい色味に変更
- **実装状況**: 完了
- **実装内容**:
  - Dashboard.vue: リサイズバーにdark:bg-gray-600, dark:bg-gray-700, dark:text-gray-500クラス追加

### ❌ ②共有カレンダーにおいてタスクと外出の切り分けができるように（優先度:低）
- **実装状況**: 未実装
- **理由**: 優先度低、要件の詳細が不明
- **推奨**: 具体的な仕様を確認後に実装

### ✅ ③カレンダーの休暇ジャンルの進捗削除
- **実装状況**: 完了
- **実装内容**:
  - SharedNotes.vue: `note.color !== 'pink'`条件で進捗バーを非表示
  - SharedCalendar.vue: `hoveredEvent.category !== '休暇'`条件でホバーツールチップの進捗バーを非表示
  - Notes.vue: 進捗バー表示時に休暇ジャンルを除外

## 【バグ】

### ✅ ①横断検索(Globalsearch.vue)のフィルターが不完全
- **実装状況**: 修正完了
- **実装内容**:
  - GlobalSearch.vue: SelectContentコンポーネントにz-[250]クラスを追加
  - creator, participant, typeフィルターのドロップダウンが検索結果(z-[100])の上に表示されるように修正

### ✅ ②アンケート回答時(おそらくanswers.vue)にセーブハンドル表示する
- **実装状況**: 修正完了
- **実装内容**:
  - Answer.vue: CheckCircleアイコンをインポート
  - 保存メッセージ表示機能を実装（preserveState: trueで画面遷移を防止）
  - メッセージ表示時間を4秒に延長
  - 送信時は1.5秒の遅延後にリダイレクト

## 【マイグレーション実行が必要】

```bash
# Docker環境の場合
docker-compose exec app php artisan migrate

# または
docker exec -it <コンテナ名> php artisan migrate
```

## 【未実装項目の理由】

1. **④個人の空き時間確認機能**: 要件が不明確。「前中後に何時間空きがあるのか」の具体的な表示方法や計算ロジックの確認が必要。

2. **⑦日付ジャンプ機能**: VueDatePickerの配置場所（ヘッダー？ツールバー？）と動作仕様（選択後の挙動）の確認が必要。

3. **②タスクと外出の切り分け**: 優先度低。現在のEventCategoryに新しいカテゴリを追加するのか、既存カテゴリを分割するのか仕様確認が必要。
