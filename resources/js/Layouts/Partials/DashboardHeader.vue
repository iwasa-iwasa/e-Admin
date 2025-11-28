<script setup lang="ts">
import { Link, useForm, router } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import { ref, onMounted, computed } from 'vue'
import { Search, Bell, User, Calendar, StickyNote, BarChart3, Settings, Clock, Undo2 } from 'lucide-vue-next'
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
import NotificationSettingsDialog from '@/components/NotificationSettingsDialog.vue'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'

const showConfirmLogoutModal = ref(false);
const form = useForm({});

const logout = () => {
    form.post(route('logout'));
};

interface Event {
  event_id: number
  title: string
  start_date: string
  end_date?: string
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
  deadline: string
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
const isNotificationSettingsOpen = ref(false)
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

const insertSearchOption = (option: string) => {
  searchQuery.value += option
}

const isLoadingNotifications = ref(false)

const fetchNotifications = async () => {
  if (isLoadingNotifications.value) return
  
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

const getItemColor = (type: string, priority?: string) => {
  if (type === 'event') return 'bg-blue-50 border-blue-200'
  if (type === 'note') {
    switch (priority) {
      case 'high': return 'bg-red-50 border-red-200'
      case 'medium': return 'bg-yellow-50 border-yellow-200'
      default: return 'bg-green-50 border-green-200'
    }
  }
  if (type === 'reminder') return 'bg-green-50 border-green-200'
  return 'bg-purple-50 border-purple-200'
}

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('ja-JP')
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

onMounted(fetchNotifications)
</script>

<template>
  <header class="bg-white border-b border-gray-300 px-6 py-4">
    <div class="flex items-center justify-between gap-4">
      <!-- 強力な検索バー -->
      <div class="flex-1 max-w-2xl">
        <Popover v-model:open="isSearchFocused">
          <PopoverTrigger as-child>
            <div
              class="relative"
              @mouseenter="isSearchFocused = true"
              @mouseleave="isSearchFocused = false"
            >
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
              <Input
                type="text"
                placeholder="日付、名前、キーワードで検索... (例: 2025-10-20, 田中, 会議)"
                class="pl-10 pr-4 py-2 w-full text-gray-500 border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                v-model="searchQuery"
              />
            </div>
          </PopoverTrigger>
          <PopoverContent
            class="w-80 p-2  border-gray-300"
            align="start"
            side="bottom"
            @mouseenter="isSearchFocused = true"
            @mouseleave="isSearchFocused = false"
          >
            <div class="space-y-1">
              <p class="text-xs text-gray-500 px-2 py-1">
                検索オプション
              </p>
              <button
                @click="() => { insertSearchOption('タイトル:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-blue-600">T</span>
                <span>タイトル</span>
              </button>
              <button
                @click="() => { insertSearchOption('重要度:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-red-600">!!</span>
                <span>重要度</span>
              </button>
              <button
                @click="() => { insertSearchOption('日付:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>🗓️</span>
                <span>日付</span>
              </button>
              <button
                @click="() => { insertSearchOption('終了日:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-orange-600">End</span>
                <span>ある日付までの予定</span>
              </button>
              <button
                @click="() => { insertSearchOption('開始日:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-green-600">Start</span>
                <span>ある日付からの予定</span>
              </button>
              <button
                @click="() => { insertSearchOption('ジャンル:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span class="text-purple-600">#</span>
                <span>ジャンル</span>
              </button>
              <button
                @click="() => { insertSearchOption('メンバー:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>👤</span>
                <span>メンバー</span>
              </button>
              <button
                @click="() => { insertSearchOption('会議室:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>🚪</span>
                <span>会議室</span>
              </button>
              <button
                @click="() => { insertSearchOption('メモ:'); isSearchFocused = false; }"
                class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm"
              >
                <span>📝</span>
                <span>メモ</span>
              </button>
            </div>
          </PopoverContent>
        </Popover>
        <p class="text-xs text-gray-500 mt-1 ml-1">
          すべての予定、メモ、リマインダーを横断検索
        </p>
      </div>

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
            
            <div class="flex-1 overflow-y-auto scrollbar-hide">
              <div v-if="notifications.events.length > 0" class="p-3 border-b border-gray-300">
                <div class="flex items-center gap-2 mb-2">
                  <Calendar class="h-4 w-4 text-blue-600" />
                  <h4 class="text-sm">共有カレンダー</h4>
                  <Badge class="ml-auto text-xs bg-blue-500">{{ notifications.events.length }}件</Badge>
                </div>
                <div class="space-y-2">
                  <div v-for="event in notifications.events" :key="event.event_id"
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('event')}`"
                    @click="handleClick('event', event)">
                    <div class="text-sm mb-1">{{ event.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>{{ formatDate(event.end_date || event.start_date) }}</span>
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
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('note', note.priority)}`"
                    @click="handleClick('note', note)">
                    <div class="text-sm mb-1">{{ note.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>期限: {{ formatDate(note.deadline_date) }}</span>
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
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('reminder')}`"
                    @click="handleClick('reminder', reminder)">
                    <div class="text-sm mb-1">{{ reminder.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>期限: {{ formatDate(reminder.deadline_date) }} {{ reminder.deadline_time ? reminder.deadline_time.substring(0, 5) : '' }}</span>
                      <Badge variant="outline" class="text-xs">{{ reminder.category }}</Badge>
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
                    :class="`p-2 rounded-lg hover:opacity-80 cursor-pointer transition-colors border ${getItemColor('survey')}`"
                    @click="handleClick('survey', survey)">
                    <div class="text-sm mb-1">{{ survey.title }}</div>
                    <div class="text-xs text-gray-600 flex items-center justify-between">
                      <span>回答期限: {{ formatDate(survey.deadline) }}</span>
                      <Badge variant="outline" class="text-xs">{{ survey.creator.name }}</Badge>
                    </div>
                  </div>
                </div>
              </div>
            </div>
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
            <DropdownMenuItem @click="isNotificationSettingsOpen = true">
              通知設定
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
        title="Logout Confirmation"
        message="Are you sure you want to log out?"
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
      @update:open="(isOpen) => !isOpen && (selectedNote = null)"
      @save="handleNoteSave"
      @delete="handleNoteDelete"
      @toggle-pin="handleNoteTogglePin"
    />

    <!-- 通知設定ダイアログ -->
    <NotificationSettingsDialog
      :open="isNotificationSettingsOpen"
      @update:open="isNotificationSettingsOpen = $event"
    />

    <!-- リマインダー詳細ダイアログ -->
    <ReminderDetailDialog
      :reminder="selectedReminder as any"
      :open="selectedReminder !== null"
      @update:open="(isOpen, completed) => { if (!isOpen) { if (completed && selectedReminder) { lastDeletedReminder = selectedReminder; showMessage('リマインダーを完了しました。', 'delete'); fetchNotifications(); } selectedReminder = null; } }"
      @update:reminder="fetchNotifications"
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
        :class="['fixed bottom-6 left-1/2 transform -translate-x-1/2 z-[9999] p-3 text-white rounded-lg shadow-lg pointer-events-auto',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
        @click.stop
      >
        <div class="flex items-center gap-2">
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
  </header>
</template>
