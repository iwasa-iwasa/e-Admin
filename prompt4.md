ご指摘いただいた以下のファイルが未変換でしたので、これらの変換とルーティング設定をお願いします。

- `CreateSurvey.tsx`
- `DisplayMonitor.tsx`
- `MemberCalendarPage.tsx`
- `SurveyResultsPage.tsx`
- `components/figma/ImageWithFallback.tsx`

### 1. ファイルの変換指示

これまでの指示と同様のルール（Vue 3 Composition API, TypeScript, shadcn-vueの利用）に従って、以下のコンポーネントを変換してください。

| 元ファイル | 新ファイル（例） | 変換指示 |
| :--- | :--- | :--- |
| `CreateSurvey.tsx` | `resources/js/Components/CreateSurveyForm.vue` | Vue 3のフォームとして変換します。`<Input>`, `<Textarea>`, `<RadioGroup>`, `<Checkbox>`, `<Button>`など、`shadcn-vue`のフォーム部品を全面的に利用してアンケート作成フォームを構築してください。 |
| `DisplayMonitor.tsx` | `resources/js/Components/DisplayMonitor.vue` | Vue 3コンポーネントに変換します。`<Card>`でレイアウトを構成し、データの可視化には`shadcn-vue`の`<Progress>`や`<Chart>`コンポーネントを利用してください。 |
| `MemberCalendarPage.tsx` | `resources/js/Pages/MemberCalendar.vue` | Vue 3のページコンポーネントに変換します。`shadcn-vue`の`<Calendar>`をメイン要素として使用してください。特定のメンバーのカレンダーを表示するページになる想定です。 |
| `SurveyResultsPage.tsx` | `resources/js/Pages/SurveyResults.vue` | Vue 3のページコンポーネントに変換します。アンケート結果をグラフで表示するために、`shadcn-vue`の`<Chart>`コンポーネントを積極的に利用してください。また、詳細な回答データを`<Table>`で表示する部分も作成してください。 |
| `components/figma/ImageWithFallback.tsx` | `resources/js/Components/ImageWithFallback.vue` | このユーティリティコンポーネントをVue 3に変換します。画像の読み込みエラーをハンドリングし（`<img>`タグの`@error`イベントを利用）、フォールバック用の表示に切り替えるロジックを再現してください。 |

---

### 2. Laravelルーティングの追加

次に、上記で作成した新しいページコンポーネント (`MemberCalendarPage` と `SurveyResultsPage`) のために、`routes/web.php` ファイルに以下のルートを**追記**してください。

**`routes/web.php` に追記する内容:**

```php
// ... 既存のルート定義の末尾に追記 ...

// メンバーカレンダーページ用のルート
Route::get('/member-calendar', function () {
    return Inertia::render('MemberCalendar');
})->middleware(['auth', 'verified'])->name('member.calendar');

// アンケート結果ページ用のルート
// {survey} の部分は実際のアプリケーションに合わせて調整してください
Route::get('/surveys/{survey}/results', function () {
    // 必要に応じて、コントローラー経由でアンケート結果データをPropsとして渡します
    return Inertia::render('SurveyResults');
})->middleware(['auth', 'verified'])->name('surveys.results');
```

以上、よろしくお願いします。