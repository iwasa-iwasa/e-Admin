<script setup lang="ts">
/**
 * DeadlineInput - Domain Layer
 * 責務: 「期限」というドメイン概念、期限なし/ありの切替、相対指定
 * 持つもの: ドメインロジック、業務ルール
 * 持たないもの: 低レベルな入力制御
 */

import { ref, computed, watch } from 'vue'
import DateTimeInput from './DateTimeInput.vue'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Label } from '@/components/ui/label'

// Domain型定義
type DeadlineValue =
  | { mode: 'none' }
  | { mode: 'absolute'; date: string; time?: string }
  | { mode: 'relative'; days: number }

interface Props {
  modelValue: DeadlineValue
  required?: boolean
  allowNoDeadline?: boolean
  relativeOptions?: string[]
  minDate?: Date
}

interface Emits {
  (e: 'update:modelValue', value: DeadlineValue): void
}

const props = withDefaults(defineProps<Props>(), {
  required: false,
  allowNoDeadline: true,
  relativeOptions: () => ['今日', '明日', '1週間後', '1ヶ月後']
})

const emit = defineEmits<Emits>()

// State: 期限設定モード（絶対/相対/なし）
const currentMode = ref<'none' | 'absolute' | 'relative'>(props.modelValue.mode)
const absoluteDate = ref<Date | null>(null)
const relativeDays = ref<number>(1)

// 初期化
watch(() => props.modelValue, (newValue) => {
  currentMode.value = newValue.mode
  
  if (newValue.mode === 'absolute') {
    absoluteDate.value = new Date(newValue.date)
  } else if (newValue.mode === 'relative') {
    relativeDays.value = newValue.days
  }
}, { immediate: true })

// 業務ルール: 必須チェック
const isValid = computed(() => {
  if (props.required && currentMode.value === 'none') {
    return false
  }
  return true
})

// モード切替ハンドラー
const handleModeChange = (mode: 'none' | 'absolute' | 'relative') => {
  currentMode.value = mode
  
  switch (mode) {
    case 'none':
      emit('update:modelValue', { mode: 'none' })
      break
    case 'absolute':
      if (absoluteDate.value) {
        emit('update:modelValue', {
          mode: 'absolute',
          date: absoluteDate.value.toISOString(),
          time: absoluteDate.value.toTimeString().slice(0, 5)
        })
      }
      break
    case 'relative':
      emit('update:modelValue', {
        mode: 'relative',
        days: relativeDays.value
      })
      break
  }
}

// 絶対日時変更ハンドラー
const handleAbsoluteDateChange = (date: Date | string | null) => {
  if (!date) {
    absoluteDate.value = null
    return
  }
  
  const dateObj = date instanceof Date ? date : new Date(date)
  absoluteDate.value = dateObj
  
  if (currentMode.value === 'absolute') {
    emit('update:modelValue', {
      mode: 'absolute',
      date: dateObj.toISOString(),
      time: dateObj.toTimeString().slice(0, 5)
    })
  }
}

// 相対日数変更ハンドラー
const handleRelativeDaysChange = (days: number) => {
  relativeDays.value = days
  
  if (currentMode.value === 'relative') {
    emit('update:modelValue', {
      mode: 'relative',
      days
    })
  }
}

// 相対オプションのマッピング
const relativeOptionsMap: Record<string, number> = {
  '今日': 0,
  '明日': 1,
  '1週間後': 7,
  '1ヶ月後': 30
}

const onRelativeSelectChange = (value: unknown) => {
  if (value == null) return

  let days: number | null = null

  if (typeof value === 'string') {
    days =
      value in relativeOptionsMap
        ? relativeOptionsMap[value]
        : Number(value)
  } else if (typeof value === 'number') {
    days = value
  } else {
    // bigint は仕様外 → 無視
    return
  }

  if (Number.isFinite(days)) {
    handleRelativeDaysChange(days)
  }
}


</script>

<template>
  <div class="space-y-3">
    <Label>期限設定</Label>
    
    <!-- モード選択 -->
    <div class="flex gap-2">
      <Button
        v-if="allowNoDeadline"
        type="button"
        :variant="currentMode === 'none' ? 'default' : 'outline'"
        size="sm"
        @click="handleModeChange('none')"
      >
        期限なし
      </Button>
      
      <Button
        type="button"
        :variant="currentMode === 'absolute' ? 'default' : 'outline'"
        size="sm"
        @click="handleModeChange('absolute')"
      >
        日時指定
      </Button>
      
      <Button
        type="button"
        :variant="currentMode === 'relative' ? 'default' : 'outline'"
        size="sm"
        @click="handleModeChange('relative')"
      >
        相対指定
      </Button>
    </div>
    
    <!-- 絶対日時入力 -->
    <div v-if="currentMode === 'absolute'" class="space-y-2">
      <Label>期限日時</Label>
      <DateTimeInput
        :model-value="absoluteDate"
        @update:model-value="handleAbsoluteDateChange"
        :show-time="true"
      />
    </div>
    
    <!-- 相対指定選択 -->
    <div v-if="currentMode === 'relative'" class="space-y-2">
      <Label>期限</Label>
      <Select
        :model-value="String(relativeDays)"
        @update:model-value="onRelativeSelectChange"
      >

        <SelectTrigger>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in relativeOptions"
            :key="option"
            :value="option"
          >
            {{ option }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    
    <!-- バリデーションエラー -->
    <div v-if="!isValid" class="text-sm text-red-600">
      期限の設定が必要です
    </div>
  </div>
</template>