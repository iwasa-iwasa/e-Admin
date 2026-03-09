<script setup lang="ts">
import { computed } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { AlertCircle, ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
  show: boolean
  conflictData: any
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'resolve', option: 'force_update' | 'cancel'): void
}>()

const isOpen = computed({
  get: () => props.show,
  set: (value) => emit('update:show', value)
})

const currentData = computed(() => props.conflictData?.current_data)
const yourChanges = computed(() => props.conflictData?.your_changes)

// 表示用に日時をフォーマット
const formatDateTime = (dateStr: string, timeStr: string | null, isAllDay: boolean) => {
  if (isAllDay || !timeStr) return `${dateStr} (終日)`
  return `${dateStr} ${timeStr.slice(0, 5)}`
}

const handleForceUpdate = () => {
  emit('resolve', 'force_update')
}

const handleCancel = () => {
  emit('resolve', 'cancel')
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="isOpen = $event">
    <DialogContent class="max-w-[700px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2 text-red-600">
          <AlertCircle class="h-5 w-5" />
          予定の同時編集が検出されました
        </DialogTitle>
        <DialogDescription class="pt-2">
          あなたが編集している間に、他のユーザーがこの予定を更新しました。
          現在のサーバー上のデータと、あなたの編集内容が競合しています。
        </DialogDescription>
      </DialogHeader>

      <div class="grid grid-cols-2 gap-4 py-4" v-if="currentData && yourChanges">
        <!-- サーバー上の現在のデータ -->
        <div class="border rounded-md p-4 bg-muted/20">
          <h3 class="font-bold text-sm text-muted-foreground mb-3 pb-2 border-b">現在のサーバー上のデータ<br>(最新)</h3>
          <div class="space-y-3 text-sm">
            <div>
              <span class="text-xs text-muted-foreground block">タイトル</span>
              <span class="font-medium">{{ currentData.title }}</span>
            </div>
            <div>
              <span class="text-xs text-muted-foreground block">日時</span>
              <span class="font-medium">
                {{ formatDateTime(currentData.start_date, currentData.start_time, currentData.is_all_day) }} 
                〜 
                {{ formatDateTime(currentData.end_date, currentData.end_time, currentData.is_all_day) }}
              </span>
            </div>
            <div>
              <span class="text-xs text-muted-foreground block">場所 / 詳細</span>
              <span class="font-medium line-clamp-2">{{ currentData.location || '-' }} / {{ currentData.description || '-' }}</span>
            </div>
            <div>
              <span class="text-xs text-muted-foreground block">最終更新者</span>
              <span class="font-medium">{{ currentData.creator?.name || '不明' }}</span>
            </div>
          </div>
        </div>

        <!-- あなたの変更 -->
        <div class="border border-blue-200 rounded-md p-4 bg-blue-50/30">
          <h3 class="font-bold text-sm text-blue-700 mb-3 pb-2 border-b border-blue-100">あなたの編集内容</h3>
          <div class="space-y-3 text-sm">
            <div>
              <span class="text-xs text-muted-foreground block">タイトル</span>
              <span class="font-medium text-blue-900">{{ yourChanges.title }}</span>
            </div>
            <div>
              <span class="text-xs text-muted-foreground block">日時</span>
              <span class="font-medium text-blue-900">
                {{ formatDateTime(yourChanges.date_range[0], yourChanges.start_time, String(yourChanges.is_all_day) === '1') }} 
                〜 
                {{ formatDateTime(yourChanges.date_range[1], yourChanges.end_time, String(yourChanges.is_all_day) === '1') }}
              </span>
            </div>
            <div>
              <span class="text-xs text-muted-foreground block">場所 / 詳細</span>
              <span class="font-medium text-blue-900 line-clamp-2">{{ yourChanges.location || '-' }} / {{ yourChanges.description || '-' }}</span>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="sm:justify-between items-center mt-2">
        <Button variant="outline" @click="handleCancel">
          キャンセルして最新データを読み込む
        </Button>
        <Button variant="destructive" @click="handleForceUpdate">
          自分の変更で上書き（強制保存）する
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
