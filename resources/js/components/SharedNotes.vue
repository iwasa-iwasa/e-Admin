<script setup lang="ts">
import { ref, computed } from 'vue'
import { StickyNote, Plus, User, AlertCircle, Calendar } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import CreateNoteDialog from './CreateNoteDialog.vue'
import NoteDetailDialog from './NoteDetailDialog.vue'

type Priority = 'high' | 'medium' | 'low'
type SortOrder = 'priority' | 'deadline'

interface Note {
  id: number
  title: string
  content: string
  author: string
  date: string
  deadline?: string
  pinned: boolean
  color: string
  priority: Priority
}

const sortOrder = ref<SortOrder>('priority')
const isCreateDialogOpen = ref(false)
const selectedNote = ref<Note | null>(null)
const notesData = ref<Note[]>([
  {
    id: 1,
    title: '備品発注リスト',
    content: '・コピー用紙 A4 10箱\n・ボールペン 黒 50本\n・クリアファイル 100枚',
    author: '佐藤',
    date: '2025-10-13',
    deadline: '2025-10-20',
    pinned: true,
    color: 'bg-yellow-100 border-yellow-300',
    priority: 'high',
  },
  {
    id: 2,
    title: '来客対応メモ',
    content: '10/15 14:00 A社 山本様\n会議室Bを予約済み',
    author: '田中',
    date: '2025-10-12',
    deadline: '2025-10-15',
    pinned: true,
    color: 'bg-blue-100 border-blue-300',
    priority: 'high',
  },
  {
    id: 3,
    title: '月次報告の進捗',
    content: '経理：完了\n人事：作業中\n総務：未着手',
    author: '鈴木',
    date: '2025-10-11',
    deadline: '2025-10-18',
    pinned: false,
    color: 'bg-green-100 border-green-300',
    priority: 'medium',
  },
  {
    id: 4,
    title: '社内イベント企画',
    content: '忘年会の候補日：12/20, 12/22\n参加人数：約50名',
    author: '山田',
    date: '2025-10-10',
    deadline: '2025-11-30',
    pinned: false,
    color: 'bg-pink-100 border-pink-300',
    priority: 'low',
  },
])

const handleUpdateNote = (updatedNote: Note) => {
  notesData.value = notesData.value.map(note => note.id === updatedNote.id ? updatedNote : note)
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

const toggleSortOrder = () => {
  sortOrder.value = sortOrder.value === 'priority' ? 'deadline' : 'priority'
}

const sortedNotes = computed(() => {
  return [...notesData.value].sort((a, b) => {
    if (sortOrder.value === 'priority') {
      const priorityDiff = getPriorityValue(b.priority) - getPriorityValue(a.priority)
      if (priorityDiff !== 0) return priorityDiff
      return (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
    } else {
      const deadlineDiff = (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
      if (deadlineDiff !== 0) return deadlineDiff
      return getPriorityValue(b.priority) - getPriorityValue(a.priority)
    }
  })
})

</script>

<template>
  <Card class="h-full flex flex-col">
    <CardHeader>
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <StickyNote class="h-5 w-5 text-yellow-600" />
          <CardTitle class="text-lg">共有メモ</CardTitle>
        </div>
        <Button
          size="sm"
          variant="outline"
          class="h-8 gap-1"
          @click="isCreateDialogOpen = true"
        >
          <Plus class="h-3 w-3" />
          追加
        </Button>
      </div>
      <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
        <button
          @click="toggleSortOrder"
          :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'priority' ? 'bg-white shadow-sm border border-gray-200' : 'hover:bg-gray-200']"
        >
          <AlertCircle :class="['h-3.5 w-3.5', sortOrder === 'priority' ? 'text-red-600' : 'text-gray-400']" />
          <span :class="['text-xs', sortOrder === 'priority' ? 'text-gray-900' : 'text-gray-500']">
            優先度順
          </span>
        </button>
        <button
          @click="toggleSortOrder"
          :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'deadline' ? 'bg-white shadow-sm border border-gray-200' : 'hover:bg-gray-200']"
        >
          <Calendar :class="['h-3.5 w-3.5', sortOrder === 'deadline' ? 'text-blue-600' : 'text-gray-400']" />
          <span :class="['text-xs', sortOrder === 'deadline' ? 'text-gray-900' : 'text-gray-500']">
            期限順
          </span>
        </button>
      </div>
    </CardHeader>
    <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
      <ScrollArea class="h-full">
        <div class="space-y-3">
          <div
            v-for="note in sortedNotes"
            :key="note.id"
            :class="[note.color, 'border-2 rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow']"
            @click="selectedNote = note"
          >
            <div class="flex items-start justify-between mb-2">
              <h4 class="flex-1">
                {{ note.title }}
              </h4>
              <Badge :class="[getPriorityInfo(note.priority).className, 'text-xs px-2 py-0.5']">
                {{ getPriorityInfo(note.priority).label }}
              </Badge>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line mb-2">
              {{ note.content }}
            </p>
            <div class="flex items-center justify-between text-xs text-gray-600">
              <div class="flex items-center gap-2">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  {{ note.author }}
                </div>
                <Badge v-if="note.deadline" variant="outline" class="text-xs h-5">
                  期限: {{ note.deadline }}
                </Badge>
              </div>
              <span>{{ note.date }}</span>
            </div>
          </div>
        </div>
      </ScrollArea>
    </CardContent>
    <CreateNoteDialog
      :open="isCreateDialogOpen"
      @update:open="isCreateDialogOpen = $event"
    />
    <NoteDetailDialog
      :note="selectedNote"
      :open="selectedNote !== null"
      @update:open="(isOpen) => !isOpen && (selectedNote = null)"
      @update:note="handleUpdateNote"
    />
  </Card>
</template>
