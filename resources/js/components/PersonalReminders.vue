<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Bell, Plus, Clock, CheckCircle, Undo2 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Checkbox } from '@/components/ui/checkbox'
import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'

const props = defineProps<{
  reminders: App.Models.Reminder[]
}>()

const selectedReminder = ref<App.Models.Reminder | null>(null)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedReminder = ref<App.Models.Reminder | null>(null)
const showCompleted = ref(false)

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

</script>

<template>
  <Card class="h-full flex flex-col relative">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Bell class="h-5 w-5 text-blue-600" />
          <CardTitle class="text-lg">個人リマインダー</CardTitle>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
            <button
              @click="showCompleted = false"
              :class="['flex items-center gap-1.5 py-1.5 px-3 rounded text-xs transition-all', !showCompleted ? 'bg-white shadow-sm border border-input text-gray-900' : 'hover:bg-gray-200 text-gray-500']"
            >
              <Clock :class="['h-3.5 w-3.5', !showCompleted ? 'text-blue-500' : 'text-gray-400']" />
              アクティブ
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
        <div class="space-y-3">
          <div
            v-for="reminder in displayedReminders"
            :key="reminder.reminder_id"
            :class="['border-2 rounded-lg p-3 transition-all cursor-pointer', reminder.completed ? 'border-gray-300 bg-gray-100 opacity-60' : 'border-gray-200 bg-gray-50 hover:shadow-md']"
            @click="(e) => { if (!(e.target as HTMLElement).closest('input[type=\'checkbox\']')) { selectedReminder = reminder } }"
          >
            <div class="flex items-start gap-3">
              <input
                type="checkbox"
                :checked="reminder.completed"
                @change="handleToggleComplete(reminder.reminder_id, ($event.target as HTMLInputElement).checked)"
                class="mt-1 h-4 w-4 text-blue-600 rounded"
              />
              <div class="flex-1">
                <h4 :class="['mb-2', reminder.completed ? 'line-through text-gray-500' : '']">
                  {{ reminder.title }}
                </h4>
                <p v-if="reminder.description" :class="['text-sm mb-2', reminder.completed ? 'text-gray-500 line-through' : 'text-gray-600']">
                  {{ reminder.description }}
                </p>
                <div :class="['flex items-center gap-4 text-xs', reminder.completed ? 'text-gray-500' : 'text-gray-600']">
                  <div class="flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    期限: {{ formatDate(reminder.deadline) }}
                  </div>
                  <Badge variant="outline" :class="['text-xs', reminder.completed ? 'opacity-60' : '']">
                    {{ reminder.category }}
                  </Badge>
                </div>
              </div>
            </div>
          </div>
        </div>
      </ScrollArea>
    </CardContent>

    <ReminderDetailDialog
      :reminder="selectedReminder"
      :open="selectedReminder !== null"
      @update:open="(isOpen) => !isOpen && (selectedReminder = null)"
      @update:reminder="handleUpdateReminder"
    />

    <ReminderDetailDialog
      :reminder="null"
      :open="isCreateDialogOpen"
      @update:open="isCreateDialogOpen = $event"
      @update:reminder="handleUpdateReminder"
    />
    
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
