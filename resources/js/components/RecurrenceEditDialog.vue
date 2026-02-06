<script setup lang="ts">
import { ref, computed } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

const props = defineProps<{
  open: boolean
  mode: 'edit' | 'delete'
  eventDate: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'confirm': [scope: 'this-only' | 'this-and-future' | 'all']
}>()

const selectedScope = ref<'this-only' | 'this-and-future' | 'all'>('this-only')

const formattedDate = computed(() => {
  const date = new Date(props.eventDate)
  return date.toLocaleDateString('ja-JP', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    weekday: 'short'
  })
})

const handleConfirm = () => {
  emit('confirm', selectedScope.value)
  emit('update:open', false)
}

const handleCancel = () => {
  emit('update:open', false)
  selectedScope.value = 'this-only'
}
</script>

<template>
  <Dialog :open="open" @update:open="handleCancel">
    <DialogContent class="max-w-md z-[100]">
      <DialogHeader>
        <DialogTitle>この予定は繰り返しです</DialogTitle>
        <DialogDescription>
          どの範囲を{{ mode === 'edit' ? '編集' : '削除' }}しますか？
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-3">
        <div 
          class="flex items-start space-x-3 p-3 rounded-lg border-2 transition-colors cursor-pointer"
          :class="selectedScope === 'this-only' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : 'border-gray-200 dark:border-gray-700'"
          @click="selectedScope = 'this-only'"
        >
          <div class="flex items-center justify-center w-5 h-5 mt-1 rounded-full border-2 transition-colors"
               :class="selectedScope === 'this-only' ? 'border-blue-500' : 'border-gray-300 dark:border-gray-600'">
            <div v-if="selectedScope === 'this-only'" class="w-3 h-3 rounded-full bg-blue-500"></div>
          </div>
          <div class="flex-1">
            <Label class="font-medium cursor-pointer" :class="selectedScope === 'this-only' ? 'text-gray-900 dark:text-gray-100' : ''">
              この予定のみ{{ mode === 'edit' ? '編集' : '削除' }}
            </Label>
            <p class="text-sm mt-1" :class="selectedScope === 'this-only' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-600 dark:text-gray-400'">
              {{ formattedDate }}のみ{{ mode === 'edit' ? '変更' : '削除' }}します
            </p>
          </div>
        </div>

        <div 
          class="flex items-start space-x-3 p-3 rounded-lg border-2 transition-colors cursor-pointer"
          :class="selectedScope === 'this-and-future' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : 'border-gray-200 dark:border-gray-700'"
          @click="selectedScope = 'this-and-future'"
        >
          <div class="flex items-center justify-center w-5 h-5 mt-1 rounded-full border-2 transition-colors"
               :class="selectedScope === 'this-and-future' ? 'border-blue-500' : 'border-gray-300 dark:border-gray-600'">
            <div v-if="selectedScope === 'this-and-future'" class="w-3 h-3 rounded-full bg-blue-500"></div>
          </div>
          <div class="flex-1">
            <Label class="font-medium cursor-pointer" :class="selectedScope === 'this-and-future' ? 'text-gray-900 dark:text-gray-100' : ''">
              この予定以降すべて{{ mode === 'edit' ? '編集' : '削除' }}
            </Label>
            <p class="text-sm mt-1" :class="selectedScope === 'this-and-future' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-600 dark:text-gray-400'">
              {{ formattedDate }}以降の予定をすべて{{ mode === 'edit' ? '変更' : '削除' }}します
            </p>
            <p v-if="mode === 'delete'" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
              ※ 過去の予定は残ります
            </p>
          </div>
        </div>

        <div 
          class="flex items-start space-x-3 p-3 rounded-lg border-2 transition-colors cursor-pointer"
          :class="selectedScope === 'all' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30' : 'border-gray-200 dark:border-gray-700'"
          @click="selectedScope = 'all'"
        >
          <div class="flex items-center justify-center w-5 h-5 mt-1 rounded-full border-2 transition-colors"
               :class="selectedScope === 'all' ? 'border-blue-500' : 'border-gray-300 dark:border-gray-600'">
            <div v-if="selectedScope === 'all'" class="w-3 h-3 rounded-full bg-blue-500"></div>
          </div>
          <div class="flex-1">
            <Label class="font-medium cursor-pointer" :class="selectedScope === 'all' ? 'text-gray-900 dark:text-gray-100' : ''">
              すべての予定を{{ mode === 'edit' ? '編集' : '削除' }}
            </Label>
            <p class="text-sm mt-1" :class="selectedScope === 'all' ? 'text-gray-700 dark:text-gray-300' : 'text-gray-600 dark:text-gray-400'">
              過去も含めてすべての繰り返し予定を{{ mode === 'edit' ? '変更' : '削除' }}します
            </p>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleCancel">
          キャンセル
        </Button>
        <Button 
          @click="handleConfirm"
          :class="mode === 'delete' ? 'bg-red-600 hover:bg-red-700' : ''"
        >
          {{ mode === 'edit' ? '続ける' : '削除' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
