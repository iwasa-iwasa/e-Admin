# 外部キー制約エラー デバッグガイド

## 問題の概要
アンケート回答送信時に外部キー制約エラーが発生する場合の対処法

## 解決済みの問題

### 1. モデルの不整合
**問題**: `Answer`モデルと`SurveyAnswer`モデルが混在していた
- `Answer`モデル → `answers`テーブル（存在しない）
- `SurveyAnswer`モデル → `survey_answers`テーブル（正しい）

**解決**: 
- `Answer`モデルを削除
- `SurveyController`で`SurveyAnswer`モデルのみを使用

### 2. 外部キー関係の修正
**修正内容**:
```php
// SurveyResponse.php
public function answers()
{
    return $this->hasMany(SurveyAnswer::class, 'response_id', 'response_id');
}

// SurveyAnswer.php  
public function response()
{
    return $this->belongsTo(SurveyResponse::class, 'response_id', 'response_id');
}
```

## 確認済みの正しいデータベース構造

### テーブル構造
- ✅ `surveys` テーブル
- ✅ `survey_questions` テーブル  
- ✅ `survey_question_options` テーブル
- ✅ `survey_responses` テーブル
- ✅ `survey_answers` テーブル

### 主キー
- `survey_responses.response_id` (bigint unsigned)
- `survey_answers.answer_id` (bigint unsigned)

### 外部キー制約
- `survey_answers.response_id` → `survey_responses.response_id`
- `survey_answers.question_id` → `survey_questions.question_id`
- `survey_answers.selected_option_id` → `survey_question_options.option_id`

## 今後のエラー発生時の確認項目

### 1. データベーステーブル構造の確認
```sql
-- テーブルの存在確認
SHOW TABLES LIKE 'survey%';

-- 主キーの確認
SHOW CREATE TABLE survey_responses;
SHOW CREATE TABLE survey_answers;
```

### 2. モデルの主キー設定確認
```php
// SurveyResponse.php
protected $primaryKey = 'response_id';

// SurveyAnswer.php  
protected $primaryKey = 'answer_id';
```

### 3. データ型の一致確認
```sql
-- response_idのデータ型が一致しているか
SHOW COLUMNS FROM survey_responses WHERE Field = 'response_id';
SHOW COLUMNS FROM survey_answers WHERE Field = 'response_id';
```

### 4. 保存順序の確認
```php
// 必ず survey_responses → survey_answers の順で保存
$response = SurveyResponse::create([...]);
SurveyAnswer::create([
    'response_id' => $response->response_id, // 正しいIDを使用
    // ...
]);
```

### 5. デバッグログの追加
```php
try {
    DB::transaction(function () use ($survey, $validated) {
        $response = SurveyResponse::create([...]);
        \Log::info('Created response_id: ' . $response->response_id);
        
        foreach ($validated['answers'] as $questionId => $answerText) {
            \Log::info('Saving answer', [
                'question_id' => $questionId,
                'response_id' => $response->response_id
            ]);
            SurveyAnswer::create([...]);
        }
    });
} catch (\Exception $e) {
    \Log::error('Survey submission failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
```

## よくあるエラーメッセージと対処法

### "SQLSTATE[23000]: Integrity constraint violation"
- 外部キー制約違反
- 参照先のレコードが存在しない
- データ型が一致していない

### "Cannot add or update a child row: a foreign key constraint fails"
- 親テーブルに対応するレコードが存在しない
- 保存順序が間違っている

### "Foreign key constraint is incorrectly formed"
- マイグレーションファイルの外部キー定義が間違っている
- データ型が一致していない

## 緊急時の対処法

### 1. 制約の一時的な無効化（本番環境では非推奨）
```sql
SET FOREIGN_KEY_CHECKS = 0;
-- データ操作
SET FOREIGN_KEY_CHECKS = 1;
```

### 2. マイグレーションの再実行
```bash
php artisan migrate:rollback
php artisan migrate
```

### 3. データベースの再作成（開発環境のみ）
```bash
php artisan migrate:fresh --seed
```