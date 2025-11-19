<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Bell, Plus, Clock, CheckCircle, Undo2, Trash2 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import ReminderDetailDialog from './ReminderDetailDialog.vue'

const props = defineProps<{
  reminders: App.Models.Reminder[]
}>()

const selectedReminder = ref<App.Models.Reminder | null>(null)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedReminder = ref<App.Models.Reminder | null>(null)
const showCompleted = ref(false)
const reminderToDelete = ref<App.Models.Reminder | null>(null)

const completedCount = computed(() => props.reminders.filter((r) => r.completed).length)
const activeCount = computed(() => props.reminders.filter((r) => !r.completed).length)
const displayedReminders = computed(() => {
  return showCompleted.value 
    ? props.reminders.filter((r) => r.completed)
    : props.reminders.filter((r) => !r.completed)
})

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
    lastDeletedReminder.value = null
  }, 4000)
}

const handleToggleComplete = (id: number, checked: boolean) => {
  if (checked) {
    const reminder = props.reminders.find(r => r.reminder_id === id)
    if (reminder) {
      lastDeletedReminder.value = reminder
    }
    
    router.patch(route('reminders.complete', id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        showMessage('リマインダーを削除しました。', 'delete')
      },
      onError: (errors) => {
        console.error('完了エラー:', errors)
        lastDeletedReminder.value = null
        showMessage('リマインダーの削除に失敗しました。', 'success')
      },
    })
  } else {
    router.post(route('reminders.restore'), {
      reminder_id: id
    }, {
      preserveScroll: true,
      onSuccess: () => {
        showMessage('リマインダーが元に戻されました。', 'success')
      },
      onError: (errors) => {
        console.error('復元エラー:', errors)
        showMessage('元に戻す処理に失敗しました。', 'success')
      },
    })
  }
}

const handleUndoDelete = () => {
  if (!lastDeletedReminder.value) return

  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  saveMessage.value = '元に戻しています...'
  
  const reminderToRestore = lastDeletedReminder.value
  lastDeletedReminder.value = null

  router.post(route('reminders.restore'), {
    reminder_id: reminderToRestore.reminder_id
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showMessage('リマインダーが元に戻されました。', 'success')
    },
    onError: () => {
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  })
}
const handleUpdateReminder = (updatedReminder: App.Models.Reminder) => {}
const isCreateDialogOpen = ref(false)

const handleCloseDetailDialog = (isOpen: boolean) => {
  if (!isOpen) {
    selectedReminder.value = null
  }
}

const handleCloseCreateDialog = (isOpen: boolean) => {
  isCreateDialogOpen.value = isOpen
}

const handlePermanentDelete = (reminder: App.Models.Reminder) => {
  reminderToDelete.value = reminder
}

const confirmPermanentDelete = () => {
  if (!reminderToDelete.value) return
  
  const deleteId = reminderToDelete.value.reminder_id
  const reminder = reminderToDelete.value
  
  router.delete(route('reminders.destroy', deleteId), {
    onSuccess: () => {
      showMessage(`「${reminder.title}」を完全に削除しました`, 'success')
    },
    onError: (errors) => {
      console.error('Delete error:', errors)
      showMessage('削除に失敗しました', 'success')
    }
  })
  
  reminderToDelete.value = null
}

</script>

<template>
  <Card class="h-full flex flex-col relative">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Bell class="h-5 w-5 text-blue-600" />
          <CardTitle class="text-lg">個人リマインダー</CardTitle>
        </div>
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
            <button
              @click="showCompleted = false"
              :class="['flex items-center gap-1.5 py-1.5 px-3 rounded text-xs transition-all', !showCompleted ? 'bg-white shadow-sm border border-input text-gray-900' : 'hover:bg-gray-200 text-gray-500']"
            >
              <Clock :class="['h-3.5 w-3.5', !showCompleted ? 'text-blue-500' : 'text-gray-400']" />
              未完了
              <Badge variant="secondary" class="text-xs h-4 px-1 ml-1">
                {{ activeCount }}
              </Badge>
            </button>
            <button
              @click="showCompleted = true"
              :class="['flex items-center gap-1.5 py-1.5 px-3 rounded text-xs transition-all', showCompleted ? 'bg-white shadow-sm border border-input text-gray-900' : 'hover:bg-gray-200 text-gray-500']"
            >
              <CheckCircle :class="['h-3.5 w-3.5', showCompleted ? 'text-green-500' : 'text-gray-400']" />
              完了済み
              <Badge variant="secondary" class="text-xs h-4 px-1 ml-1">
                {{ completedCount }}
              </Badge>
            </button>
          </div>
          <Button
            size="sm"
            variant="outline"
            class="h-8 gap-1"
            @click="isCreateDialogOpen = true"
          >
            <Plus class="h-3 w-3" />
            新規作成
          </Button>
        </div>
      </div>
      <p v-if="completedCount > 0" class="text-xs text-gray-500 mt-2">
        ※ チェックした項目は次回ログイン時にゴミ箱へ移動します
      </p>
    </CardHeader>
    <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
      <ScrollArea class="h-full">
        <div class="space-y-4">
          <Card
            v-for="reminder in displayedReminders"
            :key="reminder.reminder_id"
            :class="['transition-all cursor-pointer', reminder.completed ? 'opacity-60' : 'hover:shadow-md']"
@click="(e) => { if (!(e.target as HTMLElement).closest('input[type=\'checkbox\'], button')) { selectedReminder = reminder } }"
          >
            <CardHeader>
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 flex-1">
                  <input
                    type="checkbox"
                    :checked="reminder.completed"
                    @change="handleToggleComplete(reminder.reminder_id, ($event.target as HTMLInputElement).checked)"
                    class="mt-1 h-4 w-4 text-blue-600 rounded"
                  />
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <CardTitle
                        :class="['text-lg cursor-pointer hover:text-blue-600 transition-colors', reminder.completed ? 'line-through text-gray-500' : '']"
                      >
                        {{ reminder.title }}
                      </CardTitle>
                      <Badge
                        :variant="reminder.completed ? 'secondary' : 'outline'"
                        :class="['text-xs', reminder.completed ? 'opacity-60' : '']"  
                      >
                        {{ reminder.category }}
                      </Badge>
                    </div>
                    <p v-if="reminder.description" :class="['text-sm text-gray-600 mb-3', reminder.completed ? 'text-gray-500 line-through' : '']">
                      {{ reminder.description }}
                    </p>
                    <div :class="['flex flex-wrap items-center gap-3 text-xs text-gray-500', reminder.completed ? 'opacity-60' : '']">
                      <div class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        期限: {{ formatDate(reminder.deadline) }}
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <Button
                    variant="outline"
                    size="sm"
                    class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700"
                    @click.stop="handlePermanentDelete(reminder)"
                  >
                    <Trash2 class="h-4 w-4" />
                    完全に削除
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600">ステータス</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                  <CheckCircle v-if="reminder.completed" class="h-4 w-4 text-green-600" />
                  <Clock v-else class="h-4 w-4 text-blue-600" />
                  <span :class="reminder.completed ? 'text-green-600' : 'text-blue-600'">
                    {{ reminder.completed ? '完了済み' : '未完了' }}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </ScrollArea>
    </CardContent>

    <ReminderDetailDialog
      :reminder="selectedReminder"
      :open="selectedReminder !== null"
      @update:open="handleCloseDetailDialog"
      @update:reminder="handleUpdateReminder"
    />

    <ReminderDetailDialog
      :reminder="null"
      :open="isCreateDialogOpen"
      @update:open="handleCloseCreateDialog"
      @update:reminder="handleUpdateReminder"
    />

    <AlertDialog :open="reminderToDelete !== null">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>完全に削除しますか？</AlertDialogTitle>
          <AlertDialogDescription>このアイテムを完全に削除します。この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="reminderToDelete = null" class="hover:bg-gray-100">キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmPermanentDelete" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700">
            完全に削除
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
    
    <!-- メッセージ表示 -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="transform opacity-0 translate-y-full"
      enter-to-class="transform opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="transform opacity-100 translate-y-0"
      leave-to-class="transform opacity-0 translate-y-full"
    >
      <div 
        v-if="saveMessage"
        :class="['absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 p-3 text-white rounded-lg shadow-lg',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
          <Button 
            v-if="messageType === 'delete' && lastDeletedReminder"
            variant="link"
            :class="messageType === 'delete' ? 'text-white hover:bg-red-400 p-1 h-auto ml-2' : 'text-white hover:bg-green-400 p-1 h-auto ml-2'"
            @click.stop="handleUndoDelete"
          >
            <Undo2 class="h-4 w-4 mr-1" />
            <span class="underline">元に戻す</span>
          </Button>
        </div>
      </div>
    </Transition>
  </Card>
</template>
