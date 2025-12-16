<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { User, Clock, Edit2, Save, X, MapPin, Trash2, CheckCircle, Undo2 } from 'lucide-vue-next'
import { router, usePage } from '@inertiajs/vue3'
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
  teamMembers?: App.Models.User[]
  totalUsers?: number
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
const participantSelectValue = ref<string | null>(null)
const tagInput = ref('')
const saveMessage = ref('')
const currentUserId = computed(() => (usePage().props as any).auth?.user?.id ?? null)

const isAllUsers = (participants: any[]) => {
  return participants && props.totalUsers && participants.length === props.totalUsers
}

// 編集権限チェック
const canEdit = computed(() => {
  if (!props.note) return false
  const note = props.note
  const isCreator = note.author?.id === currentUserId.value
  
  // 参加者が空：作成者のみ編集可能
  if (!note.participants || note.participants.length === 0) {
    return isCreator
  }
  
  // 全員が参加者：全員編集可能
  if (props.totalUsers && note.participants.length === props.totalUsers) {
    return true
  }
  
  // 個人指定：作成者または参加者のみ編集可能
  const isParticipant = note.participants.some(p => p.id === currentUserId.value)
  return isCreator || isParticipant
})

const canEditParticipants = computed(() => {
  if (!props.note) return false
  const isCreator = props.note.author?.id === currentUserId.value
  if (isAllUsers(props.note.participants || [])) return isCreator // 全員共有は作成者のみ変更可能
  const isParticipant = props.note.participants?.some(p => p.id === currentUserId.value)
  return isCreator || isParticipant
})
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedNote = ref<App.Models.SharedNote | null>(null)

watch(() => props.note, (newNote) => {
  if (newNote) {
    editedNote.value = { 
      ...newNote,
      deadline: formatDateTimeForInput(newNote.deadline_date, newNote.deadline_time)
    }
  } else {
    editedNote.value = null
  }
  isEditing.value = false
  participantSelectValue.value = null
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
    blue: { bg: 'bg-blue-100', label: 'ブルー' },
    green: { bg: 'bg-green-100', label: 'グリーン' },
    yellow: { bg: 'bg-yellow-100', label: 'オレンジ' },
    purple: { bg: 'bg-purple-100', label: 'パープル' },
    pink: { bg: 'bg-pink-100', label: 'ピンク' },
  }
  return colorMap[c] || colorMap.yellow
}

const handleEdit = () => {
  if (props.note) {
    editedNote.value = { 
      ...props.note,
      deadline: formatDateTimeForInput(props.note.deadline_date, props.note.deadline_time)
    }
    isEditing.value = true
  }
}

const handleConfirm = () => {
  closeDialog()
  setTimeout(() => {
    showMessage('確認しました', 'success')
  }, 100)
}

const handleSave = () => {
  if (!editedNote.value) return
  
  const updateData = {
    title: editedNote.value.title,
    content: editedNote.value.content,
    deadline: editedNote.value.deadline || null,
    priority: editedNote.value.priority,
    color: editedNote.value.color,
    tags: editedNote.value.tags?.map(tag => tag.tag_name) || [],
    participants: editedNote.value.participants?.map(p => p.id) || []
  }
  
  router.put(route('shared-notes.update', editedNote.value.note_id), updateData, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      emit('save', editedNote.value!)
      isEditing.value = false
      closeDialog()
      setTimeout(() => {
        showMessage('メモが保存されました。', 'success')
      }, 100)
    },
    onError: () => {
      showMessage('保存に失敗しました。', 'success')
    }
  })
}

const handleTogglePin = () => {
  if (props.note) {
    emit('toggle-pin', props.note)
    closeDialog()
  }
}

const handleCancel = () => {
  if (isEditing.value) {
    isEditing.value = false
    if (props.note) {
      editedNote.value = { ...props.note }
    }
  } else {
    closeDialog()
  }
}

const closeDialog = () => {
    emit('update:open', false)
}

// Format datetime for input[type="datetime-local"] from deadline_date and deadline_time
const formatDateTimeForInput = (deadlineDate: string | null | undefined, deadlineTime: string | null | undefined): string => {
  if (!deadlineDate) return ''
  
  const time = deadlineTime ? deadlineTime.substring(0, 5) : '23:59' // HH:mm format
  return `${deadlineDate}T${time}`
}

// Watch for deadline changes and format them
watch(() => editedNote.value?.deadline, (newDeadline) => {
  if (editedNote.value && newDeadline) {
    const [date, time] = newDeadline.split('T')
    editedNote.value.deadline_date = date
    editedNote.value.deadline_time = time ? `${time}:00` : '23:59:00'
  }
})

const editedDeadline = computed({
  get: (): string => {
    // Ensure the input always receives a string (empty when null)
    return editedNote.value?.deadline ?? ''
  },
  set: (val: string) => {
    // Convert empty string back to null for the note model
    if (!editedNote.value) return
    editedNote.value.deadline = val === '' ? null : val
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
      editedNote.value.tags.push({ tag_id: 0, tag_name: newTag })
      tagInput.value = ''
    }
  }
}

const handleRemoveTag = (tagToRemove: string) => {
  if (editedNote.value?.tags) {
    editedNote.value.tags = editedNote.value.tags.filter(tag => tag.tag_name !== tagToRemove)
  }
}

const handleAddParticipant = (memberId: unknown) => {
  if (memberId === null || memberId === undefined || !editedNote.value) return
  const id = Number(memberId as any)
  if (Number.isNaN(id)) return
  const member = props.teamMembers?.find((m) => m.id === id)
  if (member) {
    if (!editedNote.value.participants) {
      editedNote.value.participants = []
    }
    if (!editedNote.value.participants.find((p) => p.id === member.id)) {
      editedNote.value.participants = [...editedNote.value.participants, member]
    }
  }
}

const handleRemoveParticipant = (participantId: number) => {
  if (editedNote.value?.participants) {
    editedNote.value.participants = editedNote.value.participants.filter((p) => p.id !== participantId)
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

const formatDate = (dateString: string | null | undefined): string => {
  if (!dateString) return ''
  try {
    return new Date(dateString).toLocaleDateString()
  } catch {
    return ''
  }
}

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
  <Dialog :open="open" @update:open="closeDialog" :modal="true">
    <DialogContent v-if="currentNote" class="max-w-2xl max-h-[90vh]" @pointerDownOutside.prevent @interactOutside.prevent>
      <DialogHeader>
        <div class="flex flex-col items-startgap-4">
          <div class="flex items-center  justify-between ">
            <DialogTitle class="flex-1">
              <Input
                v-if="isEditing && editedNote"
                v-model="editedNote.title"
                :disabled="!canEdit"
                class="h-8"
                aria-label="メモタイトル"
              />
              <template v-else>{{ currentNote.title }}</template>
            </DialogTitle>
            <div class="flex items-center gap-2 ">
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
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600 pt-2">
          <div class="flex items-center gap-1">
            <User class="h-4 w-4" />
            <span>{{ currentNote.author?.name || 'N/A' }}</span>
          </div>
          <div v-if="currentNote.participants && currentNote.participants.length > 0" class="flex items-center gap-1">
            <Badge v-if="isAllUsers(currentNote.participants)" variant="secondary" class="text-xs px-1 py-0">
              全員
            </Badge>
            <template v-else>
              <Badge v-for="participant in currentNote.participants.slice(0, 2)" :key="participant.id" variant="secondary" class="text-xs px-1 py-0">
                {{ participant.name }}
              </Badge>
              <Badge v-if="currentNote.participants.length > 2" variant="secondary" class="text-xs px-1 py-0">
                +{{ currentNote.participants.length - 2 }}
              </Badge>
            </template>
          </div>
          <div class="flex items-center gap-1">
            <Clock class="h-4 w-4" />
            <span>{{ new Date(currentNote.updated_at || currentNote.created_at).toLocaleDateString() }}</span>
          </div>
          <div v-if="isEditing && editedNote" class="flex items-center gap-2">
            <span class="text-xs">期限:</span>
            <Input
              type="datetime-local"
              v-model="editedNote.deadline"
              :disabled="!canEdit"
              class="h-7 w-48 text-xs"
              aria-label="期限日時"
            />
            <div class="flex items-center gap-2">
              <span class="text-xs whitespace-nowrap">進捗 ({{ editedNote.progress || 0 }}%):</span>
              <div class="relative w-24">
                <div 
                  class="w-full h-2 rounded-lg overflow-hidden"
                  :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${editedNote.progress || 0}%, #e5e7eb ${editedNote.progress || 0}%, #e5e7eb 100%)` }"
                >
                </div>
                <input 
                  type="range" 
                  min="0" 
                  max="100" 
                  v-model.number="editedNote.progress"
                  :disabled="!canEdit"
                  class="w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider absolute top-0"
                />
              </div>
            </div>
          </div>
          <div v-else class="flex items-center gap-2">
            <Badge variant="outline" class="text-xs">
              {{ currentNote.deadline_date ? '期限' : '作成日' }}: {{ currentNote.deadline_date ? `${new Date(currentNote.deadline_date).toLocaleDateString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).replace(/\//g, '-')} ${(currentNote.deadline_time || '23:59:00').substring(0, 5)}` : new Date(currentNote.created_at).toLocaleString('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(/\//g, '-') }}
            </Badge>
          </div>
        </div>
      </DialogHeader>

      <div v-if="isEditing && editedNote" class="space-y-3 pt-2">
        <div class="flex gap-2">
          <Select v-model="editedNote.priority" :disabled="!canEdit">
            <SelectTrigger class="w-32 h-8 text-xs" aria-label="重要度選択">
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
          <Select v-model="editedNote.color" :disabled="!canEdit">
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
          <div v-if="canEdit" class="flex gap-1">
            <Input
              placeholder="タグを追加"
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
            <button v-if="canEdit" @click="handleRemoveTag(tag.tag_name)" class="hover:bg-gray-300 rounded-full p-0.5">
              <X class="h-2 w-2" />
            </button>
          </Badge>
        </div>
        <!-- 参加者編集UI -->
        <div v-if="isEditing && editedNote" class="space-y-2 mt-3">
          <label class="text-xs font-medium text-gray-700 block">共有範囲</label>
          <div class="text-xs text-gray-600 p-2 bg-gray-50 rounded border">
            💡 メンバーを選択すると、選択したメンバーと自分のみに表示されます。選択しない場合は全員に表示されます。
          </div>
          <template v-if="!canEditParticipants">
            <div class="text-xs text-gray-500 p-2 bg-gray-50 rounded border">
              共有メンバーの変更は作成者または参加者のみ可能です
            </div>
          </template>
          <template v-else-if="isAllUsers(editedNote.participants || []) && editedNote.author?.id !== currentUserId">
            <div class="text-xs text-gray-500 p-2 bg-gray-50 rounded border">
              全員共有のメモは作成者のみが共有設定を変更できます
            </div>
          </template>
          <template v-else>
            <div v-if="editedNote?.participants?.length === props.totalUsers" class="text-xs text-blue-600 p-2 bg-blue-50 rounded border">
              全員が選択されています。変更するにはメンバーを削除してください。
            </div>
            <div v-else class="max-h-[200px] overflow-y-auto border rounded p-2 space-y-1">
              <label v-for="member in props.teamMembers?.filter(m => m.id !== editedNote?.author?.id)" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-gray-50 rounded cursor-pointer">
                <input 
                  type="checkbox" 
                  :checked="editedNote?.participants?.find(p => p.id === member.id) !== undefined"
                  @change="(e) => (e.target as HTMLInputElement).checked ? handleAddParticipant(member.id) : handleRemoveParticipant(member.id)"
                  class="h-4 w-4 text-blue-600 rounded border-gray-300"
                />
                <span class="text-xs">{{ member.name }}</span>
              </label>
            </div>
          </template>
          <div v-if="editedNote.participants && editedNote.participants.length > 0" class="min-h-[60px] p-3 border border-purple-300 rounded-md bg-purple-50">
            <div class="text-xs font-medium text-purple-800 mb-2">🔒 限定公開: 選択されたメンバーと自分のみ表示</div>
            <div class="flex flex-wrap gap-1">
              <Badge v-for="participant in editedNote.participants" :key="participant.id" variant="secondary" class="text-xs gap-1">
                {{ participant.name }}
                <button v-if="canEdit && canEditParticipants && !(isAllUsers(editedNote.participants || []) && editedNote.author?.id !== currentUserId)" @click="handleRemoveParticipant(participant.id)" class="hover:bg-gray-300 rounded-full p-0.5">
                  <X class="h-2 w-2" />
                </button>
              </Badge>
            </div>
          </div>
          <div v-else class="min-h-[40px] p-3 border border-input rounded-md bg-blue-50 text-blue-700 text-sm">
            🌐 全体公開: 全員に表示されます
          </div>
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
            v-model="editedContent"
            :disabled="!canEdit"
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
          <Button variant="outline" @click="closeDialog" size="sm">
            <X class="h-4 w-4 mr-1" />
            閉じる
          </Button>
          <Button v-if="canEdit" variant="outline" @click="handleSave" size="sm">
            <Save class="h-4 w-4 mr-1" />
            保存
          </Button>
          <Button v-else variant="outline" @click="handleConfirm" size="sm">
            <CheckCircle class="h-4 w-4 mr-1" />
            確認完了
          </Button>
        </template>
        <template v-else>
          <Button variant="outline" @click="closeDialog" size="sm">
            閉じる
          </Button>
          <Button variant="outline" @click="handleEdit" size="sm">
            <Edit2 class="h-4 w-4 mr-1" />
            {{ canEdit ? '編集' : '確認' }}
          </Button>
          <Button v-if="canEdit" variant="outline" @click="handleDeleteNote" size="sm" class="text-red-600 hover:text-red-700">
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
