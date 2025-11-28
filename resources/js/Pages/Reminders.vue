<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Bell, Plus, Clock, ArrowLeft, Trash2, CheckCircle, Undo2 } from 'lucide-vue-next'
import { formatDate } from '@/lib/utils'
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

import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  reminders: App.Models.Reminder[]
}>()

const page = usePage()
const isCreateDialogOpen = ref(false)
const isCreatingNew = ref(false)
const selectedReminder = ref<App.Models.Reminder | null>(null)
const showCompleted = ref(false)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedReminder = ref<App.Models.Reminder | null>(null)
const reminderToDelete = ref<App.Models.Reminder | null>(null)

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

const handleDeletePermanently = (reminder: App.Models.Reminder) => {
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
const handleUpdateReminder = (updatedReminder: App.Models.Reminder) => {
  // メッセージ表示はReminderDetailDialog内で処理される
  if (isCreatingNew.value) {
    isCreatingNew.value = false
  }
}



const activeReminders = computed(() => props.reminders.filter((r) => !r.completed))
const completedReminders = computed(() => props.reminders.filter((r) => r.completed))

</script>

<template>
  <Head title="個人リマインダー" />
  <div class="h-full bg-gray-50 flex flex-col ">
    <header class="bg-white border-b border-gray-300 px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Button variant="ghost" size="icon" @click="router.get('/')">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <Bell class="h-6 w-6 text-green-700" />
          <div>
            <CardTitle>個人リマインダー</CardTitle>
            <p class="text-xs text-gray-500">自分専用のタスク管理</p>
          </div>
        </div>
          <Button variant="outline" @click="() => { isCreateDialogOpen = true; isCreatingNew = true }" class="gap-2">
            <Plus class="h-4 w-4" />
            新規作成
          </Button>
      </div>
    </header>

    <main class="flex-1 overflow-auto p-6">
      <div class="max-w-4xl mx-auto space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <span>未完了</span>
              <Badge>{{ activeReminders.length }}件</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ScrollArea class="max-h-[400px]">
              <div class="space-y-3">
                <div v-for="reminder in activeReminders" :key="reminder.reminder_id" class="border-2 border-gray-300 bg-white rounded-lg p-4 hover:shadow-md transition-all cursor-pointer" @click="(e) => { if (!(e.target as HTMLElement).closest('input[type=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { selectedReminder = reminder } }">
                  <div class="flex items-start gap-3">
                    <input
                      type="checkbox"
                      :checked="false"
                      @change="handleToggleComplete(reminder.reminder_id, ($event.target as HTMLInputElement).checked)"
                      class="mt-1 h-4 w-4 text-blue-600 rounded"
                    />
                    <div class="flex-1">
                      <h3 class="mb-2">{{ reminder.title }}</h3>
                      <p v-if="reminder.description" class="text-sm text-gray-600 mb-2">{{ reminder.description }}</p>
                      <div class="flex items-center gap-4 text-xs text-gray-600">
                        <div class="flex items-center gap-1">
                          <Clock class="h-3 w-3" />
                          期限: {{ formatDate(reminder.deadline) }}
                        </div>
                        <Badge variant="outline" class="text-xs">{{ reminder.category }}</Badge>
                      </div>
                    </div>
                    <Button variant="outline" size="sm" class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700" @click.stop="handleDeletePermanently(reminder)">
                      <Trash2 class="h-4 w-4" />
                      完全に削除
                    </Button>
                  </div>
                </div>
                <div v-if="activeReminders.length === 0" class="text-center py-12 text-gray-500">
                  <Bell class="h-12 w-12 mx-auto mb-4 opacity-30" />
                  <p>未完了のリマインダーはありません</p>
                </div>
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <Card v-if="completedReminders.length > 0">
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <Button 
                variant="ghost" 
                @click="showCompleted = !showCompleted"
                class="flex items-center gap-2 p-0 h-auto font-semibold text-lg"
              >
                <span>完了済み</span>
                <Badge variant="secondary">{{ completedReminders.length }}件</Badge>
              </Button>
            </CardTitle>
          </CardHeader>
          <CardContent v-if="showCompleted">
            <ScrollArea class="max-h-[300px]">
              <div class="space-y-3">
                <div v-for="reminder in completedReminders" :key="reminder.reminder_id" class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 opacity-60">
                  <div class="flex items-start gap-3">
                    <input
                      type="checkbox"
                      :checked="reminder.completed"
                      @change="handleToggleComplete(reminder.reminder_id, ($event.target as HTMLInputElement).checked)"
                      class="mt-1 h-4 w-4 text-blue-600 rounded"
                    />
                    <div class="flex-1">
                      <h3 class="mb-2 line-through text-gray-500">{{ reminder.title }}</h3>
                      <p v-if="reminder.description" class="text-sm text-gray-500 mb-2 line-through">{{ reminder.description }}</p>
                      <div class="flex items-center gap-4 text-xs text-gray-500">
                        <div class="flex items-center gap-1">
                          <Clock class="h-3 w-3" />
                          期限: {{ formatDate(reminder.deadline) }}
                        </div>
                        <Badge variant="outline" class="text-xs opacity-60">{{ reminder.category }}</Badge>
                      </div>
                    </div>
                    <Button variant="outline" size="sm" class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700" @click="handleDeletePermanently(reminder)">
                      <Trash2 class="h-4 w-4" />
                      完全に削除
                    </Button>
                  </div>
                </div>
              </div>
            </ScrollArea>
          </CardContent>
        </Card>
      </div>
    </main>

    <ReminderDetailDialog 
      :reminder="selectedReminder" 
      :open="selectedReminder !== null" 
      @update:open="(isOpen, completed) => { if (!isOpen) { if (completed && selectedReminder) { lastDeletedReminder = selectedReminder; showMessage('リマインダーを完了しました。', 'delete'); } selectedReminder = null; } }" 
      @update:reminder="handleUpdateReminder" 
    />

    <ReminderDetailDialog
      :reminder="null"
      :open="isCreateDialogOpen"
      @update:open="(open) => { isCreateDialogOpen = open; if (!open) isCreatingNew = false }"
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg',
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
  </div>
</template>
