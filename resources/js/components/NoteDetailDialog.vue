<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { User, Clock, Edit2, Save, X, MapPin, Trash2, CheckCircle, Undo2 } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
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
  'update:open': [value: boolean]
  'save': [value: App.Models.SharedNote]
  'toggle-pin': [value: App.Models.SharedNote]
  'delete': [value: App.Models.SharedNote]
}>()

const isEditing = ref(false)
const editedNote = ref<App.Models.SharedNote | null>(null)
const tagInput = ref('')
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedNote = ref<App.Models.SharedNote | null>(null)

watch(() => props.note, (newNote) => {
  if (newNote) {
    editedNote.value = { ...newNote }
  } else {
    editedNote.value = null
  }
  isEditing.value = false
  tagInput.value = ''
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

const getColorInfo = (c: string) => {
  const colorMap: Record<string, { bg: string; label: string }> = {
    yellow: { bg: 'bg-yellow-100', label: 'イエロー' },
    blue: { bg: 'bg-blue-100', label: 'ブルー' },
    green: { bg: 'bg-green-100', label: 'グリーン' },
    pink: { bg: 'bg-pink-100', label: 'ピンク' },
    purple: { bg: 'bg-purple-100', label: 'パープル' },
  }
  return colorMap[c] || colorMap.yellow
}

const handleEdit = () => {
  if (props.note) {
    editedNote.value = { ...props.note }
    isEditing.value = true
  }
}

const handleSave = () => {
  if (editedNote.value) {
    emit('save', editedNote.value)
    isEditing.value = false
  }
}

const handleTogglePin = () => {
  if (props.note) {
    emit('toggle-pin', props.note)
    // ローカルで状態を更新（UIの即座更新のため）
    props.note.is_pinned = !props.note.is_pinned
  }
}

const handleCancel = () => {
  isEditing.value = false
  if (props.note) {
    editedNote.value = { ...props.note }
  }
}

const closeDialog = () => {
    emit('update:open', false)
}

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

// Watch for deadline changes and format them
watch(() => editedNote.value?.deadline, (newDeadline) => {
  if (editedNote.value && newDeadline) {
    editedNote.value.deadline = formatDateForInput(newDeadline)
  }
})

const handleAddTag = () => {
  if (tagInput.value.trim() && editedNote.value) {
    const newTag = tagInput.value.trim()
    const existingTags = editedNote.value.tags?.map(tag => tag.tag_name) || []
    if (!existingTags.includes(newTag)) {
      if (!editedNote.value.tags) {
        editedNote.value.tags = []
      }
      editedNote.value.tags.push({ tag_name: newTag })
      tagInput.value = ''
    }
  }
}

const handleRemoveTag = (tagToRemove: string) => {
  if (editedNote.value?.tags) {
    editedNote.value.tags = editedNote.value.tags.filter(tag => tag.tag_name !== tagToRemove)
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
    lastDeletedNote.value = null
  }, 4000)
}

const handleDeleteNote = () => {
  if (!props.note) return
  
  lastDeletedNote.value = props.note
  
  router.delete(route('notes.destroy', props.note.note_id), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      emit('update:open', false)
      emit('delete', props.note!)
      // ダイアログを閉じた後にメッセージを表示
      setTimeout(() => {
        showMessage('メモを削除しました。', 'delete')
      }, 100)
    },
    onError: () => {
      lastDeletedNote.value = null
      showMessage('メモの削除に失敗しました。', 'success')
    }
  })
}

const handleUndoDelete = () => {
  if (!lastDeletedNote.value) return

  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  saveMessage.value = '元に戻しています...'
  
  const noteToRestore = lastDeletedNote.value
  lastDeletedNote.value = null

  router.post(route('notes.restore', noteToRestore.note_id), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      showMessage('メモが元に戻されました。', 'success')
    },
    onError: () => {
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  })
}

</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent v-if="currentNote" class="max-w-2xl max-h-[90vh]">
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
              variant="outline"
              size="sm"
              @click="handleTogglePin"
              :class="currentNote.is_pinned ? 'bg-yellow-50 border-yellow-300 text-yellow-700 hover:bg-yellow-100' : 'hover:bg-gray-50'"
              aria-label="ピン留めの切り替え"
              class="gap-1"
            >
              <MapPin class="h-4 w-4" :class="{ 'fill-yellow-500 text-yellow-500': currentNote.is_pinned }" />
              <span class="text-xs">{{ currentNote.is_pinned ? 'ピン解除' : 'ピン留め' }}</span>
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
            <span>{{ new Date(currentNote.updated_at || currentNote.created_at).toLocaleDateString() }}</span>
          </div>
          <div v-if="isEditing && editedNote" class="flex items-center gap-2">
            <span class="text-xs">期限:</span>
            <Input
              type="date"
              v-model="editedNote.deadline"
              class="h-7 w-40 text-xs"
              aria-label="期限日"
            />
          </div>
          <Badge v-else-if="currentNote.deadline" variant="outline" class="text-xs">
            期限: {{ currentNote.deadline }}
          </Badge>
        </div>
      </DialogHeader>

      <div v-if="isEditing && editedNote" class="space-y-3 pt-2">
        <div class="flex gap-2">
          <Select v-model="editedNote.priority">
            <SelectTrigger class="w-32 h-8 text-xs" aria-label="優先度選択">
              <div class="flex items-center gap-2">
                <Badge :class="getPriorityInfo(editedNote.priority as Priority).className" class="text-xs px-1 py-0">
                  {{ getPriorityInfo(editedNote.priority as Priority).label }}
                </Badge>
              </div>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="high">
                <Badge class="bg-red-600 text-white text-xs">重要</Badge>
              </SelectItem>
              <SelectItem value="medium">
                <Badge class="bg-yellow-500 text-white text-xs">中</Badge>
              </SelectItem>
              <SelectItem value="low">
                <Badge class="bg-gray-400 text-white text-xs">低</Badge>
              </SelectItem>
            </SelectContent>
          </Select>
          <Select v-model="editedNote.color">
            <SelectTrigger class="w-32 h-8 text-xs" aria-label="色選択">
              <div class="flex items-center gap-2">
                <div :class="['w-3 h-3 rounded', getColorInfo(editedNote.color).bg]"></div>
                <span>{{ getColorInfo(editedNote.color).label }}</span>
              </div>
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="c in ['yellow', 'blue', 'green', 'pink', 'purple']" :key="c" :value="c">
                <div class="flex items-center gap-2">
                  <div :class="['w-3 h-3 rounded', getColorInfo(c).bg]"></div>
                  <span>{{ getColorInfo(c).label }}</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
          <div class="flex gap-1">
            <Input
              placeholder="タグを追加..."
              v-model="tagInput"
              @keypress.enter.prevent="handleAddTag"
              class="h-8 text-xs flex-1 w-32"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="handleAddTag"
              class="h-8 px-2 text-xs"
            >
              追加
            </Button>
          </div>
        </div>
        <div v-if="editedNote.tags && editedNote.tags.length > 0" class="flex flex-wrap gap-1">
          <Badge v-for="tag in editedNote.tags" :key="tag.tag_name" variant="secondary" class="text-xs gap-1">
            {{ tag.tag_name }}
            <button @click="handleRemoveTag(tag.tag_name)" class="hover:bg-gray-300 rounded-full p-0.5">
              <X class="h-2 w-2" />
            </button>
          </Badge>
        </div>
      </div>

      <ScrollArea class="max-h-[60vh]">
        <div :class="[getColorClass(currentNote.color), 'border-2 rounded-lg p-6']">
          <div v-if="!isEditing && currentNote.tags && currentNote.tags.length > 0" class="flex flex-wrap gap-1 mb-3">
            <Badge v-for="tag in currentNote.tags" :key="tag.tag_name" variant="secondary" class="text-xs">
              {{ tag.tag_name }}
            </Badge>
          </div>
          <Textarea
            v-if="isEditing && editedNote"
            v-model="editedNote.content"
            class="min-h-[200px] whitespace-pre-line bg-white"
            aria-label="メモ内容"
          />
          <p v-else class="whitespace-pre-line text-gray-800">
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
          <Button variant="outline" @click="handleDeleteNote" size="sm" class="text-red-600 hover:text-red-700">
            <Trash2 class="h-4 w-4 mr-1" />
            削除
          </Button>
        </template>
      </DialogFooter>
    </DialogContent>
    
    <!-- メッセージ -->
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
            v-if="messageType === 'delete' && lastDeletedNote"
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
  </Dialog>
</template>
