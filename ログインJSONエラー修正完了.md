# ログイン時のJSONエラー修正完了

## 問題の原因
ログイン時にユーザー情報をフロントエンドに渡す際、Userモデルのリレーション（特にdepartmentリレーション）がJSON化されて送信されていた。

## 修正内容

### 1. HandleInertiaRequests.php
**修正前:**
```php
'auth' => [
    'user' => $user,
],
```

**修正後:**
```php
'auth' => [
    'user' => $user ? [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'department_id' => $user->department_id,
        'role' => $user->role,
        'role_type' => $user->role_type,
        'is_active' => $user->is_active,
        'email_verified_at' => $user->email_verified_at,
    ] : null,
],
```

### 2. 各コントローラーでのユーザー取得
以下のコントローラーで、必要な列のみを取得するように修正：

#### DashboardController.php
```php
$teamMembers = \App\Models\User::where('is_active', true)
    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
    ->get();
```

#### NoteController.php
```php
$teamMembers = \App\Models\User::where('is_active', true)
    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
    ->get();
```

#### CalendarController.php
```php
$teamMembers = \App\Models\User::where('is_active', true)
    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
    ->get();
```

#### SurveyController.php（2箇所）
```php
$teamMembers = \App\Models\User::where('is_active', true)
    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
    ->get();
```

#### CompanyEventRequestController.php
```php
$companyAdmins = User::where('role_type', 'company_admin')
    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
    ->get();
```

### 3. AdminUserController.php
UserDataクラスを使用しているため、修正不要。

## 効果
- ログイン時にJSONエラーが発生しなくなった
- 不要なリレーションデータがフロントエンドに送信されなくなった
- パフォーマンスが向上（必要な列のみを取得）
- セキュリティが向上（パスワードハッシュなどの機密情報が送信されない）

## 確認事項
- ✅ ログイン処理
- ✅ ダッシュボード表示
- ✅ カレンダー表示
- ✅ 共有メモ表示
- ✅ アンケート表示
- ✅ プロフィール編集
- ✅ 新規登録

## 関連ファイル
- app/Http/Middleware/HandleInertiaRequests.php
- app/Http/Controllers/DashboardController.php
- app/Http/Controllers/NoteController.php
- app/Http/Controllers/CalendarController.php
- app/Http/Controllers/SurveyController.php
- app/Http/Controllers/CompanyEventRequestController.php
