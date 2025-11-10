<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { User, Clock, Edit2, Save, X } from 'lucide-vue-next'
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

const props = defineProps<{ 
    note: Note | null,
    open: boolean 
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'update:note', value: Note): void
}>()

const isEditing = ref(false)
const editedNote = ref<Note | null>(null)

watch(() => props.note, (newNote) => {
  if (newNote) {
    editedNote.value = { ...newNote }
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
    editedNote.value = { ...props.note }
    isEditing.value = true
  }
}

const handleSave = () => {
  if (editedNote.value) {
    emit('update:note', editedNote.value)
  }
  isEditing.value = false
  emit('update:open', false)
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

</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent v-if="currentNote" class="max-w-2xl max-h-[90vh]">
      <DialogHeader>
        <div class="flex items-start justify-between gap-4">
          <DialogTitle class="flex-1">
            <Input
              v-if="isEditing"
              v-model="editedNote.title"
              class="h-8"
            />
            <template v-else>{{ currentNote.title }}</template>
          </DialogTitle>
          <Badge :class="getPriorityInfo(currentNote.priority).className">
            {{ getPriorityInfo(currentNote.priority).label }}
          </Badge>
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600 pt-2">
          <div class="flex items-center gap-1">
            <User class="h-4 w-4" />
            <span>{{ currentNote.author }}</span>
          </div>
          <div class="flex items-center gap-1">
            <Clock class="h-4 w-4" />
            <span>{{ currentNote.date }}</span>
          </div>
          <div v-if="isEditing" class="flex items-center gap-2">
            <span class="text-xs">期限:</span>
            <Input
              type="date"
              v-model="editedNote.deadline"
              class="h-7 w-40 text-xs"
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
            <SelectTrigger class="w-32 h-8 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="high">重要</SelectItem>
              <SelectItem value="medium">中</SelectItem>
              <SelectItem value="low">低</SelectItem>
            </SelectContent>
          </Select>
          <Select v-model="editedNote.color">
            <SelectTrigger class="w-32 h-8 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="yellow">黄色</SelectItem>
              <SelectItem value="blue">青色</SelectItem>
              <SelectItem value="green">緑色</SelectItem>
              <SelectItem value="pink">ピンク</SelectItem>
              <SelectItem value="purple">紫色</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <ScrollArea class="max-h-[60vh]">
        <div :class="[getColorClass(currentNote.color), 'border-2 rounded-lg p-6']">
          <Textarea
            v-if="isEditing && editedNote"
            v-model="editedNote.content"
            class="min-h-[200px] whitespace-pre-line bg-white"
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
        </template>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
