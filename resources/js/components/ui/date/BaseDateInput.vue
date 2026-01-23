<script setup lang="ts">
/**
 * BaseDateInput - Primitive Layer
 * 責務: ネイティブinput要素のラッパー、shadcn/uiスタイル適用、基本v-model対応
 * 持つもの: スタイリング、基本バリデーション
 * 持たないもの: フォーマット変換、ドメインロジック、UI状態管理
 */

interface Props {
  type: 'date' | 'time' | 'datetime-local'
  modelValue: string
  disabled?: boolean
  placeholder?: string
  class?: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

defineProps<Props>()
defineEmits<Emits>()

// State: なし（完全にstateless）
</script>

<template>
  <input
    :type="type"
    :value="modelValue"
    @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    :disabled="disabled"
    :placeholder="placeholder"
    :class="[
      'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    ]"
  />
</template>