<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Bell, Plus, Clock, ArrowLeft, Trash2, CheckCircle, Undo2, Search, Tag } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/utils'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
const selectedActiveItems = ref<Set<number>>(new Set())
const selectedCompletedItems = ref<Set<number>>(new Set())
const searchQuery = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const filterTag = ref('_all_')

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



const allTags = computed(() => {
  const tags = new Set<string>()
  props.reminders.forEach(reminder => {
    reminder.tags?.forEach((tag: any) => tags.add(tag.tag_name))
  })
  return Array.from(tags).sort()
})

const filteredReminders = computed(() => {
  return props.reminders.filter(reminder => {
    const matchesSearch = !searchQuery.value.trim() || (() => {
      const query = searchQuery.value.toLowerCase()
      const title = reminder.title?.toLowerCase() || ''
      const description = reminder.description?.toLowerCase() || ''
      const tags = reminder.tags?.map((t: any) => t.tag_name.toLowerCase()).join(' ') || ''
      const deadline = reminder.deadline_date ? formatDate(reminder.deadline_date) : ''
      
      return title.includes(query) || 
             description.includes(query) || 
             tags.includes(query) || 
             deadline.includes(query)
    })()
    
    const matchesTag = filterTag.value === '_all_' || reminder.tags?.some((tag: any) => tag.tag_name === filterTag.value)
    
    return matchesSearch && matchesTag
  })
})

const activeReminders = computed(() => {
  return filteredReminders.value
    .filter((r) => !r.completed)
    .sort((a, b) => {
      // 期限なしは後に表示
      if (!a.deadline_date && b.deadline_date) return 1
      if (a.deadline_date && !b.deadline_date) return -1
      // 両方期限ありの場合は期限順
      if (a.deadline_date && b.deadline_date) {
        return a.deadline_date.localeCompare(b.deadline_date)
      }
      return 0
    })
})

const completedReminders = computed(() => {
  return filteredReminders.value
    .filter((r) => r.completed)
    .sort((a, b) => {
      // 期限なしは後に表示
      if (!a.deadline_date && b.deadline_date) return 1
      if (a.deadline_date && !b.deadline_date) return -1
      // 両方期限ありの場合は期限順
      if (a.deadline_date && b.deadline_date) {
        return a.deadline_date.localeCompare(b.deadline_date)
      }
      return 0
    })
})

const isAllActiveSelected = computed(() => {
  return activeReminders.value.length > 0 && activeReminders.value.every(r => selectedActiveItems.value.has(r.reminder_id))
})

const isAllCompletedSelected = computed(() => {
  return completedReminders.value.length > 0 && completedReminders.value.every(r => selectedCompletedItems.value.has(r.reminder_id))
})

const toggleAllActive = (checked: boolean) => {
  if (checked) {
    activeReminders.value.forEach(r => selectedActiveItems.value.add(r.reminder_id))
  } else {
    selectedActiveItems.value.clear()
  }
  selectedActiveItems.value = new Set(selectedActiveItems.value)
}

const toggleAllCompleted = (checked: boolean) => {
  if (checked) {
    completedReminders.value.forEach(r => selectedCompletedItems.value.add(r.reminder_id))
  } else {
    selectedCompletedItems.value.clear()
  }
  selectedCompletedItems.value = new Set(selectedCompletedItems.value)
}

const handleBulkComplete = () => {
  const ids = Array.from(selectedActiveItems.value)
  if (ids.length === 0) return
  
  router.post(route('reminders.bulkComplete'), { ids }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedActiveItems.value.clear()
      showMessage(`${ids.length}件のリマインダーを完了しました。`, 'success')
    }
  })
}

const handleBulkRestore = () => {
  const ids = Array.from(selectedCompletedItems.value)
  if (ids.length === 0) return
  
  router.post(route('reminders.bulkRestore'), { ids }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedCompletedItems.value.clear()
      showMessage(`${ids.length}件のリマインダーを未完了に戻しました。`, 'success')
    }
  })
}

const showBulkDeleteDialog = ref(false)

const handleBulkDelete = () => {
  showBulkDeleteDialog.value = true
}

const confirmBulkDelete = () => {
  const ids = Array.from(selectedCompletedItems.value)
  if (ids.length === 0) return
  
  router.post(route('reminders.bulkDelete'), { ids }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedCompletedItems.value.clear()
      showMessage(`${ids.length}件のリマインダーを完全に削除しました。`, 'success')
    }
  })
  showBulkDeleteDialog.value = false
}

</script>

<template>
  <Head title="個人リマインダー" />
  <div class="max-w-[1800px] mx-auto h-full p-6">
    <Card class="h-full overflow-hidden flex flex-col">
      <div class="p-4 border-b border-gray-300">
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon" @click="router.get(route('dashboard'))" class="mr-1">
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <Bell class="h-6 w-6 text-green-700" />
            <CardTitle>個人リマインダー</CardTitle>
          </div>
          <div class="flex items-center gap-2">
            <Select v-model="filterTag">
              <SelectTrigger class="w-[180px]">
                <SelectValue>
                  <div class="flex items-center gap-2">
                    <Tag class="h-4 w-4" />
                    <span>{{ filterTag === '_all_' ? 'タグで絞り込み' : filterTag }}</span>
                  </div>
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="_all_">すべてのタグ</SelectItem>
                <SelectItem v-for="tag in allTags" :key="tag" :value="tag">
                  {{ tag }}
                </SelectItem>
              </SelectContent>
            </Select>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                placeholder="タイトル、タグ、期限、詳細で検索..."
                class="pl-9 pr-4 w-[280px] flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              />
            </div>
            <Button variant="outline" @click="() => { isCreateDialogOpen = true; isCreatingNew = true }" class="gap-2">
              <Plus class="h-4 w-4" />
              新規作成
            </Button>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-hidden p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 h-full">
          <Card class="flex flex-col overflow-hidden">
            <CardHeader>
              <CardTitle class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <input 
                    type="checkbox" 
                    :checked="isAllActiveSelected" 
                    @change="(e) => toggleAllActive((e.target as HTMLInputElement).checked)"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>未完了</span>
                  <Badge>{{ activeReminders.length }}件</Badge>
                </div>
                <Button 
                  v-if="selectedActiveItems.size > 0" 
                  variant="outline" 
                  size="sm" 
                  @click="handleBulkComplete"
                  class="gap-2 bg-blue-600 text-white border-blue-600 hover:bg-blue-700"
                >
                  <CheckCircle class="h-4 w-4" />
                  {{ selectedActiveItems.size }}件を完了
                </Button>
              </CardTitle>
            </CardHeader>
            <CardContent class="flex-1 overflow-hidden p-6">
              <div class="h-full overflow-auto">
                <div class="space-y-3">
                  <div v-for="reminder in activeReminders" :key="reminder.reminder_id" :class="['border-2 bg-white rounded-lg p-4 hover:shadow-md transition-all cursor-pointer', selectedActiveItems.has(reminder.reminder_id) ? 'border-blue-500 bg-blue-50' : 'border-gray-300']" @click="(e) => { if (!(e.target as HTMLElement).closest('input[type=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { if (selectedActiveItems.size > 0) { const checked = selectedActiveItems.has(reminder.reminder_id); if (checked) { selectedActiveItems.delete(reminder.reminder_id) } else { selectedActiveItems.add(reminder.reminder_id) }; selectedActiveItems = new Set(selectedActiveItems) } else { selectedReminder = reminder } } }">
                    <div class="flex items-start gap-3">
                      <input
                        type="checkbox"
                        :checked="selectedActiveItems.has(reminder.reminder_id)"
                        @change="(e) => { const checked = (e.target as HTMLInputElement).checked; if (checked) { selectedActiveItems.add(reminder.reminder_id) } else { selectedActiveItems.delete(reminder.reminder_id) }; selectedActiveItems = new Set(selectedActiveItems) }"
                        class="mt-1 h-4 w-4 text-blue-600 rounded"
                      />
                      <div class="flex-1">
                        <h3 class="mb-2">{{ reminder.title }}</h3>
                        <p v-if="reminder.description" class="text-sm text-gray-600 mb-2">{{ reminder.description }}</p>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                          <div v-if="reminder.deadline_date" class="flex items-center gap-1">
                            <Clock class="h-3 w-3" />
                            期限: {{ formatDate(reminder.deadline_date) }} {{ reminder.deadline_time ? reminder.deadline_time.substring(0, 5) : '' }}
                          </div>
                          <div v-else class="flex items-center gap-1 text-gray-400">
                            <Clock class="h-3 w-3" />
                            期限なし
                          </div>
                          <Badge
                            v-for="tag in reminder.tags"
                            :key="tag.tag_id"
                            variant="secondary"
                            class="text-xs"
                          >
                            {{ tag.tag_name }}
                          </Badge>
                        </div>
                      </div>
                      <Button variant="outline" size="sm" class="gap-1 sm:gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 whitespace-nowrap" @click.stop="handleDeletePermanently(reminder)">
                        <Trash2 class="h-4 w-4" />
                        <span class="hidden sm:inline">完全に削除</span>
                        <span class="sm:hidden">削除</span>
                      </Button>
                    </div>
                  </div>
                  <div v-if="activeReminders.length === 0" class="text-center py-12 text-gray-500">
                    <Bell class="h-12 w-12 mx-auto mb-4 opacity-30" />
                    <p>未完了のリマインダーはありません</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="flex flex-col overflow-hidden">
            <CardHeader>
              <CardTitle class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <input 
                    type="checkbox" 
                    :checked="isAllCompletedSelected" 
                    @change="(e) => toggleAllCompleted((e.target as HTMLInputElement).checked)"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span>完了済</span>
                  <Badge variant="secondary">{{ completedReminders.length }}件</Badge>
                </div>
                <div v-if="selectedCompletedItems.size > 0" class="flex items-center gap-2">
                  <Button 
                    variant="outline" 
                    size="sm" 
                    @click="handleBulkRestore"
                    class="gap-2 bg-green-600 text-white border-green-600 hover:bg-green-700"
                  >
                    <Undo2 class="h-4 w-4" />
                    {{ selectedCompletedItems.size }}件を未完了に
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    @click="handleBulkDelete"
                    class="gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700"
                  >
                    <Trash2 class="h-4 w-4" />
                    {{ selectedCompletedItems.size }}件を完全削除
                  </Button>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent class="flex-1 overflow-hidden p-6">
              <div class="h-full overflow-auto">
                <div class="space-y-3">
                  <div v-for="reminder in completedReminders" :key="reminder.reminder_id" :class="['border-2 bg-gray-100 rounded-lg p-4 opacity-60 cursor-pointer', selectedCompletedItems.has(reminder.reminder_id) ? 'border-green-500 bg-green-50 opacity-100' : 'border-gray-300']" @click="(e) => { if (!(e.target as HTMLElement).closest('input[type=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { if (selectedCompletedItems.size > 0) { const checked = selectedCompletedItems.has(reminder.reminder_id); if (checked) { selectedCompletedItems.delete(reminder.reminder_id) } else { selectedCompletedItems.add(reminder.reminder_id) }; selectedCompletedItems = new Set(selectedCompletedItems) } } }">
                    <div class="flex items-start gap-3">
                      <input
                        type="checkbox"
                        :checked="selectedCompletedItems.has(reminder.reminder_id)"
                        @change="(e) => { const checked = (e.target as HTMLInputElement).checked; if (checked) { selectedCompletedItems.add(reminder.reminder_id) } else { selectedCompletedItems.delete(reminder.reminder_id) }; selectedCompletedItems = new Set(selectedCompletedItems) }"
                        class="mt-1 h-4 w-4 text-blue-600 rounded"
                      />
                      <div class="flex-1">
                        <h3 class="mb-2 line-through text-gray-500">{{ reminder.title }}</h3>
                        <p v-if="reminder.description" class="text-sm text-gray-500 mb-2 line-through">{{ reminder.description }}</p>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                          <div v-if="reminder.deadline_date" class="flex items-center gap-1">
                            <Clock class="h-3 w-3" />
                            期限: {{ formatDate(reminder.deadline_date) }} {{ reminder.deadline_time ? reminder.deadline_time.substring(0, 5) : '' }}
                          </div>
                          <div v-else class="flex items-center gap-1 text-gray-400">
                            <Clock class="h-3 w-3" />
                            期限なし
                          </div>
                          <Badge
                            v-for="tag in reminder.tags"
                            :key="tag.tag_id"
                            variant="secondary"
                            class="text-xs opacity-60"
                          >
                            {{ tag.tag_name }}
                          </Badge>
                        </div>
                      </div>
                      <Button variant="outline" size="sm" class="gap-1 sm:gap-2 bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700 whitespace-nowrap" @click="handleDeletePermanently(reminder)">
                        <Trash2 class="h-4 w-4" />
                        <span class="hidden sm:inline">完全に削除</span>
                        <span class="sm:hidden">削除</span>
                      </Button>
                    </div>
                  </div>
                  <div v-if="completedReminders.length === 0" class="text-center py-12 text-gray-500">
                    <CheckCircle class="h-12 w-12 mx-auto mb-4 opacity-30" />
                    <p>完了済のリマインダーはありません</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </Card>

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

    <AlertDialog :open="showBulkDeleteDialog" @update:open="(open) => showBulkDeleteDialog = open">
      <AlertDialogContent class="bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle>選択した{{ selectedCompletedItems.size }}件を完全に削除しますか？</AlertDialogTitle>
          <AlertDialogDescription>この操作は取り消せません。</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>キャンセル</AlertDialogCancel>
          <AlertDialogAction @click="confirmBulkDelete" class="bg-red-600 text-white border-red-600 hover:bg-red-700 hover:border-red-700">
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
