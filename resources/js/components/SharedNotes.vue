<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { StickyNote, Plus, User, AlertCircle, Calendar, CheckCircle, ArrowUp, ArrowDown, HelpCircle } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import CreateNoteDialog from '@/components/CreateNoteDialog.vue'
import NoteDetailDialog from '@/components/NoteDetailDialog.vue'

type Priority = 'high' | 'medium' | 'low'
type SortKey = 'priority' | 'deadline'
type SortDirection = 'asc' | 'desc'
type HeaderStage = 'normal' | 'compact' | 'iconOnly'

const props = defineProps<{
  notes: App.Models.SharedNote[]
  totalUsers?: number
  teamMembers?: App.Models.User[]
}>()

// 修正1: props直接変更を防ぐためローカルstateを作成
const localNotes = ref<App.Models.SharedNote[]>([...props.notes])

// propsの変更を監視してlocalNotesを同期
watch(() => props.notes, (newNotes) => {
  localNotes.value = [...newNotes]
}, { deep: true })

// 修正4: any型を排除し型安全性を強化
const isAllUsers = (participants: App.Models.User[]) => {
  return participants && props.totalUsers && participants.length === props.totalUsers
}

const sortKey = ref<SortKey>('priority')
const sortDirection = ref<SortDirection>('desc')
const isCreateDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const saveMessage = ref('')
const messageTimer = ref<number | null>(null)
const skipDialogOpen = ref(false)
const headerRef = ref<HTMLElement | null>(null)
const headerStage = ref<HeaderStage>('normal')
const isHelpOpen = ref(false)
let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  const url = new URL(window.location.href)
  const selectNoteId = url.searchParams.get('selectNote')
  if (selectNoteId) {
    skipDialogOpen.value = true
    const noteToSelect = localNotes.value.find(note => note.note_id.toString() === selectNoteId)
    if (noteToSelect) {
      scrollToNote(selectNoteId)
    }
    url.searchParams.delete('selectNote')
    window.history.replaceState({}, '', url.toString())
    setTimeout(() => {
      skipDialogOpen.value = false
    }, 500)
  }
  
  if (headerRef.value) {
    resizeObserver = new ResizeObserver(entries => {
      const width = entries[0].contentRect.width
      if (width < 350) {
        headerStage.value = 'iconOnly'
      } else if (width < 520) {
        headerStage.value = 'compact'
      } else {
        headerStage.value = 'normal'
      }
    })
    resizeObserver.observe(headerRef.value)
  }
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
  }
})

// selectNote処理もlocalNotesを使用
watch(() => localNotes.value, (newNotes) => {
  const url = new URL(window.location.href)
  const selectNoteId = url.searchParams.get('selectNote')
  
  if (selectNoteId) {
    skipDialogOpen.value = true
    const noteToSelect = newNotes.find(note => note.note_id.toString() === selectNoteId)
    if (noteToSelect) {
      scrollToNote(selectNoteId)
    }
    url.searchParams.delete('selectNote')
    window.history.replaceState({}, '', url.toString())
    setTimeout(() => {
      skipDialogOpen.value = false
    }, 500)
  }
}, { deep: true })

// 修正1: localNotesを更新するように変更
const handleUpdateNote = (updatedNote: App.Models.SharedNote) => {
  const index = localNotes.value.findIndex(note => note.note_id === updatedNote.note_id)
  if (index !== -1) {
    localNotes.value[index] = updatedNote
  }
}

const handleSaveNote = (updatedNote: App.Models.SharedNote) => {
  handleUpdateNote(updatedNote)
}

// 修正1: localNotesを更新するように変更
const handleDeleteNote = (deletedNote: App.Models.SharedNote) => {
  const index = localNotes.value.findIndex(note => note.note_id === deletedNote.note_id)
  if (index !== -1) {
    localNotes.value.splice(index, 1)
  }
  selectedNote.value = null
}

const handleTogglePin = (note: App.Models.SharedNote) => {
  const noteId = note.note_id
  if (note.is_pinned) {
    router.delete(route('notes.unpin', noteId), {
      onSuccess: () => {
        router.get(route('dashboard', { selectNote: noteId }), {}, { preserveState: false })
      }
    })
  } else {
    router.post(route('notes.pin', noteId), {}, {
      onSuccess: () => {
        router.get(route('dashboard', { selectNote: noteId }), {}, { preserveState: false })
      }
    })
  }
}

const showMessage = (message: string) => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 3000)
}

const getPriorityInfo = (priority: Priority) => {
  switch (priority) {
    case 'high':
      return { className: 'bg-red-600 text-white border-red-600', label: '重要' }
    case 'medium':
      return { className: 'bg-yellow-500 text-white border-yellow-500', label: '中' }
    case 'low':
      return { className: 'bg-gray-400 text-white border-gray-400', label: '低' }
  }
}

const getPriorityValue = (priority: Priority) => {
  switch (priority) {
    case 'high': return 3
    case 'medium': return 2
    case 'low': return 1
  }
}

const getColorClass = (color: string) => {
  const colorMap: { [key: string]: string } = {
    yellow: 'bg-yellow-100 border-yellow-300 dark:bg-card dark:border-yellow-600',
    blue: 'bg-blue-100 border-blue-300 dark:bg-card dark:border-blue-600',
    green: 'bg-green-100 border-green-300 dark:bg-card dark:border-green-600',
    pink: 'bg-pink-100 border-pink-300 dark:bg-card dark:border-pink-600',
    purple: 'bg-purple-100 border-purple-300 dark:bg-card dark:border-purple-600',
    gray: 'bg-gray-100 border-gray-300 dark:bg-card dark:border-gray-600',
  };
  return colorMap[color] || 'bg-gray-100 border-gray-300 dark:bg-card dark:border-gray-600';
}

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

const scrollToNote = (noteId: string) => {
  setTimeout(() => {
    const element = document.querySelector(`[data-note-id="${noteId}"]`)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, 100)
}

const handleSortClick = (key: SortKey) => {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortKey.value = key
    sortDirection.value = 'desc'
  }
}

// 修正5: 日付フォーマット処理をcomputed関数に切り出し
const formatDeadlineDate = (deadlineDate: string | null, deadlineTime: string | null) => {
  if (!deadlineDate) return ''
  const formatted = new Date(deadlineDate).toLocaleDateString('ja-JP', { 
    year: 'numeric', 
    month: '2-digit', 
    day: '2-digit' 
  }).replace(/\//g, '-')
  const time = (deadlineTime || '23:59:00').substring(0, 5)
  return `${formatted} ${time}`
}

const formatCreatedDate = (createdAt: string | null) => {
  if (!createdAt) return ''
  return new Date(createdAt).toLocaleString('ja-JP', { 
    year: 'numeric', 
    month: '2-digit', 
    day: '2-digit', 
    hour: '2-digit', 
    minute: '2-digit' 
  }).replace(/\//g, '-')
}

// localNotesを使用するように変更
const sortedNotes = computed(() => {
  if (!localNotes.value) return []
  return [...localNotes.value].sort((a, b) => {
    let result = 0
    if (sortKey.value === 'priority') {
      result = getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
      if (result === 0) {
        result = (a.deadline_date || '9999-12-31').localeCompare(b.deadline_date || '9999-12-31')
      }
    } else {
      result = (a.deadline_date || '9999-12-31').localeCompare(b.deadline_date || '9999-12-31')
      if (result === 0) {
        result = getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
      }
    }
    return sortDirection.value === 'desc' ? result : -result
  })
})

export interface SharedNote {
  note_id: number
  title: string
  author: { name: string }
  participants?: { id: number; name: string }[]
  deadline_date?: string | null
  deadline_time?: string | null
  color: string
  priority: 'high' | 'medium' | 'low'
  is_pinned?: boolean
}

export interface Note extends SharedNote {
  content: string
}


</script>

<template>
  <Card class="h-full flex flex-col">
    <CardHeader>
      <div ref="headerRef" class="flex items-center justify-between gap-2">
        <div class="flex justify-start gap-2 items-center">
          <div class="flex items-center gap-2 min-w-0 cursor-pointer hover:opacity-70 transition-opacity" @click="router.visit('/notes')">
            <StickyNote class="h-6 w-6 text-orange-600 flex-shrink-0" />
            <Transition
              enter-active-class="transition-all duration-300 ease-in-out"
              leave-active-class="transition-all duration-300 ease-in-out"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <CardTitle v-if="headerStage !== 'iconOnly'" class="whitespace-nowrap flex items-center gap-2">
                共有メモ
              </CardTitle>
            </Transition>
        </div>
        
        <Button
            variant="ghost"
            size="icon"
            class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
            @click="isHelpOpen = true"
            title="共有メモの使い方"
          >
            <HelpCircle class="h-5 w-5" />
        </Button>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <button
              @click="handleSortClick('priority')"
              :class="[
                'flex items-center justify-center rounded text-xs transition-all duration-300 ease-in-out',
                headerStage === 'iconOnly' ? 'w-8 h-8 p-0' : 'gap-1 py-1 px-2',
                sortKey === 'priority' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400'
              ]"
              :title="headerStage !== 'normal' ? '重要度順' : undefined"
            >
              <AlertCircle :class="['h-3.5 w-3.5 flex-shrink-0', sortKey === 'priority' ? 'text-red-500' : 'text-gray-400']" />
              <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-auto opacity-100"
                leave-from-class="w-auto opacity-100"
                leave-to-class="w-0 opacity-0"
              >
                <span v-if="headerStage === 'normal'" class="whitespace-nowrap">重要度順</span>
              </Transition>
              <component 
                :is="sortDirection === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'priority'" 
                class="h-3.5 w-3.5 flex-shrink-0" 
              />
            </button>
            <button
              @click="handleSortClick('deadline')"
              :class="[
                'flex items-center justify-center rounded text-xs transition-all duration-300 ease-in-out',
                headerStage === 'iconOnly' ? 'w-8 h-8 p-0' : 'gap-1 py-1 px-2',
                sortKey === 'deadline' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100' : 'hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400'
              ]"
              :title="headerStage !== 'normal' ? '期限順' : undefined"
            >
              <Calendar :class="['h-3.5 w-3.5 flex-shrink-0', sortKey === 'deadline' ? 'text-blue-500' : 'text-gray-400']" />
              <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-auto opacity-100"
                leave-from-class="w-auto opacity-100"
                leave-to-class="w-0 opacity-0"
              >
                <span v-if="headerStage === 'normal'" class="whitespace-nowrap">期限順</span>
              </Transition>
              <component 
                :is="sortDirection === 'desc' ? ArrowDown : ArrowUp" 
                v-if="sortKey === 'deadline'" 
                class="h-3.5 w-3.5 flex-shrink-0" 
              />
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
        <div class="space-y-3">
          <div
            v-for="note in sortedNotes"
            :key="note.note_id"
            :data-note-id="note.note_id"
            :class="[
              isOverdue(note.deadline_date, note.deadline_time) 
                ? 'bg-gray-100 border-gray-400 border-2 dark:bg-card dark:border-gray-500' 
                : getColorClass(note.color), 
              'border-2 rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow',
              selectedNote?.note_id === note.note_id ? '' : ''
            ]"
            @click="selectedNote = note"
          >
            <div class="flex items-start justify-between mb-2 flex-wrap gap-2">
              <div class="flex-1 min-w-0">
                <h4 class="mb-1 truncate">{{ note.title }}</h4>
                <div v-if="note.progress !== undefined && note.progress !== null" class="flex items-center gap-2">
                  <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      class="h-full bg-blue-500 transition-all duration-300" 
                      :style="{ width: `${note.progress}%` }"
                    ></div>
                  </div>
                  <span class="text-xs text-gray-600">{{ note.progress }}%</span>
                </div>
              </div>
              <Badge :class="[getPriorityInfo(note.priority as Priority).className, 'text-xs px-2 py-0.5 flex-shrink-0']">
                {{ getPriorityInfo(note.priority as Priority).label }}
              </Badge>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line mb-2">
              {{ note.content }}
            </p>
            <div v-if="note.tags && note.tags.length > 0" class="flex flex-wrap gap-1 mb-2">
              <Badge v-for="tag in note.tags" :key="tag.tag_name" variant="secondary" class="text-xs">
                {{ tag.tag_name }}
              </Badge>
            </div>
            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 flex-wrap gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  <span class="truncate">{{ note.author?.name || 'N/A' }}</span>
                </div>
                <div v-if="note.participants && note.participants.length > 0" class="flex items-center gap-1 flex-wrap">
                  <Badge v-if="isAllUsers(note.participants)" variant="outline" class="text-xs text-blue-600 border-blue-300">
                    全員
                  </Badge>
                  <template v-else>
                    <Badge v-for="participant in note.participants.slice(0, 3)" :key="participant.id" variant="outline" class="text-xs text-blue-600 border-blue-300">
                      {{ participant.name }}
                    </Badge>
                    <Badge v-if="note.participants.length > 3" variant="outline" class="text-xs text-blue-600 border-blue-300">
                      +{{ note.participants.length - 3 }}
                    </Badge>
                  </template>
                </div>
                <Badge v-if="isOverdue(note.deadline_date, note.deadline_time)" variant="outline" class="text-xs h-5 bg-gray-200 text-gray-700 border-gray-400">
                  期限切れ
                </Badge>
                <Badge v-else variant="outline" class="text-xs h-5">
                  {{ note.deadline_date ? '期限' : '作成日' }}: {{ note.deadline_date ? formatDeadlineDate(note.deadline_date, note.deadline_time) : formatCreatedDate(note.created_at) }}
                </Badge>
              </div>
            </div>
          </div>
        </div>
      </ScrollArea>
    </CardContent>
    <CreateNoteDialog
      :open="isCreateDialogOpen"
      @update:open="isCreateDialogOpen = $event"
      :teamMembers="teamMembers"
    />
    <NoteDetailDialog
      :note="selectedNote"
      :open="selectedNote !== null && !skipDialogOpen"
      @update:open="(isOpen) => !isOpen && (selectedNote = null)"
      @update:note="handleUpdateNote"
      @save="handleSaveNote"
      @delete="handleDeleteNote"
      @toggle-pin="handleTogglePin"
      :teamMembers="teamMembers"
      :totalUsers="totalUsers"
    />
    
    <!-- 成功メッセージ -->
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
        class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[9999] p-3 bg-green-500 text-white rounded-lg shadow-lg"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
        </div>
      </div>
    </Transition>
  </Card>

  <!-- ヘルプダイアログ -->
  <Dialog v-model:open="isHelpOpen">
    <DialogContent class="max-w-2xl">
      <DialogHeader>
        <DialogTitle>共有メモの使い方</DialogTitle>
      </DialogHeader>
      <div class="space-y-4">
        <div>
          <h3 class="font-semibold mb-2">基本操作</h3>
          <ul class="space-y-1 text-sm text-gray-600">
            <li>• メモ作成：「新規作成」ボタンからメモを作成できます</li>
            <li>• メモ選択：メモをクリックして詳細を表示できます</li>
            <li>• 保存：詳細ダイアログで変更を保存できます</li>
          </ul>
        </div>
        <div>
          <h3 class="font-semibold mb-2">整理機能</h3>
          <ul class="space-y-1 text-sm text-gray-600">
            <li>• ピン留め：重要なメモを上部に固定できます</li>
            <li>• ソート：重要度、期限で並び替えできます</li>
            <li>• 期限管理：期限切れのメモはグレーで表示されます</li>
          </ul>
        </div>
        <div>
          <h3 class="font-semibold mb-2">共有機能</h3>
          <ul class="space-y-1 text-sm text-gray-600">
            <li>• メンバー選択：特定のメンバーとメモを共有できます</li>
            <li>• タグ付け：メモをカテゴリ分けできます</li>
            <li>• 期限設定：メモに期限を設定できます</li>
          </ul>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>