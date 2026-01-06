# フォーカス復元ディレクティブ (v-focus-restore)

## 概要
モーダルやポップオーバーを閉じた後、最後にフォーカスされていた入力要素に自動的にフォーカスを戻す共通仕組み。

## クイックスタート

### 基本的な使い方
```vue
<template>
  <input type="text" v-model="searchQuery" />
  
  <Popover v-model:open="isFilterOpen" v-focus-restore="isFilterOpen">
    <PopoverTrigger>
      <Button>フィルター</Button>
    </PopoverTrigger>
    <PopoverContent>
      <!-- フィルター設定 -->
    </PopoverContent>
  </Popover>
</template>
```

**ポイント:**
- `v-model:open` を持つコンポーネントに適用
- `v-model:open` と `v-focus-restore` に同じ ref 変数を渡す（必須）
- 入力要素自体には何も追加不要

## ディレクティブ適用のルール

### 適用先
`v-model:open` を持つ開閉状態管理コンポーネントのルート要素に適用します。

### 渡す値
`v-model:open` と同じ ref 変数を渡すことが必須です。

### ✅ OK な例
```vue
<!-- Popover -->
<Popover v-model:open="isOpen" v-focus-restore="isOpen">
  <PopoverContent>...</PopoverContent>
</Popover>

<!-- Dialog -->
<Dialog v-model:open="isDialogOpen" v-focus-restore="isDialogOpen">
  <DialogContent>...</DialogContent>
</Dialog>

<!-- DropdownMenu -->
<DropdownMenu v-model:open="isMenuOpen" v-focus-restore="isMenuOpen">
  <DropdownMenuContent>...</DropdownMenuContent>
</DropdownMenu>
```

### ❌ NG な例
```vue
<!-- ❌ 子要素に適用 -->
<Popover v-model:open="isOpen">
  <PopoverContent v-focus-restore="isOpen">...</PopoverContent>
</Popover>

<!-- ❌ 入力要素に適用 -->
<input v-focus-restore="isOpen" />

<!-- ❌ 異なる変数を渡す -->
<Popover v-model:open="isOpen" v-focus-restore="otherValue">
  ...
</Popover>

<!-- ❌ v-model:open がない要素に適用 -->
<div v-focus-restore="isOpen">
  ...
</div>
```

## フォーカス記憶の対象

以下の要素のフォーカスが自動的に記憶されます：

- `<input>` （全タイプ）
- `<textarea>`
- `<select>`

**対象外の要素:**
- `<button>`
- `contenteditable` 属性を持つ要素
- その他のフォーカス可能要素

## 動作の流れ

1. **フォーカス記憶（常時）**
   - グローバルな focusin イベントで監視
   - アプリ全体で直近にフォーカスされた対象要素を記憶
   - モーダル内の入力要素にフォーカスした場合も記憶される

2. **復元トリガー**
   - ディレクティブに渡した値が `true → false` に変化したとき
   - つまりモーダル/ポップオーバーが閉じたタイミング

3. **フォーカス復元**
   - requestAnimationFrame により次フレームで実行
   - 記憶された要素が DOM に存在する場合のみ復元
   - 存在しない場合は何もせず安全に終了

## 実装例

### モーダルの場合
```vue
<script setup lang="ts">
import { ref } from 'vue'

const isDialogOpen = ref(false)
</script>

<template>
  <Dialog v-model:open="isDialogOpen" v-focus-restore="isDialogOpen">
    <DialogContent>
      <!-- モーダルコンテンツ -->
    </DialogContent>
  </Dialog>
</template>
```

### フィルターの場合
```vue
<script setup lang="ts">
import { ref } from 'vue'

const searchQuery = ref('')
const isFilterOpen = ref(false)
</script>

<template>
  <input type="text" v-model="searchQuery" />
  
  <Popover v-model:open="isFilterOpen" v-focus-restore="isFilterOpen">
    <PopoverTrigger>
      <Button>フィルター</Button>
    </PopoverTrigger>
    <PopoverContent>
      <!-- フィルター設定 -->
    </PopoverContent>
  </Popover>
</template>
```

## 既存実装からの移行

### 削除するもの
- [ ] 入力要素への ref 定義（例: `const xxxInputRef = ref(null)`）
- [ ] 入力要素の `ref` 属性（例: `<input ref="xxxInputRef">`）
- [ ] フォーカス復元用の watch（例: `watch(isOpen, ...)`）
- [ ] watch 内の setTimeout

### 残すもの
- [x] 開閉状態の ref（例: `const isOpen = ref(false)`）
- [x] その他のロジック

### Before
```vue
<script setup lang="ts">
import { ref, watch } from 'vue'

const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)  // ❌ 削除
const isFilterOpen = ref(false)

// ❌ このwatch全体を削除
watch(isFilterOpen, (newValue, oldValue) => {
  if (oldValue === true && newValue === false) {
    setTimeout(() => {
      searchInputRef.value?.focus()
    }, 100)
  }
})
</script>

<template>
  <input ref="searchInputRef" type="text" v-model="searchQuery" />  <!-- ❌ ref削除 -->
  <Popover v-model:open="isFilterOpen">  <!-- ⚠️ ディレクティブ追加 -->
    <PopoverContent>
      <!-- フィルター設定 -->
    </PopoverContent>
  </Popover>
</template>
```

### After
```vue
<script setup lang="ts">
import { ref } from 'vue'  // watch削除

const searchQuery = ref('')
const isFilterOpen = ref(false)
</script>

<template>
  <input type="text" v-model="searchQuery" />
  <Popover v-model:open="isFilterOpen" v-focus-restore="isFilterOpen">
    <PopoverContent>
      <!-- フィルター設定 -->
    </PopoverContent>
  </Popover>
</template>
```

## よくある質問（FAQ）

### Q1: 複数のモーダルが同時に開いている場合は？
**A:** 最後にフォーカスされた要素に戻ります。モーダルAを開き、その中でモーダルBを開いた場合、モーダルBを閉じるとモーダルA内の入力要素に戻り、モーダルAを閉じると元の入力要素に戻ります。

### Q2: ネストしたモーダルでも正しく動作しますか？
**A:** はい。各モーダルが閉じるタイミングで、その時点で記憶されている要素にフォーカスが戻ります。

### Q3: 記憶された要素が DOM から削除されていたら？
**A:** `document.contains()` でチェックし、存在しない場合は何もしません。エラーは発生しません。

### Q4: フォーカス可能な要素が1つも存在しない場合は？
**A:** 何もしません。エラーは発生せず、安全に終了します。

### Q5: モーダル内の入力要素にフォーカスした場合は？
**A:** それが新しい「最後にフォーカスされた要素」として記憶されます。モーダルを閉じると、その要素が DOM に残っていればそこに戻ります。

### Q6: textarea や select でも動作しますか？
**A:** はい。input / textarea / select すべてに対応しています。

### Q7: button にフォーカスした場合は？
**A:** button は記憶対象外です。button にフォーカスしても記憶されず、以前に記憶された入力要素が保持されます。

## 設計思想

### なぜこの設計にしたか

**1. グローバルな focusin イベント**
- 各コンポーネントで個別に ref を管理する必要がない
- 入力要素が増えても自動的に対応
- DOM 構造に依存しない

**2. Directive による宣言的な適用**
- `v-focus-restore="isOpen"` だけで動作
- watch のコピペが不要
- コンポーネントのロジックを汚さない

**3. requestAnimationFrame の使用**
- setTimeout より確実に DOM 更新後に実行
- ブラウザの描画タイミングに合わせて最適化
- UX の劣化を防ぐ

**4. グローバルリスナー方式**
- focusin リスナーは1つだけ
- メモリ効率が良い
- パフォーマンスへの影響が最小
