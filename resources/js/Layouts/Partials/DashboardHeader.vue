<script setup lang="ts">
import { Link, useForm, router, usePage } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { Search, Bell, User, Calendar, StickyNote, BarChart3, Settings, Clock, Undo2, Menu, Sun, Moon, HelpCircle } from 'lucide-vue-next'
import { isDark, toggleDark } from '@/composables/useAppDark'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ScrollArea } from '@/components/ui/scroll-area'
import NoteDetailDialog from '@/components/NoteDetailDialog.vue'
import CreateNoteDialog from '@/components/CreateNoteDialog.vue'
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
  participants?: { id: number; name: string }[]
  location?: string
  description?: string
  importance?: string
}

interface Note {
  note_id: number
  title: string
  content: string | null
  author?: { name: string }
  participants?: { id: number; name: string }[]
  deadline_date?: string | null
  deadline_time?: string | null
  color: string
  priority: string
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
const isHelpOpen = ref(false)
const showEventsFilter = ref<'mine' | 'all'>(
  (localStorage.getItem('notif_events_filter') as 'mine' | 'all') || 'mine'
)
const showNotesFilter = ref<'mine' | 'all'>(
  (localStorage.getItem('notif_notes_filter') as 'mine' | 'all') || 'mine'
)

interface NotificationsData {
  events: Event[]
  notes: Note[]
  surveys: Survey[]
  reminders: Reminder[]
}

const notifications = ref<NotificationsData>({ events: [], notes: [], surveys: [], reminders: [] })
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

const getItemColor = (type: string, priority?: string, deadlineDate?: string, deadlineTime?: string, color?: string, category?: string) => {
  if (type === 'event') {
    let baseBg = 'bg-blue-50 dark:bg-[rgba(96,165,250,0.2)]'
    
    // カテゴリー別の背景色（ダークモード対応）
    if (category === '会議') baseBg = 'bg-blue-50 dark:bg-[rgba(96,165,250,0.2)]'
    else if (category === '業務') baseBg = 'bg-green-50 dark:bg-[rgba(74,222,128,0.2)]'
    else if (category === '来客') baseBg = 'bg-orange-50 dark:bg-[rgba(251,191,36,0.2)]'
    else if (category === '出張' || category === '出張・外出') baseBg = 'bg-purple-50 dark:bg-[rgba(167,139,250,0.2)]'
    else if (category === '休暇') baseBg = 'bg-pink-50 dark:bg-[rgba(244,114,182,0.2)]'
    
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return `${baseBg} border-red-500 border-2 dark:border-red-500`
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return `${baseBg} border-yellow-400 border-2 dark:border-yellow-400`
    }
    return `${baseBg} border-blue-200 dark:border-blue-800`
  }
  if (type === 'note') {
    let baseBg = 'bg-orange-50 dark:bg-[rgba(251,191,36,0.2)]'
    let darkBorder = 'dark:border-orange-800'
    
    // 色別の背景色とボーダー
    if (color === 'yellow') {
      baseBg = 'bg-yellow-50 dark:bg-[rgba(251,191,36,0.2)]'
      darkBorder = 'dark:border-yellow-600'
    } else if (color === 'blue') {
      baseBg = 'bg-blue-50 dark:bg-[rgba(96,165,250,0.2)]'
      darkBorder = 'dark:border-blue-600'
    } else if (color === 'green') {
      baseBg = 'bg-green-50 dark:bg-[rgba(74,222,128,0.2)]'
      darkBorder = 'dark:border-green-600'
    } else if (color === 'pink') {
      baseBg = 'bg-pink-50 dark:bg-[rgba(244,114,182,0.2)]'
      darkBorder = 'dark:border-pink-600'
    } else if (color === 'purple') {
      baseBg = 'bg-purple-50 dark:bg-[rgba(167,139,250,0.2)]'
      darkBorder = 'dark:border-purple-600'
    }

    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return `${baseBg} border-red-500 border-2 dark:border-red-500`
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return `${baseBg} border-yellow-400 border-2 dark:border-yellow-400`
    }
    return `${baseBg} border-orange-200 ${darkBorder}`
  }
  if (type === 'reminder') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-green-50 dark:bg-[rgba(74,222,128,0.2)] border-red-500 border-2 dark:border-red-500'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-green-50 dark:bg-[rgba(74,222,128,0.2)] border-yellow-400 border-2 dark:border-yellow-400'
    }
    return 'bg-green-50 dark:bg-[rgba(74,222,128,0.2)] border-green-200 dark:border-green-800'
  }
  if (type === 'survey') {
    if (deadlineDate && isOverdue(deadlineDate, deadlineTime)) {
      return 'bg-purple-50 dark:bg-[rgba(167,139,250,0.2)] border-red-500 border-2 dark:border-red-500'
    }
    if (deadlineDate && isUpcoming(deadlineDate, deadlineTime)) {
      return 'bg-purple-50 dark:bg-[rgba(167,139,250,0.2)] border-yellow-400 border-2 dark:border-yellow-400'
    }
  }
  return 'bg-purple-50 dark:bg-[rgba(167,139,250,0.2)] border-purple-200 dark:border-purple-800'
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

// 期限までの日数を計算する関数（日付ベース）
const getDaysFromDeadline = (deadlineDate: string, deadlineTime?: string) => {
  if (!deadlineDate) return 0
  
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  
  const deadline = new Date(deadlineDate)
  const deadlineDay = new Date(deadline.getFullYear(), deadline.getMonth(), deadline.getDate())
  
  const diffTime = deadlineDay.getTime() - today.getTime()
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))
  
  return diffDays
}

// 日数表示のテキストを生成する関数
const getDaysText = (deadlineDate: string, deadlineTime?: string) => {
  const days = getDaysFromDeadline(deadlineDate, deadlineTime)
  
  if (days > 0) {
    return `残り${days}日`
  } else if (days === 0) {
    return '今日が期限'
  } else {
    const absDays = Math.abs(days)
    if (absDays === 1) {
      // 昨日が期限だった場合は時間単位で表示
      const now = new Date()
      const deadline = new Date(deadlineDate)
      
      if (deadlineTime) {
        const [hours, minutes] = deadlineTime.split(':')
        deadline.setHours(parseInt(hours), parseInt(minutes))
      } else {
        deadline.setHours(23, 59, 59)
      }
      
      const diffTime = now.getTime() - deadline.getTime()
      const diffHours = Math.floor(diffTime / (1000 * 60 * 60))
      
      return `${diffHours}時間経過`
    } else {
      return `${absDays}日経過`
    }
  }
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

const handleCopyEvent = () => {
  const copyData = sessionStorage.getItem('event_copy_data')
  if (copyData) {
    const data = JSON.parse(copyData)
    const currentUserId = (page.props as any).auth?.user?.id ?? null
    const me = teamMembers.value.find((m: any) => m.id === currentUserId)
    
    isEventDetailOpen.value = false
    
    selectedEvent.value = {
      event_id: 0,
      title: data.title,
      category: data.category,
      importance: data.importance,
      location: data.location,
      description: data.description,
      url: data.url,
      progress: data.progress,
      participants: me ? [me] : [],
      start_date: data.date_range[0],
      end_date: data.date_range[1],
      is_all_day: data.is_all_day,
      start_time: data.start_time,
      end_time: data.end_time,
      creator: { name: '' }
    } as any
    isEventEditOpen.value = true
  }
}

const handleNoteDelete = (note: any) => {
  router.delete(`/notes/${note.note_id}`, {
    onSuccess: () => {
      fetchNotifications()
      selectedNote.value = null
      window.dispatchEvent(new CustomEvent('notification-updated'))
      window.dispatchEvent(new CustomEvent('trash-updated'))
    }
  })
}

const handleNoteTogglePin = (note: any) => {
  if (note.is_pinned) {
    router.delete(`/notes/${note.note_id}/unpin`, {
      onSuccess: () => {
        fetchNotifications()
        window.dispatchEvent(new CustomEvent('notification-updated'))
      }
    })
  } else {
    router.post(`/notes/${note.note_id}/pin`, {}, {
      onSuccess: () => {
        fetchNotifications()
        window.dispatchEvent(new CustomEvent('notification-updated'))
      }
    })
  }
}

const handleCopyNote = () => {
  console.log('[DashboardHeader] handleCopyNote called')
  selectedNote.value = null
  setTimeout(() => {
    console.log('[DashboardHeader] Opening CreateNoteDialog')
    isCreateNoteDialogOpen.value = true
  }, 100)
}

const isCreateNoteDialogOpen = ref(false)

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
      router.reload()
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
// 以下該当アイテムのみ差分更新
function updateNotificationItem<T extends { [key: string]: any }>(
  type: 'events' | 'notes' | 'reminders' | 'surveys',
  updatedItem: T,
  idKey: string
) {
  const list = notifications.value[type] as T[]
  const idx = list.findIndex(item => item[idKey] === updatedItem[idKey])
  if (idx !== -1) {
    // 差分更新: スプレッドコピー
    list[idx] = { ...list[idx], ...updatedItem }
  }
}

const handleNoteSave = (note: Note) => {
  const editable: Note = {
    ...note,
    content: note.content ?? ''
  }
  router.put(`/shared-notes/${note.note_id}`, {
    title: note.title,
    content: note.content ?? '',
    color: note.color,
    priority: note.priority,
    deadline_date: note.deadline_date,
    deadline_time: note.deadline_time,
    progress: (note as any).progress,
    participants: note.participants?.map(p => p.id) || []
  }, {
    preserveScroll: true,
    onSuccess: () => {
      // ダイアログ閉じ
      selectedNote.value = null

      const updated = (page.props as any)?.updatedNote
        if (!updated) return
      // 差分更新
      updateNotificationItem('notes', note, 'note_id')
      window.dispatchEvent(new CustomEvent('notification-updated'))
    }
  })
}

const handleEventSave = (event: Event) => {
  router.put(`/events/${event.event_id}`, {
    title: event.title,
    start_date: event.start_date,
    start_time: event.start_time,
    end_date: event.end_date,
    end_time: event.end_time,
    participants: event.participants?.map(p => p.id) || []
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedEvent.value = null
      updateNotificationItem('events', event, 'event_id')
    }
  })
}

const handleReminderSave = (reminder: Reminder) => {
  router.put(`/reminders/${reminder.reminder_id}`, {
    title: reminder.title,
    description: reminder.description,
    deadline_date: reminder.deadline_date,
    deadline_time: reminder.deadline_time,
    category: reminder.category,
    completed: reminder.completed
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedReminder.value = null
      updateNotificationItem('reminders', reminder, 'reminder_id')
    }
  })
}

const handleSurveySave = (survey: Survey) => {
  router.put(`/surveys/${survey.survey_id}`, {
    title: survey.title,
    deadline_date: survey.deadline_date,
    deadline_time: survey.deadline_time
  }, {
    preserveScroll: true,
    onSuccess: () => {
      updateNotificationItem('surveys', survey, 'survey_id')
    }
  })
}

// ----- Fetch Notifications (全件フェッチ用: 必要に応じ) -----
const fetchNotifications = async () => {
  if (isLoadingNotifications.value || !page.props.auth?.user) return

  isLoadingNotifications.value = true
  try {
    const params = new URLSearchParams({
      events_filter: showEventsFilter.value,
      notes_filter: showNotesFilter.value,
      _t: Date.now().toString(),
    })

    const res = await fetch(`/api/notifications?${params}`, { cache: 'no-store' })
    notifications.value = await res.json()
  } finally {
    isLoadingNotifications.value = false
  }
}

const handleCopyReminder = () => {
  const copyData = sessionStorage.getItem('reminder_copy_data')
  if (copyData) {
    const data = JSON.parse(copyData)
    selectedReminder.value = {
      reminder_id: 0,
      user_id: 0,
      title: data.title,
      description: data.description,
      deadline_date: data.deadline ? data.deadline.split('T')[0] : null,
      deadline_time: data.deadline ? data.deadline.split('T')[1] : null,
      completed: false,
      is_deleted: false,
      tags: data.tags?.map((name: string, index: number) => ({ tag_id: index, tag_name: name })) || [],
      created_at: null,
      updated_at: null,
      category: '',
      completed_at: null,
      deleted_at: null
    } as any
  } else {
    selectedReminder.value = null
  }
}


onMounted(() => {
  fetchNotifications()
  window.addEventListener('notification-updated', fetchNotifications)
  
  // Inertiaのページ読み込み後に通知更新フラグをチェック
  router.on('success', (event) => {
    const pageProps = event.detail.page.props as any
    if (pageProps.notification_updated || pageProps.flash?.notification_updated) {
      fetchNotifications()
    }
  })
})

onUnmounted(() => {
  window.removeEventListener('notification-updated', fetchNotifications)
})
</script>

<template>
  <header class="bg-background border-b border-border px-6 py-4">
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
            <Button variant="outline" size="icon" class="relative border-gray-300 dark:border-input">
              <Bell class="h-5 w-5" />
              <Badge class="absolute -top-1 -right-1 h-5 w-5 flex items-center text-white justify-center p-0 bg-red-500">
                {{ totalNotifications }}
              </Badge>
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-[420px] p-0 max-h-[80vh] flex flex-col bg-background border-border" align="end">
            <div class="p-4 border-b border-border">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="flex items-center gap-2">
                    <Bell class="h-5 w-5 text-blue-600" />
                    通知センター
                    
                    <Button
                      variant="ghost"
                      size="icon"
                      class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
                      @click.stop="isHelpOpen = true"
                      title="通知センターの使い方"
                    >
                      <HelpCircle class="h-4 w-4" />
                    </Button>
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
                          <div class="flex gap-1 p-1 bg-muted rounded-lg">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showEventsFilter === 'mine' ? 'bg-background shadow-sm text-foreground' : 'hover:bg-muted text-muted-foreground'"
                              :disabled="isLoadingNotifications"
                              @click="toggleEventsFilter"
                            >
                              自分のみ
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showEventsFilter === 'all' ? 'bg-background shadow-sm text-foreground' : 'hover:bg-muted text-muted-foreground'"
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
                          <div class="flex gap-1 p-1 bg-muted rounded-lg">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showNotesFilter === 'mine' ? 'bg-background shadow-sm text-foreground' : 'hover:bg-muted text-muted-foreground'"
                              :disabled="isLoadingNotifications"
                              @click="toggleNotesFilter"
                            >
                              自分のみ
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              class="flex-1 h-7 text-xs"
                              :class="showNotesFilter === 'all' ? 'bg-background shadow-sm text-foreground' : 'hover:bg-muted text-muted-foreground'"
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
                  <Badge class="ml-auto text-white text-xs bg-blue-500">{{ notifications.events.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="event in notifications.events" :key="event.event_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('event', undefined, event.end_date || event.start_date, event.end_time || event.start_time, undefined, (event as any).category)}`"
                    @click="handleClick('event', event)">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <div class="text-sm mb-1">{{ event.title }}</div>
                        <div class="text-xs text-gray-600 mb-1">
                          <span>{{ formatDateTime(event.end_date || event.start_date, event.end_time || event.start_time) }}</span>
                        </div>
                        <div v-if="event.participants && event.participants.length > 0" class="flex items-center gap-1 flex-wrap">
                          <Badge v-for="participant in event.participants" :key="participant.id" variant="outline" class="text-xs cursor-help" :title="participant.name" style="font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;">{{ getInitial(participant.name) }}</Badge>
                        </div>
                      </div>
                      <div class="flex flex-col items-end gap-1">
                        <Badge v-if="isOverdue(event.end_date || event.start_date, event.end_time || event.start_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                        <Badge v-else-if="isUpcoming(event.end_date || event.start_date, event.end_time || event.start_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                        <Badge v-if="(isOverdue(event.end_date || event.start_date, event.end_time || event.start_time) || isUpcoming(event.end_date || event.start_date, event.end_time || event.start_time))" 
                          :class="isOverdue(event.end_date || event.start_date, event.end_time || event.start_time) ? 'text-xs bg-red-500 text-white' : 'text-xs bg-yellow-500 text-white'">
                          {{ getDaysText(event.end_date || event.start_date, event.end_time || event.start_time) }}
                        </Badge>
                        <div v-if="!(isOverdue(event.end_date || event.start_date, event.end_time || event.start_time) || isUpcoming(event.end_date || event.start_date, event.end_time || event.start_time))" class="flex-1 flex items-end justify-end">
                          <Badge class="text-xs bg-blue-500 text-white">{{ event.creator.name }}</Badge>
                        </div>
                        <Badge v-else class="text-xs bg-blue-500 text-white">{{ event.creator.name }}</Badge>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.notes.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <StickyNote class="h-4 w-4 text-orange-600" />
                  <h4 class="text-sm">共有メモ</h4>
                  <Badge class="ml-auto text-white text-xs bg-orange-500">{{ notifications.notes.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="note in notifications.notes" :key="note.note_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('note', note.priority, note.deadline_date ?? undefined, note.deadline_time ?? undefined, note.color)}`"
                    @click="handleClick('note', note)">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <div class="text-sm mb-1">{{ note.title }}</div>
                        <div class="text-xs text-gray-600 mb-1">
                          <span>期限: {{ formatDateTime(note.deadline_date ?? undefined, note.deadline_time ?? undefined) }}</span>
                        </div>
                        <div v-if="note.participants && note.participants.length > 0" class="flex items-center gap-1 flex-wrap">
                          <Badge v-for="participant in note.participants" :key="participant.id" variant="outline" class="text-xs cursor-help" :title="participant.name" style="font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;">{{ getInitial(participant.name) }}</Badge>
                        </div>
                      </div>
                      <div class="flex flex-col items-end gap-1">
                        <Badge v-if="note.deadline_date && isOverdue(note.deadline_date, note.deadline_time ?? undefined)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                        <Badge v-else-if="note.deadline_date && isUpcoming(note.deadline_date, note.deadline_time ?? undefined)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                        <Badge v-if="note.deadline_date && (isOverdue(note.deadline_date, note.deadline_time ?? undefined) || isUpcoming(note.deadline_date ?? undefined, note.deadline_time ?? undefined))" 
                          :class="isOverdue(note.deadline_date, note.deadline_time ?? undefined) ? 'text-xs bg-red-500 text-white' : 'text-xs bg-yellow-500 text-white'">
                          {{ getDaysText(note.deadline_date, note.deadline_time ?? undefined) }}
                        </Badge>
                        <div v-if="!(note.deadline_date && (isOverdue(note.deadline_date, note.deadline_time ?? undefined) || isUpcoming(note.deadline_date ?? undefined, note.deadline_time ?? undefined)))" class="flex-1 flex items-end justify-end">
                          <Badge class="text-xs bg-orange-500 text-white">{{ note.author?.name || 'N/A' }}</Badge>
                        </div>
                        <Badge v-else class="text-xs bg-orange-500 text-white">{{ note.author?.name || 'N/A' }}</Badge>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.reminders.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <Clock class="h-4 w-4 text-green-600" />
                  <h4 class="text-sm">個人リマインダー</h4>
                  <Badge class="ml-auto text-white text-xs bg-green-500">{{ notifications.reminders.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="reminder in notifications.reminders" :key="reminder.reminder_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('reminder', undefined, reminder.deadline_date, reminder.deadline_time)}`"
                    @click="handleClick('reminder', reminder)">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <div class="text-sm mb-1">{{ reminder.title }}</div>
                        <div class="text-xs text-gray-600 flex items-center justify-between">
                          <span v-if="reminder.deadline_date">期限: {{ formatDateTime(reminder.deadline_date, reminder.deadline_time) }}</span>
                          <span v-else class="text-gray-400">期限なし</span>
                        </div>
                      </div>
                      <div class="flex flex-col items-end gap-1">
                        <Badge v-if="isOverdue(reminder.deadline_date, reminder.deadline_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                        <Badge v-else-if="isUpcoming(reminder.deadline_date, reminder.deadline_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                        <Badge v-if="reminder.deadline_date && (isOverdue(reminder.deadline_date, reminder.deadline_time) || isUpcoming(reminder.deadline_date, reminder.deadline_time))" 
                          :class="isOverdue(reminder.deadline_date, reminder.deadline_time) ? 'text-xs bg-red-500 text-white' : 'text-xs bg-yellow-500 text-white'">
                          {{ getDaysText(reminder.deadline_date, reminder.deadline_time) }}
                        </Badge>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="notifications.surveys.length > 0" class="p-3">
                <div class="flex items-center gap-2 mb-2">
                  <BarChart3 class="h-4 w-4 text-purple-600" />
                  <h4 class="text-sm">未回答アンケート</h4>
                  <Badge class="ml-auto text-white text-xs bg-purple-500">{{ notifications.surveys.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="survey in notifications.surveys" :key="survey.survey_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('survey', undefined, survey.deadline_date, survey.deadline_time)}`"
                    @click="handleClick('survey', survey)">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <div class="text-sm mb-1">{{ survey.title }}</div>
                        <div class="text-xs text-gray-600">
                          <span>回答期限: {{ formatDateTime(survey.deadline_date, survey.deadline_time) }}</span>
                        </div>
                      </div>
                      <div class="flex flex-col items-end gap-1">
                        <Badge v-if="survey.deadline_date && isOverdue(survey.deadline_date, survey.deadline_time)" class="text-xs bg-red-500 text-white">期限切れ</Badge>
                        <Badge v-else-if="survey.deadline_date && isUpcoming(survey.deadline_date, survey.deadline_time)" class="text-xs bg-yellow-500 text-white">期限間近</Badge>
                        <Badge v-if="survey.deadline_date && (isOverdue(survey.deadline_date, survey.deadline_time) || isUpcoming(survey.deadline_date, survey.deadline_time))" 
                          :class="isOverdue(survey.deadline_date, survey.deadline_time) ? 'text-xs bg-red-500 text-white' : 'text-xs bg-yellow-500 text-white'">
                          {{ getDaysText(survey.deadline_date, survey.deadline_time) }}
                        </Badge>
                        <div v-if="!(survey.deadline_date && (isOverdue(survey.deadline_date, survey.deadline_time) || isUpcoming(survey.deadline_date, survey.deadline_time)))" class="flex-1 flex items-end justify-end">
                          <Badge class="text-xs bg-purple-500 text-white">{{ survey.creator.name }}</Badge>
                        </div>
                        <Badge v-else class="text-xs bg-purple-500 text-white">{{ survey.creator.name }}</Badge>
                      </div>
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
            <Button variant="outline" size="icon" class="border-gray-300 dark:border-input">
              <User class="h-5 w-5" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>総務部 アカウント</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
              <Link :href="route('profile.edit')">プロフィール設定</Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
              <Link :href="route('trash.auto-delete')">ゴミ箱自動削除設定</Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
              <Link :href="route('calendar.settings')">カレンダー表示設定</Link>
            </DropdownMenuItem>
            <DropdownMenuItem @click="toggleDark()">
              <div class="flex items-center justify-between w-full">
                <span>ダークモード</span>
                <component :is="isDark ? Moon : Sun" class="h-4 w-4 ml-2" />
              </div>
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
      @copy="handleCopyEvent"
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
      @copy="handleCopyNote"
    />



    <!-- リマインダー詳細ダイアログ -->
    <ReminderDetailDialog
      :reminder="selectedReminder as any"
      :open="selectedReminder !== null"
      @update:open="(isOpen, completed) => { if (!isOpen) { if (completed && selectedReminder) { lastDeletedReminder = selectedReminder; showMessage('リマインダーを完了しました。', 'delete'); fetchNotifications(); } selectedReminder = null; } }"
      @update:reminder="fetchNotifications"
      @copy="handleCopyReminder"
    />
    
    <!-- ヘルプダイアログ -->
    <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
      <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>通知センターの使い方</DialogTitle>
        </DialogHeader>
        <div class="space-y-6 overflow-y-auto flex-1 pr-2">
          <div class="relative pl-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">🔔 通知アイテムの種類</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1">
                    <div class="flex items-center gap-2">
                      <Calendar class="h-4 w-4 text-blue-600" />
                      <Badge class="text-xs bg-blue-500 text-white">{{ notifications.events.length }}件</Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">共有カレンダー</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">チームメンバーと共有している予定が表示されます。期限が近いものは黄色、期限切れは赤色のバッジで強調されます。</p>
                  </div>
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1">
                    <div class="flex items-center gap-2">
                      <StickyNote class="h-4 w-4 text-orange-600" />
                      <Badge class="text-xs bg-orange-500 text-white">{{ notifications.notes.length }}件</Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">共有メモ</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">関係者と共有している重要なメモです。背景色はメモの設定色（重要度など）を反映します。</p>
                  </div>
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1">
                    <div class="flex items-center gap-2">
                      <Clock class="h-4 w-4 text-green-600" />
                      <Badge class="text-xs bg-green-500 text-white">{{ notifications.reminders.length }}件</Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">個人リマインダー</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">自分だけのToDoやリマインダーです。完了するとリストから消えます（詳細ダイアログから完了操作が可能）。</p>
                  </div>
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1">
                    <div class="flex items-center gap-2">
                      <BarChart3 class="h-4 w-4 text-purple-600" />
                      <Badge class="text-xs bg-purple-500 text-white">{{ notifications.surveys.length }}件</Badge>
                    </div>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">未回答アンケート</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">まだ回答していないアンケートが表示されます。クリックすると回答画面へ移動します。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="relative pl-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-transparent dark:from-green-950/30 p-4 rounded-r-lg">
            <h3 class="font-semibold mb-3 text-lg">⚙️ 表示設定</h3>
            <div class="space-y-4">
              <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0 pt-1">
                    <Button variant="ghost" size="icon" class="h-8 w-8 pointer-events-none opacity-100" tabindex="-1">
                      <Settings class="h-4 w-4" />
                    </Button>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium text-sm mb-1">表示フィルター</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">歯車アイコンから、共有カレンダーと共有メモの表示を「自分のみ」か「全員表示」で切り替えられます。</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-800 flex-shrink-0">
          <p class="text-sm text-blue-800 dark:text-blue-200 flex items-center gap-2">
            <span class="text-lg">💡</span>
            <span>通知センターで重要なタスクを一括管理しましょう！</span>
          </p>
        </div>
      </DialogContent>
    </Dialog>

    <!-- 共有メモ作成ダイアログ -->
    <CreateNoteDialog
      :open="isCreateNoteDialogOpen"
      :team-members="teamMembers"
      @update:open="isCreateNoteDialogOpen = $event"
      @save="() => { fetchNotifications(); isCreateNoteDialogOpen = false; }"
    />

  </header>
</template>
