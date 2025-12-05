<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed, watch } from 'vue'
import { Clock, CheckCircle2, Edit2, Save, X, CheckCircle, Tag, Plus as PlusIcon } from 'lucide-vue-next'
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
  id?: number;
  reminder_id: number;
  user_id: number;
  title: string;
  description: string | null;
  deadline?: string;
  deadline_date: string | null;
  deadline_time: string | null;
  category: string;
  completed: boolean;
  completed_at: string | null;
  is_deleted: boolean;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
  completedAt?: string;
}

const props = defineProps<{ 
    reminder: Reminder | null,
    open: boolean 
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean, completed?: boolean): void
  (e: 'update:reminder', value: Reminder): void
}>()

const isEditing = ref(false)
const editedReminder = ref<Reminder | null>(null)
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const isSaving = ref(false)

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 4000)
}

// Inertiaフォーム
const form = useForm({
  title: '',
  description: '',
  deadline: '',
  tags: [] as string[],
})

const newTag = ref('')

const addTag = () => {
  const tag = newTag.value.trim()
  if (tag && !form.tags.includes(tag)) {
    form.tags.push(tag)
    newTag.value = ''
  }
}

const removeTag = (tag: string) => {
  form.tags = form.tags.filter(t => t !== tag)
}

// Format datetime for input[type="datetime-local"] from deadline_date and deadline_time
const formatDateTimeForInput = (deadlineDate: string | null | undefined, deadlineTime: string | null | undefined): string => {
  if (!deadlineDate) return ''
  const time = deadlineTime ? deadlineTime.substring(0, 5) : '23:59'
  return `${deadlineDate}T${time}`
}

// 新規作成用のデフォルトリマインダー
const createDefaultReminder = (): Reminder => ({
  reminder_id: 0,
  user_id: 0,
  title: '',
  description: '',
  deadline_date: null,
  deadline_time: null,
  category: '業務',
  completed: false,
  completed_at: null,
  is_deleted: false,
  created_at: new Date().toISOString(),
  updated_at: new Date().toISOString(),
  deleted_at: null,
})

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    isSaving.value = false
    if (props.reminder) {
      editedReminder.value = { ...props.reminder }
      isEditing.value = false
      // フォームを既存のリマインダーデータで初期化
      form.reset()
      form.title = props.reminder.title
      form.description = props.reminder.description || ''
      form.deadline = formatDateTimeForInput(props.reminder.deadline_date, props.reminder.deadline_time)
      form.tags = props.reminder.tags?.map((t: any) => t.tag_name) || []
    } else {
      // 新規作成モード
      editedReminder.value = createDefaultReminder()
      isEditing.value = true
      // フォームをデフォルト値で初期化
      form.reset()
      form.title = ''
      form.description = ''
      form.deadline = ''
      form.tags = []
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
    form.deadline = formatDateTimeForInput(newReminder.deadline_date, newReminder.deadline_time)
    form.tags = newReminder.tags?.map((t: any) => t.tag_name) || []
  } else if (props.open) {
    // 新規作成モード
    editedReminder.value = createDefaultReminder()
    isEditing.value = true
    form.reset()
    form.title = ''
    form.description = ''
    form.deadline = ''
    form.tags = []
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
    form.title = props.reminder.title
    form.description = props.reminder.description || ''
    form.deadline = formatDateTimeForInput(props.reminder.deadline_date, props.reminder.deadline_time)
    form.tags = props.reminder.tags?.map((t: any) => t.tag_name) || []
  }
}

const handleSave = () => {
  if (!form.title.trim()) {
    return
  }

  // deadlineが空文字列の場合はnullに変換
  if (form.deadline === '') {
    form.deadline = null as any
  }
  
  console.log('Saving reminder with tags:', form.tags)
  
  isSaving.value = true

  if (isCreateMode.value) {
    // 新規作成
    form.post(route('reminders.store'), {
      preserveScroll: true,
      onSuccess: () => {
        emit('update:open', false)
      },
      onError: () => {
        showMessage('リマインダーの作成に失敗しました。', 'success')
        isSaving.value = false
      }
    })
  } else if (props.reminder) {
    // 更新 - reminder_idまたはidのどちらかを使用
    const reminderId = props.reminder.reminder_id || props.reminder.id
    if (reminderId) {
      form.put(route('reminders.update', reminderId), {
        preserveScroll: true,
        onSuccess: () => {
          emit('update:open', false)
        },
        onError: () => {
          showMessage('リマインダーの更新に失敗しました。', 'success')
          isSaving.value = false
        }
      })
    }
  }
}

const handleCancel = () => {
  isEditing.value = false
  isSaving.value = false
  form.reset()
  if (props.reminder) {
    editedReminder.value = { ...props.reminder }
    form.title = props.reminder.title
    form.description = props.reminder.description || ''
    form.deadline = formatDateTimeForInput(props.reminder.deadline_date, props.reminder.deadline_time)
    form.tags = props.reminder.tags?.map((t: any) => t.tag_name) || []
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
    isSaving.value = false
    emit('update:open', false)
  }
}

const handleComplete = () => {
  if (!props.reminder) return
  
  router.patch(route('reminders.complete', props.reminder.reminder_id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      emit('update:open', false, true)
    },
    onError: () => {
      showMessage('リマインダーの完了に失敗しました。', 'success')
    }
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="closeDialog">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ isCreateMode ? '新規リマインダー作成' : (props.reminder?.completed ? '完了済リマインダー' : 'リマインダー詳細') }}
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
            <div v-else class="text-base font-medium">
              {{ props.reminder?.title }}
            </div>
            <div v-if="form.errors.title" class="text-xs text-red-500 mt-1">
              {{ form.errors.title }}
            </div>
          </div>

          <div class="space-y-2">
            <div class="text-sm text-gray-600 flex items-center gap-2">
              <Tag class="h-4 w-4" />
              タグ
            </div>
            <div v-if="isEditing" class="space-y-2">
              <div class="flex gap-2">
                <Input 
                  v-model="newTag" 
                  placeholder="タグを追加"
                  class="h-8 flex-1"
                  @keydown.enter.prevent="addTag"
                />
                <Button 
                  type="button"
                  variant="outline" 
                  size="sm"
                  @click="addTag"
                  class="h-8 px-2 text-xs"
                >
                  追加
                </Button>
              </div>
              <div v-if="form.tags && form.tags.length > 0" class="flex flex-wrap gap-1">
                <Badge 
                  v-for="tag in form.tags" 
                  :key="tag" 
                  variant="secondary"
                  class="text-xs gap-1"
                >
                  {{ tag }}
                  <button @click="removeTag(tag)" class="hover:bg-gray-300 rounded-full p-0.5">
                    <X class="h-2 w-2" />
                  </button>
                </Badge>
              </div>
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <Badge 
                v-for="tag in props.reminder?.tags" 
                :key="tag.tag_id" 
                variant="secondary"
              >
                {{ tag.tag_name }}
              </Badge>
              <span v-if="!props.reminder?.tags || props.reminder.tags.length === 0" class="text-sm text-gray-400">
                タグなし
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
            <Clock class="h-4 w-4 text-gray-600" />
            <div class="flex-1">
              <div class="text-sm text-gray-600">期限（任意）</div>
              <Input 
                v-if="isEditing" 
                type="datetime-local" 
                v-model="form.deadline" 
                class="h-8 mt-1"
                placeholder="期限を設定（任意）"
                :class="{ 'border-red-500': form.errors.deadline }"
              />
              <div v-if="form.errors.deadline" class="text-xs text-red-500 mt-1">
                {{ form.errors.deadline }}
              </div>
              <div v-else :class="[(props.reminder?.completed || false) ? 'text-gray-400' : '']">
                {{ props.reminder?.deadline_date ? `${formatDate(props.reminder.deadline_date)} ${props.reminder.deadline_time ? props.reminder.deadline_time.substring(0, 5) : ''}` : '期限なし' }}
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
            <div v-else class="text-sm whitespace-pre-wrap">
              {{ props.reminder?.description || '詳細なし' }}
            </div>
            <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">
              {{ form.errors.description }}
            </div>
          </div>

          <div v-if="!isCreateMode && props.reminder?.completed && (props.reminder.completedAt || props.reminder.completed_at)" class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2 text-green-700">
              <CheckCircle2 class="h-4 w-4" />
              <span class="text-sm">
                完了日時: {{ props.reminder.completedAt ? formatDate(props.reminder.completedAt) : (props.reminder.completed_at ? formatDate(props.reminder.completed_at) : '') }}
              </span>
            </div>
            <p class="text-xs text-green-600 mt-2">
              ※ 次回ログイン時にゴミ箱へ移動します
            </p>
          </div>

          <div v-if="!isCreateMode && props.reminder" class="flex items-center justify-between p-3 border border-gray-300 rounded-lg">
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
              :disabled="form.processing || isSaving"
            >
              <X class="h-4 w-4 mr-1" />
              キャンセル
            </Button>
            <Button 
              type="submit"
              variant="outline"
              size="sm"
              :disabled="form.processing || isSaving"
            >
              <Save class="h-4 w-4 mr-1" />
              {{ (form.processing || isSaving) ? '保存中...' : (isCreateMode ? '作成' : '保存') }}
            </Button>
          </template>
          <template v-else>
            <Button 
              v-if="!isCreateMode && props.reminder && !props.reminder.completed"
              type="button"
              variant="outline" 
              @click="handleComplete" 
              size="sm"
              class="bg-green-600 text-white border-green-600 hover:bg-green-700 hover:border-green-700"
            >
              <CheckCircle class="h-4 w-4 mr-1" />
              完了
            </Button>
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[60] p-3 text-white rounded-lg shadow-lg',
          messageType === 'delete' ? 'bg-blue-500' : 'bg-green-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
        </div>
      </div>
    </Transition>
  </Dialog>
</template>
