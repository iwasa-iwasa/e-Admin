<script setup lang="ts">
import { ref, computed } from 'vue'
import { StickyNote, Plus, User, AlertCircle, Calendar, CheckCircle } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import CreateNoteDialog from './CreateNoteDialog.vue'
import NoteDetailDialog from './NoteDetailDialog.vue'

type Priority = 'high' | 'medium' | 'low'
type SortOrder = 'priority' | 'deadline'

const props = defineProps<{
  notes: App.Models.SharedNote[]
}>()

const sortOrder = ref<SortOrder>('priority')
const isCreateDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const saveMessage = ref('')
const messageTimer = ref<number | null>(null)

const handleUpdateNote = (updatedNote: App.Models.SharedNote) => {
  // This should ideally emit an event to the parent to refresh data
  const index = props.notes.findIndex(note => note.note_id === updatedNote.note_id)
  if (index !== -1) {
    props.notes[index] = updatedNote
  }
}

const handleSaveNote = (updatedNote: App.Models.SharedNote) => {
  // Save the note via API call
  // This is a simplified version - in a real app you'd make an API call
  handleUpdateNote(updatedNote)
  showMessage('メモが保存されました。')
}

const handleDeleteNote = (deletedNote: App.Models.SharedNote) => {
  // 削除されたメモをリストから除外
  const index = props.notes.findIndex(note => note.note_id === deletedNote.note_id)
  if (index !== -1) {
    props.notes.splice(index, 1)
  }
  selectedNote.value = null
}

const handleTogglePin = (note: App.Models.SharedNote) => {
  const noteId = note.note_id
  if (note.is_pinned) {
    router.delete(route('notes.unpin', noteId), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        // リスト内のメモのピン状態を更新
        const noteIndex = props.notes.findIndex(n => n.note_id === noteId)
        if (noteIndex !== -1) {
          props.notes[noteIndex].is_pinned = false
        }
      }
    })
  } else {
    router.post(route('notes.pin', noteId), {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        // リスト内のメモのピン状態を更新
        const noteIndex = props.notes.findIndex(n => n.note_id === noteId)
        if (noteIndex !== -1) {
          props.notes[noteIndex].is_pinned = true
        }
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
    yellow: 'bg-yellow-100 border-yellow-300',
    blue: 'bg-blue-100 border-blue-300',
    green: 'bg-green-100 border-green-300',
    pink: 'bg-pink-100 border-pink-300',
    purple: 'bg-purple-100 border-purple-300',
  };
  return colorMap[color] || 'bg-gray-100 border-gray-300';
}

const toggleSortOrder = () => {
  sortOrder.value = sortOrder.value === 'priority' ? 'deadline' : 'priority'
}

const sortedNotes = computed(() => {
  if (!props.notes) return []
  return [...props.notes].sort((a, b) => {
    if (sortOrder.value === 'priority') {
      const priorityDiff = getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
      if (priorityDiff !== 0) return priorityDiff
      return (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
    } else {
      const deadlineDiff = (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
      if (deadlineDiff !== 0) return deadlineDiff
      return getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
    }
  })
})

</script>

<template>
  <Card class="h-full flex flex-col">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <StickyNote class="h-5 w-5 text-yellow-600" />
          <CardTitle class="text-lg">共有メモ</CardTitle>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
            <button
              @click="sortOrder = 'priority'"
              :class="['flex items-center justify-center gap-1.5 py-1 px-3 rounded text-xs transition-all w-24 whitespace-nowrap', sortOrder === 'priority' ? 'bg-white shadow-sm border border-input text-gray-900' : 'hover:bg-gray-200 text-gray-500']"
            >
              <AlertCircle :class="['h-3.5 w-3.5', sortOrder === 'priority' ? 'text-red-500' : 'text-gray-400']" />
              優先度順
            </button>
            <button
              @click="sortOrder = 'deadline'"
              :class="['flex items-center justify-center gap-1.5 py-1 px-3 rounded text-xs transition-all w-24 whitespace-nowrap', sortOrder === 'deadline' ? 'bg-white shadow-sm border border-input text-gray-900' : 'hover:bg-gray-200 text-gray-500']"
            >
              <Calendar :class="['h-3.5 w-3.5', sortOrder === 'deadline' ? 'text-blue-500' : 'text-gray-400']" />
              期限順
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
    </CardHeader>
    <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
      <ScrollArea class="h-full">
        <div class="space-y-3">
          <div
            v-for="note in sortedNotes"
            :key="note.note_id"
            :class="[getColorClass(note.color), 'border-2 rounded-lg p-3 cursor-pointer hover:shadow-md transition-shadow']"
            @click="selectedNote = note"
          >
            <div class="flex items-start justify-between mb-2">
              <h4 class="flex-1">
                {{ note.title }}
              </h4>
              <Badge :class="[getPriorityInfo(note.priority as Priority).className, 'text-xs px-2 py-0.5']">
                {{ getPriorityInfo(note.priority as Priority).label }}
              </Badge>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line mb-2">
              {{ note.content }}
            </p>
            <div v-if="note.tags && note.tags.length > 0" class="flex flex-wrap gap-1 mb-2">
              <Badge v-for="tag in note.tags" :key="tag.tag_name" variant="secondary" class="text-xs">
                {{ tag.tag_name }}
              </Badge>
            </div>
            <div class="flex items-center justify-between text-xs text-gray-600">
              <div class="flex items-center gap-2">
                <div class="flex items-center gap-1">
                  <User class="h-3 w-3" />
                  {{ note.author?.name || 'N/A' }}
                </div>
                <Badge v-if="note.deadline" variant="outline" class="text-xs h-5">
                  期限: {{ note.deadline }}
                </Badge>
              </div>
              <span>{{ new Date(note.created_at).toLocaleDateString() }}</span>
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
      @save="handleSaveNote"
      @delete="handleDeleteNote"
      @toggle-pin="handleTogglePin"
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
</template>
