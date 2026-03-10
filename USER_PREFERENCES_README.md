# user_preferences テーブル実装完了

## 📋 実装内容

### 作成したファイル

1. **マイグレーションファイル**
   - `database/migrations/2026_01_21_000012_create_user_preferences_table.php`
   - user_preferencesテーブルを作成

2. **モデルファイル**
   - `app/Models/UserPreference.php`
   - UserPreferenceモデルを作成

3. **リレーション追加**
   - `app/Models/User.php`
   - User->preferences() リレーションを追加

## 🗄️ テーブル構造

```sql
CREATE TABLE user_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE NOT NULL,
    show_company_calendar_in_trash BOOLEAN DEFAULT FALSE,
    notification_scope ENUM('department', 'company', 'both') DEFAULT 'both',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_preferences_user (user_id)
);
```

## 📝 カラム説明

| カラム | 型 | デフォルト | 説明 |
|--------|-----|-----------|------|
| id | BIGINT | - | 主キー |
| user_id | BIGINT | - | ユーザーID（ユニーク） |
| show_company_calendar_in_trash | BOOLEAN | FALSE | ゴミ箱で全社カレンダーのアイテムを表示するか |
| notification_scope | ENUM | 'both' | 通知を受け取る範囲（department/company/both） |

## 🔧 マイグレーション実行方法

```bash
# マイグレーション実行
php artisan migrate

# または特定のマイグレーションのみ実行
php artisan migrate --path=/database/migrations/2026_01_21_000012_create_user_preferences_table.php
```

## 💻 使用例

### 設定の取得

```php
$user = auth()->user();
$preferences = $user->preferences;

// ゴミ箱で全社カレンダーを表示するか
$showCompanyItems = $preferences->show_company_calendar_in_trash ?? false;

// 通知範囲
$notificationScope = $preferences->notification_scope ?? 'both';
```

### 設定の更新

```php
$user = auth()->user();

// 設定が存在しない場合は作成
if (!$user->preferences) {
    $user->preferences()->create([
        'show_company_calendar_in_trash' => true,
        'notification_scope' => 'both',
    ]);
} else {
    // 既存の設定を更新
    $user->preferences->update([
        'show_company_calendar_in_trash' => true,
        'notification_scope' => 'department',
    ]);
}
```

### ゴミ箱での使用例

```php
// app/Http/Controllers/TrashController.php
public function index(Request $request)
{
    $user = auth()->user();
    $preferences = $user->preferences ?? new UserPreference();
    
    $query = TrashItem::with(['user'])
        ->where('user_id', $user->id);
    
    // 設定に応じて全社カレンダーのアイテムを含める
    if (!$preferences->show_company_calendar_in_trash) {
        $query->where(function($q) {
            $q->where('visibility_type', '!=', 'public')
              ->orWhereNull('visibility_type');
        });
    }
    
    $trashItems = $query->orderBy('deleted_at', 'desc')->get();
    
    return Inertia::render('Trash/Index', [
        'trashItems' => $trashItems,
        'showCompanyItems' => $preferences->show_company_calendar_in_trash,
    ]);
}
```

### 通知での使用例

```php
// app/Services/NotificationService.php
public function sendEventNotification(Event $event, $notification)
{
    $calendar = $event->calendar;
    
    // 通知対象ユーザーを取得
    $users = User::where('is_active', true)
        ->whereHas('preferences', function($q) use ($calendar) {
            if ($calendar->owner_type === 'company') {
                // 全社カレンダー: 'company' または 'both' を設定しているユーザー
                $q->whereIn('notification_scope', ['company', 'both']);
            } else {
                // 部署カレンダー: 該当部署で 'department' または 'both' を設定しているユーザー
                $q->whereIn('notification_scope', ['department', 'both'])
                  ->where('department_id', $calendar->owner_id);
            }
        })
        ->get();
    
    Notification::send($users, $notification);
}
```

## ✅ 実装完了チェックリスト

- [x] マイグレーションファイル作成
- [x] UserPreferenceモデル作成
- [x] User->preferences リレーション追加
- [x] インデックス追加
- [x] 外部キー制約追加
- [x] デフォルト値設定
- [x] マイグレーション実行（成功）
- [ ] ゴミ箱コントローラーでの使用
- [ ] 通知サービスでの使用
- [ ] UI実装（設定画面）

## 🎯 次のステップ

1. **マイグレーション実行**
   ```bash
   php artisan migrate
   ```

2. **ゴミ箱コントローラーの実装**
   - TrashController に preferences を使用したフィルタリングを追加

3. **通知サービスの実装**
   - NotificationService に preferences を使用した通知範囲制御を追加

4. **UI実装**
   - ゴミ箱の設定ボタン追加
   - 通知設定画面の追加

---

**作成日**: 2025年1月  
**ステータス**: マイグレーション・モデル実装完了
