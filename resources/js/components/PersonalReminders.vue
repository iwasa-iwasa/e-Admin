<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Bell, Plus, Clock, CheckCircle, Undo2, Trash2, Search, HelpCircle } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import ReminderDetailDialog from './ReminderDetailDialog.vue'

interface Tag {
  tag_id: number;
  tag_name: string;
}

export interface ReminderModel {
  id?: number;
  reminder_id: number;
  user_id: number;
  title: string;
  description: string | null;
  deadline?: string;
  deadline_date: string | null;
  deadline_time: string | null;
  category: string;
  completed: boolean;
  completed_at: string | null;
  is_deleted: boolean;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
  completedAt?: string;
  tags?: Tag[];
}

const props = defineProps<{
  reminders: ReminderModel[]
}>()

const selectedReminder = ref<ReminderModel | null>(null)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedReminder = ref<ReminderModel | null>(null)
const showCompleted = ref(false)
const reminderToDelete = ref<ReminderModel | null>(null)
const headerRef = ref<HTMLElement | null>(null)
const headerStage = ref<'normal' | 'compact' | 'titleCut' | 'iconOnly'>('normal')
let resizeObserver: ResizeObserver | null = null

const completedCount = computed(() => props.reminders.filter((r) => r.completed).length)
const activeCount = computed(() => props.reminders.filter((r) => !r.completed).length)

const isOverdue = (deadlineDate: string | null, deadlineTime: string | null) => {
  if (!deadlineDate) return false
  const now = new Date()
  const deadline = new Date(deadlineDate)
  if (deadlineTime) {
    const [hours, minutes] = deadlineTime.split(':')
    deadline.setHours(parseInt(hours), parseInt(minutes))
  } else {
    deadline.setHours(23, 59, 59)
  }
  return deadline < now
}

const isUpcoming = (deadlineDate: string | null, deadlineTime: string | null) => {
  if (!deadlineDate) return false
  const now = new Date()
  const deadline = new Date(deadlineDate)
  if (deadlineTime) {
    const [hours, minutes] = deadlineTime.split(':')
    deadline.setHours(parseInt(hours), parseInt(minutes))
  } else {
    deadline.setHours(23, 59, 59)
  }
  const threeDaysLater = new Date(now.getTime() + 3 * 24 * 60 * 60 * 1000)
  return deadline >= now && deadline <= threeDaysLater
}

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
        showMessage('リマインダーを完了しました。', 'delete')
        window.dispatchEvent(new CustomEvent('notification-updated'))
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
        window.dispatchEvent(new CustomEvent('notification-updated'))
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
      window.dispatchEvent(new CustomEvent('notification-updated'))
    },
    onError: () => {
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  })
}
const handleUpdateReminder = (updatedReminder: ReminderModel) => {}
const isCreateDialogOpen = ref(false)
const isHelpOpen = ref(false)

const handleCloseDetailDialog = (isOpen: boolean, completed?: boolean) => {
  if (!isOpen) {
    if (completed && selectedReminder.value) {
      lastDeletedReminder.value = selectedReminder.value
      showMessage('リマインダーを完了しました。', 'delete')
      window.dispatchEvent(new CustomEvent('notification-updated'))
    }
    selectedReminder.value = null
  }
}

const handleCloseCreateDialog = (isOpen: boolean) => {
  isCreateDialogOpen.value = isOpen
}

const handlePermanentDelete = (reminder: ReminderModel) => {
  reminderToDelete.value = reminder
}

const confirmPermanentDelete = () => {
  if (!reminderToDelete.value) return
  
  const deleteId = reminderToDelete.value.reminder_id
  const reminder = reminderToDelete.value
  
  router.delete(route('reminders.destroy', deleteId), {
    onSuccess: () => {
      showMessage(`「${reminder.title}」を完全に削除しました`, 'success')
      window.dispatchEvent(new CustomEvent('notification-updated'))
    },
    onError: (errors) => {
      console.error('Delete error:', errors)
      showMessage('削除に失敗しました', 'success')
    }
  })
  
  reminderToDelete.value = null
}

onMounted(() => {
  if (headerRef.value) {
    resizeObserver = new ResizeObserver(entries => {
      const width = entries[0].contentRect.width
      if (width < 350) {
        headerStage.value = 'iconOnly'
      } else if (width < 500) {
        headerStage.value = 'titleCut'
      } else if (width < 650) {
        headerStage.value = 'compact'
      } else {
        headerStage.value = 'normal'
      }
    })
    resizeObserver.observe(headerRef.value)
  }
  
  // ハイライト機能
  const page = usePage()
  const highlightId = (page.props as any).highlight_reminder
  if (highlightId) {
    nextTick(() => {
      const reminder = props.reminders.find(r => r.reminder_id === highlightId)
      if (reminder) {
        selectedReminder.value = reminder
        setTimeout(() => {
          const element = document.getElementById(`reminder-${highlightId}`)
          if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' })
            element.classList.add('highlight-flash')
            setTimeout(() => element.classList.remove('highlight-flash'), 3000)
          }
        }, 100)
      }
    })
  }
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})

</script>

<template>
  <Card class="h-full flex flex-col relative">
    <CardHeader>
      <div ref="headerRef" class="flex items-center gap-2">
        <div class="flex items-center gap-3 min-w-0">
          <div class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer hover:opacity-70 transition-opacity" @click="router.visit('/reminders')">
            <Bell class="h-6 w-6 text-green-700 flex-shrink-0" />
            <Transition
              enter-active-class="transition-all duration-300 ease-in-out"
              leave-active-class="transition-all duration-300 ease-in-out"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
        <div class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer hover:opacity-70 transition-opacity" @click="router.visit('/reminders')">
          <Bell class="h-6 w-6 text-green-700 flex-shrink-0" />
          <Transition
            enter-active-class="transition-all duration-300 ease-in-out"
            leave-active-class="transition-all duration-300 ease-in-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <CardTitle class="min-w-0 transition-all duration-200 whitespace-nowrap"
              v-if="headerStage !== 'iconOnly'"
              :class="[headerStage !== 'normal' && 'truncate',
              {
                'max-w-full': headerStage === 'normal',
                'max-w-[220px]': headerStage === 'titleCut',
              },
              ]"
            >
              <CardTitle class="min-w-0 transition-all duration-200 whitespace-nowrap"
                :class="[headerStage !== 'normal' && 'truncate',
                {
                  'max-w-full': headerStage === 'normal',
                  'max-w-[220px]': headerStage === 'titleCut',
                  'max-w-[140px]': headerStage === 'iconOnly',
                },
                ]"
              >
                個人リマインダー
              </CardTitle>
            </Transition>
            
          </div>
          
          <Button
            variant="ghost"
            size="icon"
            class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
            @click="isHelpOpen = true"
            title="個人リマインダーの使い方"
          >
            <HelpCircle class="h-5 w-5" />
          </Button>
        </div>
        
        <div class="flex items-center gap-2 flex-shrink-0 ml-auto">
          <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <button
              @click="showCompleted = false"
              :class="[
                'flex items-center justify-center rounded text-xs transition-all duration-300 ease-in-out',
                headerStage === 'iconOnly' ? 'w-8 h-8 p-0' : 'gap-1 py-1 px-2',
                !showCompleted ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400'
              ]"
              :title="headerStage !== 'normal' ? '未完了' : undefined"
            >
              <Clock :class="['h-3.5 w-3.5 flex-shrink-0', !showCompleted ? 'text-orange-500' : 'text-gray-400']" />
              <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-auto opacity-100"
                leave-from-class="w-auto opacity-100"
                leave-to-class="w-0 opacity-0"
              >
                <span v-if="headerStage === 'normal'" class="whitespace-nowrap">未完了</span>
              </Transition>
              <Badge v-if="headerStage !== 'iconOnly'" variant="secondary" class="text-xs h-4 px-1 ml-1 dark:bg-gray-600 dark:text-gray-100">
                {{ activeCount }}
              </Badge>
            </button>
            <button
              @click="showCompleted = true"
              :class="[
                'flex items-center justify-center rounded text-xs transition-all duration-300 ease-in-out',
                headerStage === 'iconOnly' ? 'w-8 h-8 p-0' : 'gap-1 py-1 px-2',
                showCompleted ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400'
              ]"
              :title="headerStage !== 'normal' ? '完了済' : undefined"
            >
              <CheckCircle :class="['h-3.5 w-3.5 flex-shrink-0', showCompleted ? 'text-green-500' : 'text-gray-400']" />
              <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-auto opacity-100"
                leave-from-class="w-auto opacity-100"
                leave-to-class="w-0 opacity-0"
              >
                <span v-if="headerStage === 'normal'" class="whitespace-nowrap">完了済</span>
              </Transition>
              <Badge v-if="headerStage !== 'iconOnly'" variant="secondary" class="text-xs h-4 px-1 ml-1 dark:bg-gray-600 dark:text-gray-100">
                {{ completedCount }}
              </Badge>
            </button>
          </div>
          <Transition
            enter-active-class="transition-all duration-300 ease-in-out"
            leave-active-class="transition-all duration-300 ease-in-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
          >
            <Button
              size="sm"
              variant="outline"
              class="transition-all duration-300 ease-in-out flex-shrink-0"
              :class="headerStage === 'normal' ? 'gap-1' : ''"
              @click="isCreateDialogOpen = true"
              :title="headerStage !== 'normal' ? '新規作成' : undefined"
            >
              <Plus class="h-3 w-3" />
              <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-auto opacity-100"
                leave-from-class="w-auto opacity-100"
                leave-to-class="w-0 opacity-0"
              >
                <span v-if="headerStage === 'normal'" class="whitespace-nowrap">新規作成</span>
              </Transition>
            </Button>
          </Transition>
        </div>
      </div>
    </CardHeader>
    <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
      <ScrollArea class="h-full">
        <div class="space-y-4">
          <Card
            v-for="reminder in displayedReminders"
            :key="reminder.reminder_id"
            :id="`reminder-${reminder.reminder_id}`"
            :class="[
              'transition-all cursor-pointer border',
              reminder.completed ? 'opacity-60' : 'hover:shadow-md',
              reminder.deadline_date && isOverdue(reminder.deadline_date, reminder.deadline_time) ? 'border-red-500 border-2' :
              reminder.deadline_date && isUpcoming(reminder.deadline_date, reminder.deadline_time) ? 'border-yellow-400 border-2' :
              'border-gray-300'
            ]"
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
                    </div>
                    <p v-if="reminder.description" :class="['text-sm text-gray-600 mb-3', reminder.completed ? 'text-gray-500 line-through' : '']">
                      {{ reminder.description.length > 20 ? reminder.description.substring(0, 20) + '...' : reminder.description }}
                    </p>

                  </div>
                </div>
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                  <Button
                    variant="outline"
                    size="sm"
                    class="gap-2 bg-red-50 text-red-700 border-red-200 hover:bg-red-600 hover:text-white hover:border-red-600"
                    @click.stop="handlePermanentDelete(reminder)"
                  >
                    <Trash2 class="h-4 w-4" />
                    完全に削除
                  </Button>
                  <div class="flex items-center gap-1 text-xs">
                    <CheckCircle v-if="reminder.completed" class="h-3 w-3 text-green-600" />
                    <Clock v-else class="h-3 w-3 text-orange-600" />
                    <span v-if="reminder.deadline_date" class="text-gray-500">
                      {{ formatDate(reminder.deadline_date) }}{{ reminder.deadline_time ? ` ${reminder.deadline_time.substring(0, 5)}` : '' }}
                    </span>
                  </div>
                  <Badge v-if="reminder.deadline_date && isOverdue(reminder.deadline_date, reminder.deadline_time)" variant="outline" class="text-xs bg-red-100 text-red-700 border-red-400">
                    期限切れ
                  </Badge>
                  <Badge v-else-if="reminder.deadline_date && isUpcoming(reminder.deadline_date, reminder.deadline_time)" variant="outline" class="text-xs bg-yellow-100 text-yellow-700 border-yellow-400">
                    期限間近
                  </Badge>
                </div>
              </div>
            </CardHeader>

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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[9999] p-3 text-white rounded-lg shadow-lg',
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
    
    <!-- ヘルプダイアログ -->
    <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
      <DialogContent class="max-w-2xl">
        <DialogHeader>
          <DialogTitle>個人リマインダーの使い方</DialogTitle>
          <DialogDescription>
            個人リマインダーの基本的な使い方をご説明します。
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <div>
            <h3 class="font-semibold mb-2">基本操作</h3>
            <ul class="space-y-1 text-sm text-gray-600">
              <li>• 新規作成ボタンでリマインダーを作成できます</li>
              <li>• チェックボックスをクリックして完了/未完了を切り替えられます</li>
              <li>• リマインダーをクリックして詳細を表示・編集できます</li>
              <li>• 完全に削除ボタンでリマインダーを削除できます</li>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold mb-2">表示切り替え</h3>
            <ul class="space-y-1 text-sm text-gray-600">
              <li>• 未完了タブで進行中のリマインダーを表示</li>
              <li>• 完了済タブで完了したリマインダーを表示</li>
              <li>• 各タブの数字で件数を確認できます</li>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold mb-2">期限管理</h3>
            <ul class="space-y-1 text-sm text-gray-600">
              <li>• 期限切れのリマインダーは赤い枠で表示されます</li>
              <li>• 期限間近（3日以内）のリマインダーは黄色い枠で表示されます</li>
              <li>• 完了したリマインダーは「元に戻す」で復元できます</li>
            </ul>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </Card>
</template>
