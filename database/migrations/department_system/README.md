# 部署拡張システム - マイグレーション実行手順

## 📁 ファイル構成

```
database/migrations/department_system/
├── 2026_01_21_000001_create_departments_table.php
├── 2026_01_21_000002_create_company_event_requests_table.php
├── 2026_01_21_000003_create_calendar_event_shares_table.php
├── 2026_01_21_000004_create_audit_logs_table.php
├── 2026_01_21_000005_add_department_fields_to_users_table.php
├── 2026_01_21_000006_add_owner_fields_to_calendars_table.php
├── 2026_01_21_000007_add_department_fields_to_events_table.php
├── 2026_01_21_000008_add_department_fields_to_shared_notes_table.php
├── 2026_01_21_000009_add_department_fields_to_surveys_table.php
├── 2026_01_21_000010_add_department_fields_to_trash_items_table.php
└── 2026_01_21_000011_add_indexes_to_event_participants_table.php

app/Console/Commands/
└── MigrateToDepartmentSystem.php
```

## ⚠️ 実行前の確認事項

### 1. データベースバックアップ（必須）

```bash
# 完全バックアップを取得
mysqldump -u [ユーザー名] -p [データベース名] > backup_$(date +%Y%m%d_%H%M%S).sql

# バックアップファイルの確認
ls -lh backup_*.sql
```

### 2. 現在のデータベース状態確認

```bash
# テーブル一覧確認
php artisan db:show

# 既存データ件数確認
php artisan tinker
>>> DB::table('users')->count()
>>> DB::table('calendars')->count()
>>> DB::table('events')->count()
>>> DB::table('shared_notes')->count()
>>> DB::table('surveys')->count()
```

## 🚀 実行手順（開発環境）

### ステップ1: マイグレーションファイルを通常のディレクトリに移動

```bash
# department_systemフォルダ内のファイルを通常のmigrationsディレクトリに移動
mv database/migrations/department_system/*.php database/migrations/

# 移動確認
ls -la database/migrations/2026_01_21_*
```

### ステップ2: マイグレーション実行

```bash
# マイグレーション実行
php artisan migrate

# 実行されたマイグレーションの確認
php artisan migrate:status
```

### ステップ3: データ移行コマンド実行

```bash
# 既存データを部署システムに移行
php artisan migrate:department-system
```

### ステップ4: 動作確認

```bash
php artisan tinker

# 部署の確認
>>> DB::table('departments')->get()

# カレンダーの確認（総務部カレンダーになっているか）
>>> DB::table('calendars')->where('owner_type', 'department')->get()

# イベントの確認
>>> DB::table('events')->whereNotNull('owner_department_id')->count()

# ユーザーと部署のリレーション確認（Modelが作成されている場合）
>>> User::with('department')->first()
```

## 🔄 ロールバック手順

### 方法1: マイグレーションのロールバック

```bash
# 最後のバッチをロールバック
php artisan migrate:rollback

# 特定のステップ数をロールバック
php artisan migrate:rollback --step=11
```

### 方法2: バックアップから復元

```bash
# データベースを完全に復元
mysql -u [ユーザー名] -p [データベース名] < backup_20260121_100000.sql
```

## 📊 変更内容サマリー

### 新規テーブル（4つ）
- `departments` - 部署マスタ
- `company_event_requests` - 全社カレンダー承認
- `calendar_event_shares` - 予定共有
- `audit_logs` - 監査ログ

### 変更テーブル（6つ）
- `users` - department_id, role_type追加
- `calendars` - owner_type, owner_id追加
- `events` - visibility_type, owner_department_id, version追加
- `shared_notes` - visibility_type, owner_department_id, version追加
- `surveys` - visibility_type, owner_department_id, version追加
- `trash_items` - owner_department_id, visibility_type追加

### インデックス（15個）
- 部署関連: 3個
- カレンダー関連: 5個
- 共有関連: 2個
- 参加者関連: 2個
- その他: 3個

## 🎯 移行後の確認ポイント

### 1. データ整合性
```sql
-- 全イベントに owner_department_id が設定されているか
SELECT COUNT(*) FROM events WHERE owner_department_id IS NULL;
-- 結果: 0 であるべき

-- 全カレンダーに owner_type が設定されているか
SELECT COUNT(*) FROM calendars WHERE owner_type IS NULL;
-- 結果: 0 であるべき
```

### 2. インデックス確認
```sql
-- インデックスが正しく作成されているか
SHOW INDEX FROM events;
SHOW INDEX FROM calendars;
SHOW INDEX FROM users;
```

### 3. 外部キー制約確認
```sql
-- 外部キー制約が正しく設定されているか
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IN ('departments', 'calendars', 'events');
```

## 📝 注意事項

1. **既存カレンダーについて**
   - 既存の共有カレンダーは「総務部カレンダー」に変換されます
   - 新たに「全社カレンダー」が作成されます

2. **既存イベントについて**
   - 全て総務部カレンダーに移動します
   - 参加者がいないイベントは `visibility_type = 'public'`
   - 参加者がいるイベントは `visibility_type = 'custom'`

3. **ユーザーの部署割り当て**
   - 初期状態では全ユーザーの `department_id` は NULL です
   - 管理画面から手動で部署を割り当てる必要があります

4. **ロールバック**
   - データ移行コマンドはロールバックできません
   - 必ずバックアップから復元してください

## 🆘 トラブルシューティング

### エラー: Foreign key constraint fails

```bash
# 外部キー制約の順序問題の場合、一時的に無効化
SET FOREIGN_KEY_CHECKS=0;
# マイグレーション実行
SET FOREIGN_KEY_CHECKS=1;
```

### エラー: Column already exists

```bash
# 既にカラムが存在する場合、該当マイグレーションをスキップ
php artisan migrate --skip-existing
```

### データ移行が途中で止まる

```bash
# チャンクサイズを小さくして再実行
# MigrateToDepartmentSystem.php の chunkById(100) を chunkById(50) に変更
```

## 📞 サポート

問題が発生した場合は、以下の情報を添えて報告してください：

1. エラーメッセージの全文
2. `php artisan migrate:status` の出力
3. データベースのバージョン
4. 実行したコマンドの履歴
