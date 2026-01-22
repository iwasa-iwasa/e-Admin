<script setup lang="ts">
/**
 * DateTimeInput - Semantic Layer
 * 責務: 日時入力の意味的な操作、表示フォーマット管理、時刻省略オプション
 * 持つもの: フォーマット変換、ローカライゼーション
 * 持たないもの: ドメイン固有のロジック
 */

import { computed } from 'vue'
import BaseDateInput from './BaseDateInput.vue'

interface Props {
  modelValue: Date | string | null
  showTime?: boolean
  format?: string
  locale?: string
  disabled?: boolean
  placeholder?: string
}

interface Emits {
  (e: 'update:modelValue', value: Date | string | null): void
}

const props = withDefaults(defineProps<Props>(), {
  showTime: true,
  format: 'ja-JP',
  locale: 'ja-JP'
})

const emit = defineEmits<Emits>()

// State: 内部フォーマット変換状態
const inputType = computed(() => props.showTime ? 'datetime-local' : 'date')

// フォーマット変換ロジック
const internalValue = computed(() => {
  if (!props.modelValue) return ''
  
  const date = props.modelValue instanceof Date 
    ? props.modelValue 
    : new Date(props.modelValue)
    
  if (isNaN(date.getTime())) return ''
  
  // datetime-local形式に変換 (YYYY-MM-DDTHH:mm)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  
  if (!props.showTime) {
    return `${year}-${month}-${day}`
  }
  
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  
  return `${year}-${month}-${day}T${hours}:${minutes}`
})

const handleInput = (value: string) => {
  if (!value) {
    emit('update:modelValue', null)
    return
  }
  
  const date = new Date(value)
  if (isNaN(date.getTime())) {
    emit('update:modelValue', null)
    return
  }
  
  emit('update:modelValue', date)
}
</script>

<template>
  <BaseDateInput
    :type="inputType"
    :model-value="internalValue"
    @update:model-value="handleInput"
    :disabled="disabled"
    :placeholder="placeholder"
  />
</template>