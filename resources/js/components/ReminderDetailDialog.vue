<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed, watch } from 'vue'
import { Clock, CheckCircle2, Edit2, Save, X } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
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

interface Reminder {
  id: number
  title: string
  deadline: string
  category: string
  completed: boolean
  completedAt?: string
  description?: string
}

const props = defineProps<{ 
    reminder: Reminder | null,
    open: boolean 
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'update:reminder', value: Reminder): void
}>()

const isEditing = ref(false)
const editedReminder = ref<Reminder | null>(null)

// 新規作成用のデフォルトリマインダー
const createDefaultReminder = (): Reminder => ({
  id: 0,
  title: '',
  deadline: new Date().toISOString().split('T')[0],
  category: '業務',
  completed: false,
  description: ''
})

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    if (props.reminder) {
      editedReminder.value = { ...props.reminder }
      isEditing.value = false
    } else {
      // 新規作成モード
      editedReminder.value = createDefaultReminder()
      isEditing.value = true
    }
  }
})

watch(() => props.reminder, (newReminder) => {
  if (newReminder) {
    editedReminder.value = { ...newReminder }
    isEditing.value = false
  } else if (props.open) {
    // 新規作成モード
    editedReminder.value = createDefaultReminder()
    isEditing.value = true
  }
}, { deep: true })

const currentReminder = computed(() => {
  if (isEditing.value && editedReminder.value) {
    return editedReminder.value
  }
  if (props.reminder) {
    return props.reminder
  }
  // 新規作成モード
  return editedReminder.value || createDefaultReminder()
})

const isCreateMode = computed(() => !props.reminder && props.open)

const handleEdit = () => {
  if (props.reminder) {
    editedReminder.value = { ...props.reminder }
    isEditing.value = true
  }
}

const handleSave = () => {
  if (editedReminder.value) {
    if (!editedReminder.value.title.trim()) {
      return
    }
    emit('update:reminder', editedReminder.value)
  }
  isEditing.value = false
  emit('update:open', false)
}

const handleCancel = () => {
  isEditing.value = false
  if (props.reminder) {
    editedReminder.value = { ...props.reminder }
  } else {
    editedReminder.value = createDefaultReminder()
  }
  emit('update:open', false)
}

const closeDialog = () => {
  if (isEditing.value && !props.reminder) {
    // 新規作成モードでキャンセル
    editedReminder.value = createDefaultReminder()
    isEditing.value = false
  }
  emit('update:open', false)
}

</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent v-if="currentReminder" class="max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ isCreateMode ? '新規リマインダー作成' : (currentReminder.completed ? '完了済みリマインダー' : 'リマインダー詳細') }}
        </DialogTitle>
        <DialogDescription v-if="isCreateMode">
          個人リマインダーを新規作成します
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4 pt-4">
        <div class="space-y-2">
          <div class="text-sm text-gray-600">タイトル *</div>
          <Input 
            v-if="isEditing && editedReminder" 
            v-model="editedReminder.title" 
            class="h-8" 
            placeholder="タイトルを入力" 
          />
          <div v-else :class="['text-base font-medium', currentReminder.completed ? 'line-through text-gray-500' : '']">
            {{ currentReminder.title || '（タイトルなし）' }}
          </div>
        </div>

        <div v-if="isEditing && editedReminder" class="space-y-2">
          <div class="text-sm text-gray-600">カテゴリ</div>
          <Select v-model="editedReminder.category">
            <SelectTrigger class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="業務">業務</SelectItem>
              <SelectItem value="人事">人事</SelectItem>
              <SelectItem value="総務">総務</SelectItem>
              <SelectItem value="その他">その他</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div v-else class="flex items-center gap-2">
          <Badge variant="outline">{{ currentReminder.category }}</Badge>
        </div>

        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <Clock class="h-4 w-4 text-gray-600" />
          <div class="flex-1">
            <div class="text-sm text-gray-600">期限</div>
            <Input v-if="isEditing && editedReminder" type="date" v-model="editedReminder.deadline" class="h-8 mt-1" />
            <div v-else :class="[currentReminder.completed ? 'text-gray-400' : '']">
              {{ formatDate(currentReminder.deadline) }}
            </div>
          </div>
        </div>

        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <h4 class="text-sm mb-2">詳細</h4>
          <Textarea v-if="isEditing && editedReminder" v-model="editedReminder.description" class="min-h-[80px] bg-white" placeholder="詳細を入力..." />
          <p v-else :class="['text-sm', currentReminder.completed ? 'text-gray-500' : 'text-gray-700']">
            {{ currentReminder.description || '詳細なし' }}
          </p>
        </div>

        <div v-if="!isCreateMode && currentReminder.completed && currentReminder.completedAt" class="p-4 bg-green-50 border border-green-200 rounded-lg">
          <div class="flex items-center gap-2 text-green-700">
            <CheckCircle2 class="h-4 w-4" />
            <span class="text-sm">
              完了日時: {{ formatDate(currentReminder.completedAt) }}
            </span>
          </div>
          <p class="text-xs text-green-600 mt-2">
            ※ 次回ログイン時にゴミ箱へ移動します
          </p>
        </div>

        <div v-if="!isCreateMode" class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
          <span class="text-sm text-gray-600">ステータス</span>
          <Badge :variant="currentReminder.completed ? 'secondary' : 'default'">
            {{ currentReminder.completed ? '完了' : '未完了' }}
          </Badge>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <template v-if="isEditing">
          <Button variant="outline" @click="handleCancel" size="sm">
            <X class="h-4 w-4 mr-1" />
            キャンセル
          </Button>
          <Button @click="handleSave" size="sm">
            <Save class="h-4 w-4 mr-1" />
            {{ isCreateMode ? '作成' : '保存' }}
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
