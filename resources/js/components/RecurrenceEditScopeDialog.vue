<script setup lang="ts">
import { ref } from 'vue'
import { AlertTriangle, Calendar, Repeat } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

export type EditScope = 'this-only' | 'this-and-future' | 'all'

const props = defineProps<{
  open: boolean
  eventTitle: string
  eventDate: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'scope-selected': [scope: EditScope]
}>()

const selectedScope = ref<EditScope>('all')

const handleConfirm = () => {
  emit('scope-selected', selectedScope.value)
  emit('update:open', false)
}

const handleCancel = () => {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Repeat class="h-5 w-5 text-blue-600" />
          繰り返し予定の編集
        </DialogTitle>
        <DialogDescription>
          この予定は繰り返し予定です。編集範囲を選択してください。
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg p-3 flex items-start gap-2">
          <AlertTriangle class="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
          <div class="text-sm text-amber-800 dark:text-amber-200">
            <p class="font-medium">{{ eventTitle }}</p>
            <p class="text-amber-700 dark:text-amber-300">{{ new Date(eventDate).toLocaleDateString('ja-JP') }}</p>
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex items-start space-x-3 p-3 border border-border rounded-lg hover:bg-accent dark:hover:bg-accent/50">
            <input type="radio" v-model="selectedScope" value="this-only" id="this-only" class="mt-1" />
            <div class="flex-1">
              <Label for="this-only" class="font-medium cursor-pointer">この予定のみ</Label>
              <p class="text-sm text-muted-foreground mt-1">
                この日の予定だけを変更します。他の繰り返し予定は変更されません。
              </p>
            </div>
          </div>

          <div class="flex items-start space-x-3 p-3 border border-border rounded-lg hover:bg-accent dark:hover:bg-accent/50">
            <input type="radio" v-model="selectedScope" value="this-and-future" id="this-and-future" class="mt-1" />
            <div class="flex-1">
              <Label for="this-and-future" class="font-medium cursor-pointer">この予定以降</Label>
              <p class="text-sm text-muted-foreground mt-1">
                この日以降の繰り返し予定をすべて変更します。過去の予定は変更されません。
              </p>
            </div>
          </div>

          <div class="flex items-start space-x-3 p-3 border rounded-lg hover:bg-accent dark:hover:bg-accent/50 bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800">
            <input type="radio" v-model="selectedScope" value="all" id="all" class="mt-1" />
            <div class="flex-1">
              <Label for="all" class="font-medium cursor-pointer text-gray-900 dark:text-gray-100">すべての予定</Label>
              <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                過去・現在・未来のすべての繰り返し予定を変更します。
              </p>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleCancel">
          キャンセル
        </Button>
        <Button @click="handleConfirm">
          編集を続行
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>