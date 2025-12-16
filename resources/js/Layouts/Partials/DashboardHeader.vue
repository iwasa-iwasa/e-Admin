<script setup lang="ts">
import { Link, useForm, router, usePage } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import { ref, onMounted, computed } from 'vue'
import { Search, Bell, User, Calendar, StickyNote, BarChart3, Settings, Clock, Undo2, Menu } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { ScrollArea } from '@/components/ui/scroll-area'
import NoteDetailDialog from '@/components/NoteDetailDialog.vue'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'
import GlobalSearch from '@/components/GlobalSearch.vue'

const props = defineProps<{
  isSidebarOpen?: boolean
  isTablet?: boolean
}>()

const emit = defineEmits(['toggle-sidebar'])

const showConfirmLogoutModal = ref(false);
const form = useForm({});

const page = usePage()
const teamMembers = computed(() => (page.props as any).teamMembers || [])
const totalUsers = computed(() => (page.props as any).totalUsers || 0)

const logout = () => {
    form.post(route('logout'));
};

interface Event {
  event_id: number
  title: string
  start_date: string
  start_time?: string
  end_date?: string
  end_time?: string
  creator: { name: string }
  location?: string
  description?: string
  importance?: string
}

interface Note {
  note_id: number
  title: string
  content: string
  author: { name: string }
  deadline_date?: string
  deadline_time?: string
  color: string
  priority: 'high' | 'medium' | 'low'
}

interface Survey {
  survey_id: number
  title: string
  deadline_date?: string
  deadline_time?: string
  creator: { name: string }
  description?: string
}

interface Reminder {
  reminder_id: number
  title: string
  description?: string
  deadline_date: string
  deadline_time?: string
  category: string
  completed: boolean
}

const searchQuery = ref('')
const isSearchFocused = ref(false)
const isNotificationOpen = ref(false)

const selectedEvent = ref<Event | null>(null)
const selectedNote = ref<Note | null>(null)
const selectedReminder = ref<Reminder | null>(null)
const isEventDetailOpen = ref(false)
const isEventEditOpen = ref(false)

const isProfileSettingsOpen = ref(false)
const showEventsFilter = ref<'mine' | 'all'>(
  (localStorage.getItem('notif_events_filter') as 'mine' | 'all') || 'mine'
)
const showNotesFilter = ref<'mine' | 'all'>(
  (localStorage.getItem('notif_notes_filter') as 'mine' | 'all') || 'mine'
)

const notifications = ref({ events: [], notes: [], surveys: [], reminders: [] })
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedReminder = ref<Reminder | null>(null)
const scrollPosition = ref(0)
const notificationScrollArea = ref<HTMLElement | null>(null)

const insertSearchOption = (option: string) => {
  searchQuery.value += option
}

const isLoadingNotifications = ref(false)

const fetchNotifications = async () => {
  if (isLoadingNotifications.value || !page.props.auth?.user) return
  
  isLoadingNotifications.value = true
  try {
    const params = new URLSearchParams({
      events_filter: showEventsFilter.value,
      notes_filter: showNotesFilter.value,
      _t: Date.now().toString()
    })
    const response = await fetch(`/api/notifications?${params}`, {
      cache: 'no-store'
    })
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    const data = await response.json()

    notifications.value = data
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
  } finally {
    isLoadingNotifications.value = false
  }
}

const toggleEventsFilter = async () => {
  showEventsFilter.value = showEventsFilter.value === 'mine' ? 'all' : 'mine'
  localStorage.setItem('notif_events_filter', showEventsFilter.value)
  await fetchNotifications()
}

const toggleNotesFilter = async () => {
  showNotesFilter.value = showNotesFilter.value === 'mine' ? 'all' : 'mine'
  localStorage.setItem('notif_notes_filter', showNotesFilter.value)
  await fetchNotifications()
}

const totalNotifications = computed(() => 
  notifications.value.events.length + notifications.value.notes.length + notifications.value.surveys.length + notifications.value.reminders.length
)

const isOverdue = (deadlineDate: string, deadlineTime?: string) => {
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

const isUpcoming = (deadlineDate: string, deadlineTime?: string) => {
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

const getItemColor = (type: string, priority?: string, deadlineDate?: string, deadlineTime?: string) => {
  if (type === 'event') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-blue-50 border-red-500 border-2'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-blue-50 border-yellow-400 border-2'
    }
    return 'bg-blue-50 border-blue-200'
  }
  if (type === 'note') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-orange-50 border-red-500 border-2'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-orange-50 border-yellow-400 border-2'
    }
    return 'bg-orange-50 border-orange-200'
  }
  if (type === 'reminder') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-green-50 border-red-500 border-2'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-green-50 border-yellow-400 border-2'
    }
    return 'bg-green-50 border-green-200'
  }
  if (type === 'survey') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-purple-50 border-red-500 border-2'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-purple-50 border-yellow-400 border-2'
    }
  }
  return 'bg-purple-50 border-purple-200'
}

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('ja-JP')
}

const formatDateTime = (date?: string, time?: string) => {
  if (!date) return ''
  const dateStr = new Date(date).toLocaleDateString('ja-JP')
  if (time) {
    return `${dateStr} ${time.substring(0, 5)}`
  }
  return dateStr
}

const getInitial = (name: string) => {
  if (!name || name.length === 0) return '?'
  // 姓のみを返す（スペースで分割して最初の部分）
  const parts = name.split(' ')
  return parts[0] || name.charAt(0)
}

const handleClick = (type: string, item: any) => {
  if (type === 'event') {
    selectedEvent.value = item
    isEventDetailOpen.value = true
  } else if (type === 'note') {
    selectedNote.value = item
  } else if (type === 'survey') {
    isNotificationOpen.value = false
    router.visit(`/surveys/${item.survey_id}/answer`)
  } else if (type === 'reminder') {
    const scrollArea = document.querySelector('.notification-scroll-area')
    if (scrollArea) {
      scrollPosition.value = scrollArea.scrollTop
    }
    selectedReminder.value = item
  }
}

const handleEventEdit = () => {
  isEventDetailOpen.value = false
  isEventEditOpen.value = true
}

const handleNoteSave = (note: any) => {
  router.put(`/shared-notes/${note.note_id}`, {
    title: note.title,
    content: note.content,
    color: note.color,
    priority: note.priority,
    deadline: note.deadline,
    progress: note.progress,
    participants: note.participants?.map((p: any) => p.id) || []
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedNote.value = null
      fetchNotifications()
    }
  })
}

const handleNoteDelete = (note: any) => {
  router.delete(`/notes/${note.note_id}`, {
    onSuccess: () => {
      fetchNotifications()
      selectedNote.value = null
    }
  })
}

const handleNoteTogglePin = (note: any) => {
  if (note.is_pinned) {
    router.delete(`/notes/${note.note_id}/unpin`, {
      onSuccess: () => fetchNotifications()
    })
  } else {
    router.post(`/notes/${note.note_id}/pin`, {}, {
      onSuccess: () => fetchNotifications()
    })
  }
}

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

const handleUndoDelete = async () => {
  if (!lastDeletedReminder.value) return

  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  saveMessage.value = '元に戻しています...'
  
  const reminderToRestore = lastDeletedReminder.value
  const wasOpen = isNotificationOpen.value
  const savedScrollPosition = scrollPosition.value
  lastDeletedReminder.value = null

  try {
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const url = route('reminders.restore')
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        reminder_id: reminderToRestore.reminder_id
      }),
      credentials: 'same-origin'
    })
    
    if (response.ok) {
      showMessage('リマインダーが元に戻されました。', 'success')
      // 通知センターを開いたままにする
      if (wasOpen) {
        isNotificationOpen.value = false
        await fetchNotifications()
        setTimeout(() => {
          isNotificationOpen.value = true
          setTimeout(() => {
            const scrollArea = document.querySelector('.notification-scroll-area')
            if (scrollArea) {
              scrollArea.scrollTop = savedScrollPosition
            }
          }, 50)
        }, 10)
      } else {
        await fetchNotifications()
      }
      // ページ全体を更新
      router.reload({ only: ['personalReminders'], preserveScroll: true, preserveState: true })
    } else {
      const errorData = await response.json().catch(() => ({}))
      console.error('Restore error:', response.status, errorData)
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  } catch (error) {
    console.error('Restore error:', error)
    showMessage('元に戻す処理に失敗しました。', 'success')
  }
}

onMounted(() => {
  fetchNotifications()
  
  // Inertiaのページ更新イベントをリッスン
  router.on('success', () => {
    fetchNotifications()
  })
})
</script>

<template>
  <header class="bg-white border-b border-gray-300 px-6 py-4">
    <div class="flex items-center justify-between gap-4">
      <!-- ハンバーガーメニュー (iPad Air/Proのみ) -->
      <Button 
        v-if="props.isTablet"
        variant="ghost" 
        size="icon" 
        @click="emit('toggle-sidebar')"
      >
        <Menu class="h-6 w-6" />
      </Button>
      
      <!-- グローバル検索 -->
      <GlobalSearch />

      <!-- 右側のアクション -->
      <div class="flex items-center gap-3">
        <!-- 通知 -->
        <Popover v-model:open="isNotificationOpen">
          <PopoverTrigger as-child>
            <Button variant="outline" size="icon" class="relative">
              <Bell class="h-5 w-5" />
              <Badge class="absolute -top-1 -right-1 h-5 w-5 flex items-center text-white justify-center p-0 bg-red-500">
                {{ totalNotifications }}
              </Badge>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-[420px] p-0 max-h-[80vh] flex flex-col" align="end">
            <div class="p-4 border-b border-gray-300">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="flex items-center gap-2">
                    <Bell class="h-5 w-5 text-blue-600" />
                    通知センター
                  </h3>
                  <p class="text-xs text-gray-500 mt-1">
                    重要な予定、メモ、アンケートをまとめて確認
                  </p>
                </div>
                <Popover>
                  <PopoverTrigger as-child>
                    <Button variant="ghost" size="icon" class="h-8 w-8">
                      <Settings class="h-4 w-4" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent class="w-64" align="end">
                    <div class="space-y-4">
                      <div>
                        <h4 class="text-sm font-medium mb-2">表示設定</h4>
                      </div>
                      <div class="space-y-4">
                        <div>
                          <label class="text-xs font-medium text-gray-700 block mb-2">共有カレンダー</label>
                          <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showEventsFilter === 'mine' ? 'bg-white shadow-sm' : 'hover:bg-gray-50'"
                              :disabled="isLoadingNotifications"
                              @click="toggleEventsFilter"
                            >
                              自分のみ
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showEventsFilter === 'all' ? 'bg-white shadow-sm' : 'hover:bg-gray-50'"
                              :disabled="isLoadingNotifications"
                              @click="toggleEventsFilter"
                            >
                              全員表示
                            </Button>
                          </div>
                          <p class="text-xs text-gray-500 mt-1">
                            {{ showEventsFilter === 'mine' ? '作成者または参加者として関わる予定のみ' : '全員の重要な予定を表示' }}
                          </p>
                        </div>
                        <div>
                          <label class="text-xs font-medium text-gray-700 block mb-2">共有メモ</label>
                          <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showNotesFilter === 'mine' ? 'bg-white shadow-sm' : 'hover:bg-gray-50'"
                              :disabled="isLoadingNotifications"
                              @click="toggleNotesFilter"
                            >
                              自分のみ
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showNotesFilter === 'all' ? 'bg-white shadow-sm' : 'hover:bg-gray-50'"
                              :disabled="isLoadingNotifications"
                              @click="toggleNotesFilter"
                            >
                              全員表示
                            </Button>
                          </div>
                          <p class="text-xs text-gray-500 mt-1">
                            {{ showNotesFilter === 'mine' ? '作成者または参加者として関わるメモのみ' : '全員の重要なメモを表示' }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </PopoverContent>
                </Popover>
              </div>
            </div>
            
            <div class="flex-1 overflow-y-auto scrollbar-hide notification-scroll-area">
              <div v-if="notifications.events.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <Calendar class="h-4 w-4 text-blue-600" />
                  <h4 class="text-sm">共有カレンダー</h4>
                  <Badge class="ml-auto text-xs bg-blue-500">{{ notifications.events.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="event in notifications.events" :key="event.event_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('event', undefined, event.end_date || event.start_date, event.end_time || event.start_time)}`"
                    @click="handleClick('event', event)">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="text-sm flex-1">{{ event.title }}</div>
                      <Badge v-if="isOverdue(event.end_date || event.start_date, event.end_time || event.start_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                      <Badge v-else-if="isUpcoming(event.end_date || event.start_date, event.end_time || event.start_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                    </div>
                    <div class="text-xs text-gray-600 flex items-center justify-between gap-1">
                      <div class="flex items-center gap-1 flex-wrap">
                        <span>{{ formatDateTime(event.end_date || event.start_date, event.end_time || event.start_time) }}</span>
                        <Badge v-for="participant in event.participants" :key="participant.id" variant="outline" class="text-xs cursor-help" :title="participant.name" style="font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;">{{ getInitial(participant.name) }}</Badge>
                      </div>
                      <Badge variant="outline" class="text-xs">{{ event.creator.name }}</Badge>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.notes.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <StickyNote class="h-4 w-4 text-orange-600" />
                  <h4 class="text-sm">共有メモ</h4>
                  <Badge class="ml-auto text-xs bg-orange-500">{{ notifications.notes.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="note in notifications.notes" :key="note.note_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('note', note.priority, note.deadline_date, note.deadline_time)}`"
                    @click="handleClick('note', note)">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="text-sm flex-1">{{ note.title }}</div>
                      <Badge v-if="note.deadline_date && isOverdue(note.deadline_date, note.deadline_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                      <Badge v-else-if="note.deadline_date && isUpcoming(note.deadline_date, note.deadline_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                    </div>
                    <div class="text-xs text-gray-600 flex items-center justify-between gap-1">
                      <div class="flex items-center gap-1 flex-wrap">
                        <span>期限: {{ formatDateTime(note.deadline_date, note.deadline_time) }}</span>
                        <Badge v-for="participant in note.participants" :key="participant.id" variant="outline" class="text-xs cursor-help" :title="participant.name" style="font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;">{{ getInitial(participant.name) }}</Badge>
                      </div>
                      <Badge variant="outline" class="text-xs">{{ note.author.name }}</Badge>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.reminders.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <Clock class="h-4 w-4 text-green-600" />
                  <h4 class="text-sm">個人リマインダー</h4>
                  <Badge class="ml-auto text-xs bg-green-500">{{ notifications.reminders.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="reminder in notifications.reminders" :key="reminder.reminder_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('reminder', undefined, reminder.deadline_date, reminder.deadline_time)}`"
                    @click="handleClick('reminder', reminder)">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="text-sm flex-1">{{ reminder.title }}</div>
                      <Badge v-if="isOverdue(reminder.deadline_date, reminder.deadline_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                      <Badge v-else-if="isUpcoming(reminder.deadline_date, reminder.deadline_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                    </div>
                    <div class="text-xs text-gray-600">
                      <span v-if="reminder.deadline_date">期限: {{ formatDateTime(reminder.deadline_date, reminder.deadline_time) }}</span>
                      <span v-else class="text-gray-400">期限なし</span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.surveys.length > 0" class="p-3">
                <div class="flex items-center gap-2 mb-2">
                  <BarChart3 class="h-4 w-4 text-purple-600" />
                  <h4 class="text-sm">未回答アンケート</h4>
                  <Badge class="ml-auto text-xs bg-purple-500">{{ notifications.surveys.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="survey in notifications.surveys" :key="survey.survey_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('survey', undefined, survey.deadline_date, survey.deadline_time)}`"
                    @click="handleClick('survey', survey)">
                    <div class="flex items-center gap-2 mb-1">
                      <div class="text-sm flex-1">{{ survey.title }}</div>
                      <Badge v-if="survey.deadline_date && isOverdue(survey.deadline_date, survey.deadline_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                      <Badge v-else-if="survey.deadline_date && isUpcoming(survey.deadline_date, survey.deadline_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                    </div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>回答期限: {{ formatDateTime(survey.deadline_date, survey.deadline_time) }}</span>
                      <Badge variant="outline" class="text-xs">{{ survey.creator.name }}</Badge>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- メッセージ表示 -->
            <Transition
              enter-active-class="transition ease-out duration-300"
              enter-from-class="transform opacity-0 translate-y-2"
              enter-to-class="transform opacity-100 translate-y-0"
              leave-active-class="transition ease-in duration-200"
              leave-from-class="transform opacity-100 translate-y-0"
              leave-to-class="transform opacity-0 translate-y-2"
            >
              <div 
                v-if="saveMessage"
                :class="['mx-3 mb-3 p-3 text-white rounded-lg shadow-lg',
                  messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
              >
                <div class="flex items-center gap-2">
                  <span class="font-medium text-sm">{{ saveMessage }}</span>
                  <Button 
                    v-if="messageType === 'delete' && lastDeletedReminder"
                    variant="link"
                    :class="messageType === 'delete' ? 'text-white hover:bg-red-400 p-1 h-auto ml-auto' : 'text-white hover:bg-green-400 p-1 h-auto ml-auto'"
                    @click.stop="handleUndoDelete"
                  >
                    <Undo2 class="h-4 w-4 mr-1" />
                    <span class="underline">元に戻す</span>
                  </Button>
                </div>
              </div>
            </Transition>
          </PopoverContent>
        </Popover>

        <!-- ユーザーメニュー -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon">
              <User class="h-5 w-5" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>総務部 アカウント</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
              <Link :href="route('profile.edit')">プロフィール設定</Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="showConfirmLogoutModal = true">
              ログアウト
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <ConfirmationModal
        :show="showConfirmLogoutModal"
        title="ログアウトの確認"
        message="ログアウトしてもよろしいですか？"
        :processing="form.processing"
        @close="showConfirmLogoutModal = false"
        @confirm="logout"
    />

    <!-- イベント詳細ダイアログ -->
    <EventDetailDialog
      :event="selectedEvent as any"
      :open="isEventDetailOpen"
      @update:open="(isOpen) => { isEventDetailOpen = isOpen; if (!isOpen) selectedEvent = null; }"
      @edit="handleEventEdit"
    />

    <!-- イベント編集/確認ダイアログ -->
    <CreateEventDialog
      :event="selectedEvent as any"
      :open="isEventEditOpen"
      @update:open="(isOpen) => { isEventEditOpen = isOpen; if (!isOpen) { selectedEvent = null; fetchNotifications(); } }"
    />

    <!-- メモ詳細ダイアログ -->
    <NoteDetailDialog
      :note="selectedNote as any"
      :open="selectedNote !== null"
      :team-members="teamMembers"
      :total-users="totalUsers"
      @update:open="(isOpen) => !isOpen && (selectedNote = null)"
      @save="handleNoteSave"
      @delete="handleNoteDelete"
      @toggle-pin="handleNoteTogglePin"
    />



    <!-- リマインダー詳細ダイアログ -->
    <ReminderDetailDialog
      :reminder="selectedReminder as any"
      :open="selectedReminder !== null"
      @update:open="(isOpen, completed) => { if (!isOpen) { if (completed && selectedReminder) { lastDeletedReminder = selectedReminder; showMessage('リマインダーを完了しました。', 'delete'); fetchNotifications(); } selectedReminder = null; } }"
      @update:reminder="fetchNotifications"
    />


  </header>
</template>
