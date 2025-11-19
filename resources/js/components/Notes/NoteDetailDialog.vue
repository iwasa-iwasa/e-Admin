<script setup lang="ts">
import { ref, watch, computed } from 'vue'
// Trash2 (ゴミ箱) アイコンを追加
import { User, Clock, Edit2, Save, X, MapPin, Trash2 } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type Priority = 'high' | 'medium' | 'low'

interface Props {
  note: App.Models.SharedNote | null
  open: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'save', value: App.Models.SharedNote): void
  (e: 'toggle-pin', value: App.Models.SharedNote): void
  (e: 'delete', value: App.Models.SharedNote): void // 削除イベントを追加
}>()

const isEditing = ref(false)
const editedNote = ref<App.Models.SharedNote | null>(null)

// Format date for input[type="date"] (YYYY-MM-DD format)
const formatDateForInput = (dateString: string | null | undefined): string => {
  if (!dateString) return ''
  
  // If already in YYYY-MM-DD format, return as is
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
    return dateString
  }
  
  // Try to parse and format the date
  try {
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return ''
    
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    
    return `${year}-${month}-${day}`
  } catch {
    return ''
  }
}

watch(() => props.note, (newNote) => {
  if (newNote) {
    editedNote.value = { 
        ...newNote,
        // deadlineをフォーム入力形式に整形して格納
        deadline: formatDateForInput(newNote.deadline)
    }
  } else {
    editedNote.value = null
  }
  isEditing.value = false
}, { deep: true })


const currentNote = computed(() => isEditing.value && editedNote.value ? editedNote.value : props.note)

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

const getColorClass = (color: string) => {
  const colorMap: Record<string, string> = {
    yellow: 'bg-yellow-50 border-yellow-300',
    blue: 'bg-blue-50 border-blue-300',
    green: 'bg-green-50 border-green-300',
    pink: 'bg-pink-50 border-pink-300',
    purple: 'bg-purple-50 border-purple-300',
  }
  return colorMap[color] || 'bg-gray-50 border-gray-300'
}

const handleEdit = () => {
  if (props.note) {
    // 編集開始時にもdeadlineを整形して格納
    editedNote.value = { 
        ...props.note,
        deadline: formatDateForInput(props.note.deadline)
    }
    isEditing.value = true
  }
}

const handleSave = () => {
  if (editedNote.value) {
    // 保存前に、editedNoteのdeadlineが'YYYY-MM-DD'形式であることを確認 (この形式でサーバーに送る)
    emit('save', editedNote.value)
  }
  isEditing.value = false
  emit('update:open', false)
}

const handleTogglePin = () => {
  if (props.note) {
    emit('toggle-pin', props.note)
  }
}

// 削除処理のハンドラ
const handleDelete = () => {
  if (props.note) {
    // 親コンポーネントに削除イベントを通知
    emit('delete', props.note)
    // ダイアログを閉じる
    emit('update:open', false)
  }
}

const handleCancel = () => {
  isEditing.value = false
  if (props.note) {
    // キャンセル時、元のnoteのdeadlineを再整形してeditedNoteに格納
    editedNote.value = { 
        ...props.note,
        deadline: formatDateForInput(props.note.deadline)
    }
  }
}

const closeDialog = () => {
    emit('update:open', false)
}

// Watch for deadline changes and format them
watch(() => editedNote.value?.deadline, (newDeadline) => {
  if (editedNote.value && newDeadline) {
    editedNote.value.deadline = formatDateForInput(newDeadline)
  }
})

const formatDate = (dateString: string | null | undefined): string => {
  if (!dateString) return ''
  try {
    return new Date(dateString).toLocaleDateString()
  } catch {
    return ''
  }
}

const editedDeadline = computed({
  get: (): string => {
    return editedNote.value?.deadline ?? ''
  },
  set: (val: string) => {
    if (!editedNote.value) return
    editedNote.value.deadline = val === '' ? null : val
  }
})

const editedContent = computed({
  get: (): string => {
    return editedNote.value?.content ?? ''
  },
  set: (val: string) => {
    if (!editedNote.value) return
    editedNote.value.content = val === '' ? null : val
  }
})

</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent v-if="currentNote" class="max-w-2xl max-h-[90vh]" aria-describedby="note-description">
      <DialogHeader>
        <div class="flex items-start justify-between gap-4">
          <DialogTitle class="flex-1">
            <Input
              v-if="isEditing && editedNote"
              v-model="editedNote.title"
              class="h-8"
              aria-label="メモタイトル"
            />
            <template v-else>{{ currentNote.title }}</template>
          </DialogTitle>
          <div class="flex items-center gap-2">
            <Badge :class="getPriorityInfo(currentNote.priority as Priority).className">
              {{ getPriorityInfo(currentNote.priority as Priority).label }}
            </Badge>
            <Button
              v-if="currentNote.is_pinned !== undefined"
              variant="ghost"
              size="sm"
              @click="handleTogglePin"
              :class="currentNote.is_pinned ? 'text-yellow-600' : 'text-gray-400'"
              aria-label="ピン留めの切り替え"
            >
              <MapPin class="h-4 w-4" :class="{ 'fill-yellow-600': currentNote.is_pinned }" />
            </Button>
            <Button 
                v-if="!isEditing"
                variant="ghost" 
                size="sm" 
                @click="handleDelete" 
                class="text-red-500 hover:text-red-600"
                aria-label="メモを削除"
            >
                <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600 pt-2">
          <div class="flex items-center gap-1">
            <User class="h-4 w-4" />
            <span>{{ currentNote.author?.name || 'N/A' }}</span>
          </div>
          <div class="flex items-center gap-1">
            <Clock class="h-4 w-4" />
            <span>{{ formatDate(currentNote.updated_at || currentNote.created_at) }}</span>
          </div>
          <div v-if="isEditing && editedNote" class="flex items-center gap-2">
            <span class="text-xs">期限:</span>
            <Input
              type="date"
              v-model="editedDeadline"
              class="h-7 w-40 text-xs"
              aria-label="期限日"
            />
          </div>
          <Badge v-else-if="currentNote.deadline" variant="outline" class="text-xs">
            期限: {{ currentNote.deadline }}
          </Badge>
        </div>
      </DialogHeader>

      <ScrollArea class="max-h-[60vh]">
        <div :class="[getColorClass(currentNote.color), 'border-2 rounded-lg p-6']">
          <Textarea
            v-if="isEditing && editedNote"
            v-model="editedContent"
            class="min-h-[200px] whitespace-pre-line bg-white"
            aria-label="メモ内容"
          />
          <p v-else class="whitespace-pre-line text-gray-800" id="note-description">
            {{ currentNote.content }}
          </p>
        </div>
      </ScrollArea>

      <DialogFooter class="gap-2">
        <template v-if="isEditing">
          <Button variant="outline" @click="handleCancel" size="sm">
            <X class="h-4 w-4 mr-1" />
            キャンセル
          </Button>
          <Button @click="handleSave" size="sm">
            <Save class="h-4 w-4 mr-1" />
            保存
          </Button>
        </template>
        <template v-else>
          <Button variant="outline" @click="handleEdit" size="sm">
            <Edit2 class="h-4 w-4 mr-1" />
            編集
          </Button>
        </template>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>