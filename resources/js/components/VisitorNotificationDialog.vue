<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Users class="h-5 w-5 text-orange-500" />
          来客予定のお知らせ
        </DialogTitle>
        <DialogDescription>
          来客予定の開始時刻が近づいています
        </DialogDescription>
        <button 
          @click="$emit('update:open', false)"
          class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
          <X class="h-4 w-4" />
        </button>
      </DialogHeader>
      
      <div class="py-4">
        <div class="text-center mb-4">
          <div class="text-lg font-semibold text-orange-600 mb-2">
            30分後に来客の予定です
          </div>
          <div class="text-xl font-bold mb-2">{{ event?.title }}</div>
          <div class="text-lg text-gray-600">
            {{ formatTime(event?.start_time) }} - {{ formatTime(event?.end_time) }}
          </div>
        </div>
      </div>
      
      <DialogFooter>
        <Button @click="$emit('update:open', false)" class="w-full">
          閉じる
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Users, X } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

defineProps<{
  open: boolean
  event: App.Models.Event | null
}>()

defineEmits<{
  'update:open': [value: boolean]
}>()

const formatTime = (time: string | null | undefined) => {
  if (!time) return '時刻未設定'
  
  if (typeof time === 'string') {
    // ISO 8601形式の場合: "2026-01-28T06:27:00.000000Z"
    if (time.includes('T')) {
      const date = new Date(time)
      return date.toLocaleTimeString('ja-JP', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
      })
    }
    // 通常の時刻形式の場合: "06:27:00"
    return time.substring(0, 5)
  }
  
  return '時刻未設定'
}
</script>