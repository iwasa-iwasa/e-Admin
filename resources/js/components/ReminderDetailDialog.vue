<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed, watch } from 'vue'
import { Clock, CheckCircle2, Edit2, Save, X, CheckCircle } from 'lucide-vue-next'
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
import { useForm } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'

interface Reminder {
  id?: number
  reminder_id?: number
  title: string
  deadline: string
  category: string
  completed: boolean
  completedAt?: string
  completed_at?: string
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
const saveMessage = ref('')
const messageTimer = ref<number | null>(null)

const showMessage = (message: string) => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 4000)
}

// Inertiaフォーム
const form = useForm({
  title: '',
  description: '',
  deadline: '',
  category: '業務',
  completed: false,
})

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
      // フォームを既存のリマインダーデータで初期化
      form.reset()
      form.title = props.reminder.title
      form.description = props.reminder.description || ''
      // deadlineの形式を確認（Dateオブジェクトの場合は文字列に変換）
      const deadline = props.reminder.deadline
      form.deadline = typeof deadline === 'string' ? deadline.split('T')[0] : deadline
      form.category = props.reminder.category
      form.completed = props.reminder.completed || false
    } else {
      // 新規作成モード
      editedReminder.value = createDefaultReminder()
      isEditing.value = true
      // フォームをデフォルト値で初期化
      form.reset()
      form.title = ''
      form.description = ''
      form.deadline = new Date().toISOString().split('T')[0]
      form.category = '業務'
      form.completed = false
    }
  }
})

watch(() => props.reminder, (newReminder) => {
  if (newReminder) {
    editedReminder.value = { ...newReminder }
    isEditing.value = false
    form.reset()
    form.title = newReminder.title
    form.description = newReminder.description || ''
    // deadlineの形式を確認
    const deadline = newReminder.deadline
    form.deadline = typeof deadline === 'string' ? deadline.split('T')[0] : deadline
    form.category = newReminder.category
    form.completed = newReminder.completed || false
  } else if (props.open) {
    // 新規作成モード
    editedReminder.value = createDefaultReminder()
    isEditing.value = true
    form.reset()
    form.title = ''
    form.description = ''
    form.deadline = new Date().toISOString().split('T')[0]
    form.category = '業務'
    form.completed = false
  }
}, { deep: true })

const currentReminder = computed(() => {
  // 編集モードの場合は、フォームの値ではなく既存のリマインダーまたは編集前の値を表示
  if (props.reminder && !isEditing.value) {
    return props.reminder
  }
  // 編集モードまたは新規作成モードの場合
  if (isEditing.value && editedReminder.value) {
    return editedReminder.value
  }
  // フォールバック
  return editedReminder.value || props.reminder || createDefaultReminder()
})

const isCreateMode = computed(() => !props.reminder && props.open)

const handleEdit = () => {
  if (props.reminder) {
    editedReminder.value = { ...props.reminder }
    isEditing.value = true
    // フォームを既存のリマインダーデータで初期化
    form.reset()
    form.title = props.reminder.title
    form.description = props.reminder.description || ''
    form.deadline = props.reminder.deadline
    form.category = props.reminder.category
    form.completed = props.reminder.completed
  }
}

const handleSave = () => {
  if (!form.title.trim()) {
    return
  }

  if (isCreateMode.value) {
    // 新規作成
    form.post(route('reminders.store'), {
      preserveScroll: true,
      onSuccess: () => {
        showMessage('リマインダーを正常に作成しました。')
        // ダミーリマインダーを作成して親コンポーネントに通知
        const dummyReminder = {
          reminder_id: Date.now(),
          title: form.title,
          description: form.description,
          deadline: form.deadline,
          category: form.category,
          completed: false
        }
        emit('update:reminder', dummyReminder)
        setTimeout(() => {
          emit('update:open', false)
          form.reset()
          editedReminder.value = createDefaultReminder()
          isEditing.value = false
        }, 1000)
      },
      onError: () => {
        showMessage('リマインダーの作成に失敗しました。')
      }
    })
  } else if (props.reminder) {
    // 更新 - reminder_idまたはidのどちらかを使用
    const reminderId = props.reminder.reminder_id || props.reminder.id
    if (reminderId) {
      form.put(route('reminders.update', reminderId), {
        preserveScroll: true,
        onSuccess: () => {
          showMessage('リマインダーを更新しました。')
          // 更新されたリマインダーを親コンポーネントに通知
          const updatedReminder = {
            ...props.reminder,
            title: form.title,
            description: form.description,
            deadline: form.deadline,
            category: form.category
          }
          emit('update:reminder', updatedReminder)
          setTimeout(() => {
            emit('update:open', false)
            isEditing.value = false
          }, 2500)
        },
        onError: () => {
          showMessage('リマインダーの更新に失敗しました。')
        }
      })
    }
  }
}

const handleCancel = () => {
  isEditing.value = false
  form.reset()
  if (props.reminder) {
    editedReminder.value = { ...props.reminder }
    form.title = props.reminder.title
    form.description = props.reminder.description || ''
    form.deadline = props.reminder.deadline
    form.category = props.reminder.category
    form.completed = props.reminder.completed
  } else {
    editedReminder.value = createDefaultReminder()
  }
  emit('update:open', false)
}

const closeDialog = (isOpen: boolean) => {
  if (!isOpen) {
    if (isEditing.value && !props.reminder) {
      // 新規作成モードでキャンセル
      editedReminder.value = createDefaultReminder()
      isEditing.value = false
      form.reset()
    }
    emit('update:open', false)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ isCreateMode ? '新規リマインダー作成' : (props.reminder?.completed ? '完了済みリマインダー' : 'リマインダー詳細') }}
        </DialogTitle>
        <DialogDescription v-if="isCreateMode">
          個人リマインダーを新規作成します
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="handleSave">
        <div class="space-y-4 pt-4">
          <div class="space-y-2">
            <div class="text-sm text-gray-600">タイトル *</div>
            <Input 
              v-if="isEditing" 
              v-model="form.title" 
              class="h-8" 
              placeholder="タイトルを入力"
              :class="{ 'border-red-500': form.errors.title }"
            />
            <div v-if="form.errors.title" class="text-xs text-red-500 mt-1">
              {{ form.errors.title }}
            </div>
          </div>

          <div v-if="isEditing" class="space-y-2">
            <div class="text-sm text-gray-600">カテゴリ</div>
            <Select v-model="form.category">
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
            <Badge variant="outline">{{ props.reminder?.category || '業務' }}</Badge>
          </div>

          <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
            <Clock class="h-4 w-4 text-gray-600" />
            <div class="flex-1">
              <div class="text-sm text-gray-600">期限</div>
              <Input 
                v-if="isEditing" 
                type="date" 
                v-model="form.deadline" 
                class="h-8 mt-1"
                :class="{ 'border-red-500': form.errors.deadline }"
              />
              <div v-if="form.errors.deadline" class="text-xs text-red-500 mt-1">
                {{ form.errors.deadline }}
              </div>
              <div v-else :class="[(props.reminder?.completed || false) ? 'text-gray-400' : '']">
                {{ props.reminder ? formatDate(props.reminder.deadline) : '' }}
              </div>
            </div>
          </div>

          <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h4 class="text-sm mb-2">詳細</h4>
            <Textarea 
              v-if="isEditing" 
              v-model="form.description" 
              class="min-h-[80px] bg-white" 
              placeholder="詳細を入力..."
              :class="{ 'border-red-500': form.errors.description }"
            />
            <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">
              {{ form.errors.description }}
            </div>
          </div>

          <div v-if="!isCreateMode && props.reminder?.completed && (props.reminder.completedAt || props.reminder.completed_at)" class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2 text-green-700">
              <CheckCircle2 class="h-4 w-4" />
              <span class="text-sm">
                完了日時: {{ formatDate(props.reminder.completedAt || props.reminder.completed_at) }}
              </span>
            </div>
            <p class="text-xs text-green-600 mt-2">
              ※ 次回ログイン時にゴミ箱へ移動します
            </p>
          </div>

          <div v-if="!isCreateMode && props.reminder" class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
            <span class="text-sm text-gray-600">ステータス</span>
            <Badge :variant="props.reminder.completed ? 'secondary' : 'default'">
              {{ props.reminder.completed ? '完了' : '未完了' }}
            </Badge>
          </div>
        </div>

        <DialogFooter class="gap-2 mt-6">
          <template v-if="isEditing">
            <Button 
              type="button"
              variant="outline" 
              @click="handleCancel" 
              size="sm"
              :disabled="form.processing"
            >
              <X class="h-4 w-4 mr-1" />
              キャンセル
            </Button>
            <Button 
              type="submit"
              variant="outline"
              size="sm"
              :disabled="form.processing"
            >
              <Save class="h-4 w-4 mr-1" />
              {{ form.processing ? '保存中...' : (isCreateMode ? '作成' : '保存') }}
            </Button>
          </template>
          <template v-else>
            <Button variant="outline" @click="handleEdit" size="sm">
              <Edit2 class="h-4 w-4 mr-1" />
              編集
            </Button>
          </template>
        </DialogFooter>
      </form>
    </DialogContent>
    
    <!-- 下部メッセージ -->
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
        class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[60] p-3 text-white rounded-lg shadow-lg bg-green-500"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
        </div>
      </div>
    </Transition>
  </Dialog>
</template>
