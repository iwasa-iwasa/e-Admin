-- 部署拡張マイグレーション修復スクリプト
-- エラーが出ても続行するため、各コマンドを個別に実行してください

-- 外部キー制約を一時的に無効化
SET FOREIGN_KEY_CHECKS=0;

-- 既存のテーブルを削除
DROP TABLE IF EXISTS calendar_event_shares;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS company_event_requests;
DROP TABLE IF EXISTS departments;

-- usersテーブルのカラムを削除（department_idが残っている）
ALTER TABLE users DROP COLUMN department_id;

-- surveysテーブルのカラムを削除（versionが残っている）
ALTER TABLE surveys DROP COLUMN version;

-- 外部キー制約を再度有効化
SET FOREIGN_KEY_CHECKS=1;

-- migrationsテーブルから部署関連のレコードを削除
DELETE FROM migrations WHERE migration LIKE '2026_01_21_%';
