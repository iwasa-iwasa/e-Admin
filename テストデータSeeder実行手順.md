# テストデータSeeder実行手順

## 概要
部署拡張機能のテストデータを投入するためのSeederを作成しました。

## 作成されるデータ

### 1. 部署（DepartmentSystemSeeder）
- 総務部
- 営業部
- 開発部
- 経理部

### 2. ユーザー

#### 管理者アカウント
- **全社管理者**: admin@company.com / password
- **総務部管理者**: somubu@company.com / password
- **営業部管理者**: eigyobu@company.com / password
- **開発部管理者**: kaihatsubu@company.com / password
- **経理部管理者**: keiri@company.com / password

#### 営業部メンバー（3名）
- eigyo.tanaka@company.com / password
- eigyo.sato@company.com / password
- eigyo.suzuki@company.com / password

#### 開発部メンバー（3名）
- dev.yamada@company.com / password
- dev.ito@company.com / password
- dev.watanabe@company.com / password

#### 経理部メンバー（3名）
- keiri.nakamura@company.com / password
- keiri.kobayashi@company.com / password
- keiri.kato@company.com / password

### 3. カレンダー
- 全社カレンダー（owner_type: company）
- 営業部カレンダー（owner_type: department）
- 開発部カレンダー（owner_type: department）
- 経理部カレンダー（owner_type: department）

### 4. カレンダー予定

#### 全社カレンダー（2件）
- 全社会議（7日後）
- 会社創立記念日（30日後）

#### 営業部カレンダー（2件）
- 営業部ミーティング（3日後）
- 顧客訪問（5日後）

#### 開発部カレンダー（2件）
- スプリントレビュー（4日後）
- システムメンテナンス（10日後）

### 5. 共有メモ

#### 全社公開メモ（2件）
- 社内規定の更新について
- 年末年始の営業日程

#### 営業部メモ（2件）
- 営業目標達成状況
- 顧客リスト更新

#### 開発部メモ（2件）
- コーディング規約の更新
- テスト環境のURL

### 6. ゴミ箱データ
- 削除済み予定（営業部）: 1件
- 削除済みメモ（開発部）: 1件

## 実行方法

### 1. 初回セットアップ（全データ投入）
```bash
php artisan migrate:fresh --seed
```

### 2. テストデータのみ追加
既存のデータベースにテストデータのみを追加する場合：

```bash
# 部署システムの初期化
php artisan db:seed --class=DepartmentSystemSeeder

# テストデータの投入
php artisan db:seed --class=TestDataSeeder
```

### 3. 特定のSeederのみ実行
```bash
# 部署とカレンダーのみ
php artisan db:seed --class=DepartmentSystemSeeder

# テストユーザーとデータのみ
php artisan db:seed --class=TestDataSeeder
```

## 注意事項

1. **実行順序**: DepartmentSystemSeeder → TestDataSeeder の順で実行してください
2. **重複チェック**: 各Seederは既存データをチェックし、重複を避けます
3. **パスワード**: すべてのテストアカウントのパスワードは `password` です
4. **削除データ**: ゴミ箱のデータは30日後に自動削除される設定です

## データベースのリセット

すべてのデータを削除して再投入する場合：

```bash
php artisan migrate:fresh --seed
```

## 確認方法

### ユーザー数の確認
```bash
php artisan tinker
>>> User::count()
>>> User::where('role_type', 'department_admin')->count()
>>> User::where('role_type', 'member')->count()
```

### 部署別ユーザー数の確認
```bash
php artisan tinker
>>> Department::with('users')->get()->map(fn($d) => [$d->name, $d->users->count()])
```

### カレンダー数の確認
```bash
php artisan tinker
>>> Calendar::count()
>>> Calendar::where('owner_type', 'company')->count()
>>> Calendar::where('owner_type', 'department')->count()
```

### 予定数の確認
```bash
php artisan tinker
>>> Event::where('is_deleted', false)->count()
>>> Event::where('is_deleted', true)->count()
```

### 共有メモ数の確認
```bash
php artisan tinker
>>> SharedNote::where('is_deleted', false)->count()
>>> SharedNote::where('is_deleted', true)->count()
```

### ゴミ箱データの確認
```bash
php artisan tinker
>>> TrashItem::count()
```

## トラブルシューティング

### エラー: 部署が見つかりません
```
先にDepartmentSystemSeederを実行してください：
php artisan db:seed --class=DepartmentSystemSeeder
```

### エラー: 外部キー制約違反
```
データベースをリセットしてから再実行してください：
php artisan migrate:fresh --seed
```

### エラー: ユーザーが既に存在します
```
重複チェックが機能しているため、問題ありません。
既存のユーザーはスキップされます。
```

## 開発用コマンド

### すべてのテストデータを削除
```bash
php artisan migrate:fresh
```

### テストデータを再投入
```bash
php artisan migrate:fresh --seed
```

### 特定の部署のユーザーのみ削除
```bash
php artisan tinker
>>> User::where('department_id', 2)->delete()  // 営業部のユーザーを削除
```
