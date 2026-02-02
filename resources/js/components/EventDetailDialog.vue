<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { formatDate } from '@/lib/utils'
import { Calendar as CalendarIcon, Users, MapPin, Info, Link as LinkIcon, Paperclip, Repeat, Trash2, CheckCircle, Undo2, Clock, User, Tag, AlertCircle, Save, X } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { router, usePage, useForm } from '@inertiajs/vue3'
import RecurrenceEditDialog from '@/components/RecurrenceEditDialog.vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { TimePicker } from 'vue-material-time-picker'
import { ja } from 'date-fns/locale'
import '@vuepic/vue-datepicker/dist/main.css'
import 'vue-material-time-picker/dist/style.css'

const props = defineProps<{ 
    event: App.Models.ExpandedEvent | null,
    open: boolean 
}>()
const emit = defineEmits(['update:open', 'edit'])

const page = usePage()
const currentUserId = computed(() => (page.props as any).auth?.user?.id ?? null)
const teamMembers = computed(() => (page.props as any).teamMembers || [])

// 編集権限チェック
const canEdit = computed(() => {
  if (!props.event) return false
  const event = props.event
  const isCreator = event.created_by === currentUserId.value
  
  // 作成者は常に編集可能
  if (isCreator) return true
  
  // 全員が参加者：全員編集可能
  if (Array.isArray(teamMembers.value) && teamMembers.value.length > 0 && event.participants && event.participants.length === teamMembers.value.length) {
    return true
  }
  
  // 参加者のみ編集可能
  const isParticipant = event.participants?.some(p => p.id === currentUserId.value)
  return isParticipant || false
})


const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedEvent = ref<App.Models.ExpandedEvent | null>(null)
const showRecurrenceDialog = ref(false)
const recurrenceMode = ref<'edit' | 'delete'>('delete')
const isEditMode = ref(false)

// 編集用フォームデータ
const form = useForm({
  title: '',
  category: '会議',
  importance: '中',
  location: '',
  description: '',
  url: '',
  progress: 0,
  participants: [] as App.Models.User[],
  date_range: [new Date(), new Date()] as [Date, Date],
  is_all_day: false,
  start_time: '09:00',
  end_time: '10:00',
  attachments: {
    existing: [] as App.Models.EventAttachment[],
    new_files: [] as File[],
    removed_ids: [] as number[]
  },
  recurrence: {
    is_recurring: false,
    recurrence_type: 'daily',
    recurrence_interval: 1,
    by_day: [] as string[],
    by_set_pos: null as number | null,
    end_date: null as Date | null
  }
})

const date = ref<[Date, Date]>([new Date(), new Date()])
const fileInput = ref<HTMLInputElement | null>(null)
const endTimeManuallyChanged = ref(false)

const weekdays = [
  { label: '日', value: 'SU' },
  { label: '月', value: 'MO' },
  { label: '火', value: 'TU' },
  { label: '水', value: 'WE' },
  { label: '木', value: 'TH' },
  { label: '金', value: 'FR' },
  { label: '土', value: 'SA' }
]

// イベントが変わったらフォームを初期化
watch(() => props.event, (newEvent) => {
  if (newEvent && isEditMode.value) {
    form.title = newEvent.title || ''
    form.category = newEvent.category || '会議'
    form.importance = newEvent.importance || '中'
    form.location = newEvent.location || ''
    form.description = newEvent.description || ''
    form.url = newEvent.url || ''
    form.progress = newEvent.progress ?? 0
    form.participants = newEvent.participants || []
    form.date_range = [new Date(newEvent.start_date), new Date(newEvent.end_date)]
    form.is_all_day = newEvent.is_all_day
    form.start_time = newEvent.start_time?.slice(0, 5) || '09:00'
    form.end_time = newEvent.end_time?.slice(0, 5) || '10:00'
    form.attachments.existing = newEvent.attachments || []
    form.attachments.new_files = []
    form.attachments.removed_ids = []
    if (newEvent.recurrence) {
      form.recurrence.is_recurring = true
      form.recurrence.recurrence_type = newEvent.recurrence.recurrence_type
      form.recurrence.recurrence_interval = newEvent.recurrence.recurrence_interval
      form.recurrence.by_day = newEvent.recurrence.by_day || []
      form.recurrence.by_set_pos = newEvent.recurrence.by_set_pos
      form.recurrence.end_date = newEvent.recurrence.end_date ? new Date(newEvent.recurrence.end_date) : null
    } else {
      form.recurrence.is_recurring = false
    }
    date.value = form.date_range
  }
}, { immediate: true })

// 編集モードに切り替えたらフォームを初期化
watch(isEditMode, (editing) => {
  if (editing && props.event) {
    endTimeWatcherEnabled = false
    endTimeManuallyChanged.value = false
    
    form.title = props.event.title || ''
    form.category = props.event.category || '会議'
    form.importance = props.event.importance || '中'
    form.location = props.event.location || ''
    form.description = props.event.description || ''
    form.url = props.event.url || ''
    form.progress = props.event.progress ?? 0
    form.participants = props.event.participants || []
    form.date_range = [new Date(props.event.start_date), new Date(props.event.end_date)]
    form.is_all_day = props.event.is_all_day
    form.start_time = props.event.start_time?.slice(0, 5) || '09:00'
    form.end_time = props.event.end_time?.slice(0, 5) || '10:00'
    form.attachments.existing = props.event.attachments || []
    form.attachments.new_files = []
    form.attachments.removed_ids = []
    if (props.event.recurrence) {
      form.recurrence.is_recurring = true
      form.recurrence.recurrence_type = props.event.recurrence.recurrence_type
      form.recurrence.recurrence_interval = props.event.recurrence.recurrence_interval
      form.recurrence.by_day = props.event.recurrence.by_day || []
      form.recurrence.by_set_pos = props.event.recurrence.by_set_pos
      form.recurrence.end_date = props.event.recurrence.end_date ? new Date(props.event.recurrence.end_date) : null
    } else {
      form.recurrence.is_recurring = false
      form.recurrence.recurrence_type = 'daily'
      form.recurrence.recurrence_interval = 1
      form.recurrence.by_day = []
      form.recurrence.by_set_pos = null
      form.recurrence.end_date = null
    }
    date.value = form.date_range
    
    // 初期化完了後にwatcherを有効化
    setTimeout(() => {
      endTimeWatcherEnabled = true
    }, 100)
  }
})

watch(date, (newDates) => {
  if (Array.isArray(newDates) && newDates[0] instanceof Date && newDates[1] instanceof Date) {
    form.date_range = [...newDates]
  }
}, { deep: true })

// 週次繰り返し時の曜日自動選択
watch(() => form.recurrence.recurrence_type, (newType, oldType) => {
  if (newType === 'weekly' && form.recurrence.by_day.length === 0 && form.date_range[0]) {
    const dayIndex = form.date_range[0].getDay()
    const dayMap = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA']
    form.recurrence.by_day = [dayMap[dayIndex]]
  }
})

// 開始時刻変更時に終了時刻を自動調整（分も同じ値にする）
watch(() => form.start_time, (newStartTime, oldStartTime) => {
  if (isEditMode.value && !form.is_all_day && newStartTime && oldStartTime && newStartTime !== oldStartTime) {
    if (!endTimeManuallyChanged.value) {
      try {
        const [hours, minutes] = newStartTime.split(':').map(Number)
        const endHours = (hours + 1) % 24
        // 分も同じ値にする
        const newEndTime = `${String(endHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
        // 一時的にフラグを無効化して自動更新
        const tempFlag = endTimeManuallyChanged.value
        endTimeManuallyChanged.value = false
        form.end_time = newEndTime
        endTimeManuallyChanged.value = tempFlag
      } catch (e) {
        console.error('Time calculation error:', e)
      }
    }
  }
})

// 終了時刻が手動で変更されたことを検知
let endTimeWatcherEnabled = false
watch(() => form.end_time, (newVal, oldVal) => {
  if (endTimeWatcherEnabled && isEditMode.value && newVal !== oldVal) {
    endTimeManuallyChanged.value = true
  }
})

const handleEditOrView = () => {
  if (canEdit.value) {
    // 編集権限がある場合は編集モードに切り替え
    isEditMode.value = true
  } else {
    // 編集権限がない場合は閉じる
    closeDialog()
  }
}

const closeDialog = () => {
  isEditMode.value = false
  emit('update:open', false)
}

const handleDelete = () => {
  if (props.event) {
    // 繰り返しイベントの場合はスコープ選択ダイアログを表示
    if (props.event.recurrence || props.event.isRecurring) {
      recurrenceMode.value = 'delete'
      showRecurrenceDialog.value = true
    } else {
      executeDelete()
    }
  }
}

const executeDelete = (scope?: 'this-only' | 'this-and-future' | 'all') => {
  if (!props.event) return
  
  lastDeletedEvent.value = props.event
  
  const data: Record<string, any> = {}
  if (scope) {
    data.delete_scope = scope
    data.original_date = props.event.start_date
  }
  
  router.delete(route('events.destroy', props.event.event_id), {
    data,
    onSuccess: () => {
      closeDialog()
      setTimeout(() => {
        showMessage('イベントを削除しました。', 'delete')
        window.dispatchEvent(new CustomEvent('notification-updated'))
        window.dispatchEvent(new CustomEvent('calendar-data-updated'))
      }, 100)
    },
    onError: (errors) => {
      console.error('Delete error:', errors)
      lastDeletedEvent.value = null
      showMessage('イベントの削除に失敗しました。', 'success')
    }
  })
}

const handleRecurrenceConfirm = (scope: 'this-only' | 'this-and-future' | 'all') => {
  if (recurrenceMode.value === 'delete') {
    executeDelete(scope)
  } else if (recurrenceMode.value === 'edit') {
    executeSave(scope)
  }
}

const handleSave = () => {
  if (!props.event) return
  
  // 繰り返しイベントの場合はスコープ選択
  if (props.event.recurrence || props.event.isRecurring) {
    recurrenceMode.value = 'edit'
    showRecurrenceDialog.value = true
  } else {
    executeSave()
  }
}

const executeSave = (scope?: 'this-only' | 'this-and-future' | 'all') => {
  if (!props.event) return
  
  const formData = new FormData()
  
  // 基本フィールド
  formData.append('title', form.title)
  formData.append('category', form.category)
  formData.append('importance', form.importance)
  formData.append('location', form.location || '')
  formData.append('description', form.description || '')
  formData.append('url', form.url || '')
  formData.append('progress', String(form.progress))
  
  // 日時
  formData.append('date_range[0]', form.date_range[0].toLocaleDateString('sv-SE'))
  formData.append('date_range[1]', form.date_range[1].toLocaleDateString('sv-SE'))
  formData.append('is_all_day', form.is_all_day ? '1' : '0')
  if (!form.is_all_day) {
    formData.append('start_time', form.start_time)
    formData.append('end_time', form.end_time)
  }
  
  // 参加者
  form.participants.forEach((p, index) => {
    formData.append(`participants[${index}]`, String(p.id))
  })
  
  // 添付ファイル
  form.attachments.new_files.forEach((file, index) => {
    formData.append(`attachments[new_files][${index}]`, file)
  })
  form.attachments.removed_ids.forEach((id, index) => {
    formData.append(`attachments[removed_ids][${index}]`, String(id))
  })
  
  // 繰り返し設定
  formData.append('recurrence[is_recurring]', form.recurrence.is_recurring ? '1' : '0')
  if (form.recurrence.is_recurring) {
    formData.append('recurrence[recurrence_type]', form.recurrence.recurrence_type)
    formData.append('recurrence[recurrence_interval]', String(form.recurrence.recurrence_interval))
    if (form.recurrence.by_day.length > 0) {
      form.recurrence.by_day.forEach((day, index) => {
        formData.append(`recurrence[by_day][${index}]`, day)
      })
    }
    if (form.recurrence.end_date) {
      formData.append('recurrence[end_date]', form.recurrence.end_date.toLocaleDateString('sv-SE'))
    }
  }
  
  // 繰り返し編集スコープ
  if (scope) {
    formData.append('edit_scope', scope)
    formData.append('original_date', props.event.start_date)
  }
  
  formData.append('_method', 'PUT')
  
  router.post(route('events.update', props.event.event_id), formData, {
    onSuccess: () => {
      isEditMode.value = false
      closeDialog()
      setTimeout(() => {
        showMessage('予定を更新しました', 'success')
        window.dispatchEvent(new CustomEvent('notification-updated'))
        window.dispatchEvent(new CustomEvent('calendar-data-updated'))
      }, 100)
    },
    onError: (errors) => {
      console.error('Save error:', errors)
      const firstError = Object.values(errors)[0] as string
      showMessage(firstError || '保存に失敗しました。入力内容を確認してください。', 'success')
    }
  })
}

const handleRemoveParticipant = (participantId: number) => {
  form.participants = form.participants.filter(p => p.id !== participantId)
}

const handleToggleParticipant = (member: App.Models.User) => {
  const index = form.participants.findIndex(p => p.id === member.id)
  if (index > -1) {
    form.participants.splice(index, 1)
  } else {
    form.participants.push(member)
  }
}

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    Array.from(target.files).forEach(file => {
      form.attachments.new_files.push(file)
    })
    target.value = ''
  }
}

const handleRemoveNewFile = (index: number) => {
  form.attachments.new_files.splice(index, 1)
}

const handleRemoveExistingFile = (attachmentId: number) => {
  form.attachments.removed_ids.push(attachmentId)
  form.attachments.existing = form.attachments.existing.filter(f => f.attachment_id !== attachmentId)
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const format = (dates: Date[]) => {
  if (dates && dates.length === 2) {
    const start = dates[0].toLocaleDateString('ja-JP')
    const end = dates[1].toLocaleDateString('ja-JP')
    return `${start} - ${end}`
  }
  return ''
}

const toggleByDay = (day: string) => {
  const index = form.recurrence.by_day.indexOf(day)
  if (index > -1) {
    form.recurrence.by_day.splice(index, 1)
  } else {
    form.recurrence.by_day.push(day)
  }
}

const handleCancelEdit = () => {
  isEditMode.value = false
  form.reset()
}

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
    lastDeletedEvent.value = null
  }, 4000)
}



const handleUndoDelete = () => {
  if (!lastDeletedEvent.value) return

  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  saveMessage.value = '元に戻しています...'
  
  const eventToRestore = lastDeletedEvent.value
  lastDeletedEvent.value = null

  router.post(route('events.restore', eventToRestore.event_id), {}, {
    onSuccess: () => {
      showMessage('イベントが元に戻されました。', 'success')
      window.dispatchEvent(new CustomEvent('notification-updated'))
    },
    onError: () => {
      showMessage('元に戻す処理に失敗しました。', 'success')
    }
  })
}

const displayDate = computed(() => {
    if (!props.event) return '';
    const start = formatDate(props.event.start_date);
    const end = formatDate(props.event.end_date);
    if (start === end) {
        return start;
    }
    return `${start} - ${end}`;
});

// Format a time string like "13:00:00" or "13:00" to "13:00" (drop seconds if any)
const formatTime = (time?: string | null) => {
  if (!time) return '';
  const s = String(time);
  // If it's like HH:MM:SS, take first 5 chars; otherwise try to extract HH:MM
  if (s.length >= 5) return s.slice(0, 5);
  return s;
};

const displayTime = computed(() => {
  if (!props.event || props.event.is_all_day) return '終日';
  const start = formatTime(props.event.start_time);
  const end = formatTime(props.event.end_time);
  if (!start && !end) return '';
  if (start && end) return `${start} - ${end}`;
  return start || end;
});

const recurrenceText = computed(() => {

    if (!props.event?.recurrence) {
        return '';
    }
    const { recurrence_type, recurrence_interval, by_day, by_set_pos } = props.event.recurrence;
    let text = '';

    const intervalText = recurrence_interval > 1 ? `${recurrence_interval}` : '';

    switch (recurrence_type) {
        case 'daily':
            text = intervalText ? `${intervalText}日ごと` : '毎日';
            break;
        case 'weekly':
            text = intervalText ? `${intervalText}週間ごと` : '毎週';
            if (by_day && by_day.length > 0) {
                const weekdays: { [key: string]: string } = { MO: '月', TU: '火', WE: '水', TH: '木', FR: '金', SA: '土', SU: '日' };
                text += ' ' + by_day.map(d => weekdays[d]).join(', ') + '曜日';
            }
            break;
        case 'monthly':
            text = intervalText ? `${intervalText}ヶ月ごと` : '毎月';
            if (by_set_pos && by_day && by_day.length === 1) {
                const weekdays: { [key: string]: string } = { MO: '月', TU: '火', WE: '水', TH: '木', FR: '金', SA: '土', SU: '日' };
                const pos: { [key: string]: string } = { '1': '第1', '2': '第2', '3': '第3', '4': '第4', '-1': '最終' };
                text += ` ${pos[String(by_set_pos)] || ''}${weekdays[by_day[0]]}曜日`;
            }
            break;
        case 'yearly':
            text = intervalText ? `${intervalText}年ごと` : '毎年';
            break;
    }

    return text + 'に繰り返す';
});

const getCategoryColor = (cat: string) => {
  switch (cat) {
    case '会議': return 'bg-[#3b82f6]'
    case '業務': return 'bg-[#66bb6a]'
    case '来客': return 'bg-[#ffa726]'
    case '出張': return 'bg-[#9575cd]'
    case '休暇': return 'bg-[#f06292]'
    case 'その他': return 'bg-gray-500'
    default: return 'bg-gray-500'
  }
}

const getImportanceColor = (imp: string) => {
  switch (imp) {
    case '重要': return 'text-red-600'
    case '中': return 'text-yellow-600'
    case '低': return 'text-gray-600'
    default: return 'text-gray-600'
  }
}

const getImportanceLabel = (imp: string) => {
  switch (imp) {
    case '重要': return '重要'
    case '中': return '中'
    case '低': return '低'
    default: return '中'
  }
}

</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent v-if="event" class="max-w-md md:max-w-2xl lg:max-w-4xl w-[95vw] md:w-[66vw]">
      <DialogHeader>
        <DialogTitle v-if="!isEditMode">{{ event.title }}</DialogTitle>
        <Input v-else v-model="form.title" placeholder="タイトル" class="text-lg font-semibold" />
        <DialogDescription>
          {{ isEditMode ? '予定を編集' : '予定の詳細' }}
        </DialogDescription>
      </DialogHeader>

      <Separator />

      <div class="space-y-4 py-4 max-h-[60vh] overflow-y-auto">
        <!-- ジャンルと重要度 -->
        <div class="flex items-start gap-4">
          <Tag class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1 flex gap-6">
            <div class="flex-1">
              <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">ジャンル</Label>
              <Select v-if="isEditMode && canEdit" v-model="form.category">
                <SelectTrigger>
                  <div class="flex items-center gap-2">
                    <div :class="['w-3 h-3 rounded-full', getCategoryColor(form.category)]"></div>
                    <SelectValue />
                  </div>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="会議">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-[#3b82f6]"></div>
                      <span>会議</span>
                    </div>
                  </SelectItem>
                  <SelectItem value="業務">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-[#66bb6a]"></div>
                      <span>業務</span>
                    </div>
                  </SelectItem>
                  <SelectItem value="来客">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-[#ffa726]"></div>
                      <span>来客</span>
                    </div>
                  </SelectItem>
                  <SelectItem value="出張">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-[#9575cd]"></div>
                      <span>出張</span>
                    </div>
                  </SelectItem>
                  <SelectItem value="休暇">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-[#f06292]"></div>
                      <span>休暇</span>
                    </div>
                  </SelectItem>
                  <SelectItem value="その他">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                      <span>その他</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <Badge v-else :class="[getCategoryColor(event.category), 'text-white']">
                {{ event.category }}
              </Badge>
            </div>
            <div class="flex-1">
              <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">重要度</Label>
              <Select v-if="isEditMode && canEdit" v-model="form.importance">
                <SelectTrigger>
                  <div class="flex items-center gap-2">
                    <AlertCircle :class="['h-4 w-4', getImportanceColor(form.importance)]" />
                    <SelectValue />
                  </div>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="重要">
                    <Badge class="bg-red-600 text-white">重要</Badge>
                  </SelectItem>
                  <SelectItem value="中">
                    <Badge class="bg-yellow-500 text-white">中</Badge>
                  </SelectItem>
                  <SelectItem value="低">
                    <Badge class="bg-gray-400 text-white">低</Badge>
                  </SelectItem>
                </SelectContent>
              </Select>
              <Badge v-else :class="event.importance === '重要' ? 'bg-red-600 text-white' : event.importance === '中' ? 'bg-yellow-500 text-white' : 'bg-gray-400 text-white'">
                {{ getImportanceLabel(event.importance) }}
              </Badge>
            </div>
          </div>
        </div>


        <div class="flex items-start gap-4">
          <CalendarIcon class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <!-- 編集モード -->
            <div v-if="isEditMode && canEdit" class="space-y-3">
              <div class="flex items-center gap-4">
                <Switch v-model="form.is_all_day" id="edit-all-day" />
                <Label for="edit-all-day" class="text-sm cursor-pointer">終日</Label>
              </div>
              
              <div>
                <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">期間</Label>
                <VueDatePicker
                  v-model="date"
                  range
                  :time-config="{ enableTimePicker: false }"
                  placeholder="期間を選択"
                  :locale="ja"
                  :format="format"
                  :week-start="0"
                  auto-apply
                  teleport-center
                  class="mt-1"
                />
              </div>
              
              <div v-if="!form.is_all_day" class="flex gap-4">
                <div class="flex-1">
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">開始時刻</Label>
                  <Popover>
                    <PopoverTrigger as-child>
                      <Button variant="outline" class="w-full justify-start font-normal h-10 mt-1">
                        <Clock class="mr-2 h-4 w-4" />
                        {{ form.start_time || 'Select time' }}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0">
                      <time-picker v-model="form.start_time" />
                    </PopoverContent>
                  </Popover>
                </div>
                
                <div class="flex-1">
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">終了時刻</Label>
                  <Popover>
                    <PopoverTrigger as-child>
                      <Button variant="outline" class="w-full justify-start font-normal h-10 mt-1">
                        <Clock class="mr-2 h-4 w-4" />
                        {{ form.end_time || 'Select time' }}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0">
                      <time-picker v-model="form.end_time" />
                    </PopoverContent>
                  </Popover>
                </div>
              </div>
            </div>
            
            <!-- 表示モード -->
            <div v-else>
              <p class="font-semibold dark:text-gray-100">{{ displayDate }}</p>
              <p class="text-sm text-gray-600 dark:text-gray-300">{{ displayTime }}</p>
              <p v-if="event.recurrence" class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-1 mt-1">
                <Repeat class="h-4 w-4" />
                {{ recurrenceText }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="event.creator" class="flex items-start gap-4">
          <User class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">作成者</p>
            <div class="flex flex-wrap gap-2">
              <Badge variant="outline" class="border-gray-400 text-gray-700 dark:text-gray-300 dark:border-gray-500">
                  {{ event.creator.name }}
              </Badge>
            </div>
          </div>
        
        <div v-if="(event.participants && event.participants.length > 0) || isEditMode" class="flex items-start gap-4">
          <Users class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">参加者</Label>
            
            <!-- 編集モード -->
            <div v-if="isEditMode && canEdit" class="space-y-2">
              <div class="flex gap-2 mb-2">
                <Button type="button" variant="outline" size="sm" @click="form.participants = [...teamMembers]">
                  全員選択
                </Button>
                <Button type="button" variant="outline" size="sm" @click="form.participants = []">
                  選択解除
                </Button>
              </div>
              <div class="max-h-[150px] overflow-y-auto border rounded p-2 space-y-1">
                <div v-for="member in teamMembers" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-gray-50 rounded cursor-pointer">
                  <input 
                    :id="`edit-member-${member.id}`"
                    type="checkbox" 
                    :checked="form.participants.find(p => p.id === member.id) !== undefined"
                    @change="handleToggleParticipant(member)"
                    class="h-4 w-4 text-blue-600 rounded border-gray-300"
                  />
                  <label :for="`edit-member-${member.id}`" class="text-xs cursor-pointer">{{ member.name }}</label>
                </div>
              </div>
            </div>
            
            <!-- 表示モード -->
            <div v-else class="flex flex-wrap gap-2">
              <Badge v-if="!event.participants || event.participants.length === 0" variant="secondary" class="text-sm px-3 py-1">
                選択なし（全員確認可能）
              </Badge>
              <Badge v-else-if="event.participants.length === teamMembers.length" variant="secondary" class="text-sm px-3 py-1">
                全員
              </Badge>
              <template v-else>
                <Badge v-for="participant in event.participants" :key="participant.id" variant="outline" class="border-gray-400 text-gray-700 dark:text-gray-300 dark:border-gray-500">
                  {{ participant.name }}
                </Badge>
              </template>
            </div>
          </div>
        </div>
      </div>


        <div v-if="event.location || isEditMode" class="flex items-start gap-4">
          <MapPin class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 dark:text-gray-400">場所</Label>
            <Input v-if="isEditMode && canEdit" v-model="form.location" placeholder="例：会議室A" class="mt-1" />
            <p v-else class="dark:text-gray-200 mt-1">{{ event.location }}</p>
          </div>
        </div>

        <div v-if="event.url || isEditMode" class="flex items-start gap-4">
          <LinkIcon class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 dark:text-gray-400">URL</Label>
            <Input v-if="isEditMode && canEdit" v-model="form.url" type="url" placeholder="https://..." class="mt-1" />
            <a v-else :href="event.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all dark:text-blue-400 mt-1 block">{{ event.url }}</a>
          </div>
        </div>

        <div v-if="(event.attachments && event.attachments.length > 0) || (isEditMode && canEdit) || form.attachments.new_files.length > 0" class="flex items-start gap-4">
          <Paperclip class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">添付ファイル</Label>
            
            <div v-if="isEditMode && canEdit" class="space-y-2">
              <Button variant="outline" size="sm" @click="triggerFileInput">
                ファイルを選択
              </Button>
              <input type="file" multiple @change="handleFileChange" class="hidden" ref="fileInput" />
            </div>
            
            <div v-if="form.attachments.existing.length > 0" class="space-y-2 mt-2">
              <p v-if="isEditMode" class="text-sm font-medium">既存のファイル:</p>
              <div v-for="file in form.attachments.existing" :key="file.attachment_id" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                <a :href="`/storage/${file.file_path}`" target="_blank" class="text-sm text-blue-600 hover:underline truncate flex-1">{{ file.file_name }}</a>
                <Button v-if="isEditMode && canEdit" variant="ghost" size="sm" @click="handleRemoveExistingFile(file.attachment_id)">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>
            
            <div v-if="form.attachments.new_files.length > 0" class="space-y-2 mt-2">
              <p class="text-sm font-medium">新しいファイル:</p>
              <div v-for="(file, index) in form.attachments.new_files" :key="index" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                <span class="text-sm truncate flex-1">{{ file.name }}</span>
                <Button v-if="isEditMode && canEdit" variant="ghost" size="sm" @click="handleRemoveNewFile(index)">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="event.progress !== undefined && event.progress !== null" class="flex items-start gap-4">
          <div class="h-5 w-5 text-gray-400 mt-0.5 shrink-0 flex items-center justify-center">
            <div class="w-3 h-3 rounded-full border-2 border-current"></div>
          </div>
          <div class="flex-1">
            <Label class="text-sm text-gray-500 mb-2 dark:text-gray-400">進捗状況 ({{ isEditMode ? form.progress : event.progress }}%)</Label>
            <div class="relative">
              <div 
                class="w-full h-2 rounded-lg overflow-hidden mb-2"
                :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${isEditMode ? form.progress : event.progress}%, #e5e7eb ${isEditMode ? form.progress : event.progress}%, #e5e7eb 100%)` }"
              >
              </div>
              <input 
                v-if="isEditMode && canEdit"
                type="range" 
                min="0" 
                max="100" 
                v-model.number="form.progress" 
                class="w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider absolute top-0"
              />
              <div class="flex justify-between text-xs text-gray-500 mt-1 dark:text-gray-400">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
              </div>
            </div>
          </div>
        </div>


        <div v-if="event.description || isEditMode" class="flex items-start gap-4">
          <Info class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 mb-1 dark:text-gray-400">詳細</Label>
            <Textarea v-if="isEditMode && canEdit" v-model="form.description" placeholder="予定の詳細..." rows="6" class="mt-1" />
            <p v-else class="text-gray-700 whitespace-pre-wrap dark:text-gray-200 mt-1">{{ event.description }}</p>
          </div>
        </div>

        <!-- 繰り返し設定 -->
        <div v-if="(event.recurrence || (isEditMode && canEdit))" class="flex items-start gap-4">
          <Repeat class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div class="flex-1">
            <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">繰り返し</Label>
            
            <!-- 編集モード -->
            <div v-if="isEditMode && canEdit" class="space-y-3">
              <div class="flex items-center gap-2">
                <Switch v-model="form.recurrence.is_recurring" id="edit-recurring" />
                <Label for="edit-recurring" class="text-sm cursor-pointer">繰り返しを有効化</Label>
              </div>
              
              <div v-if="form.recurrence.is_recurring" class="space-y-3 pl-6 border-l-2 border-gray-300">
                <div>
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-1">繰り返しパターン</Label>
                  <Select v-model="form.recurrence.recurrence_type">
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="daily">毎日</SelectItem>
                      <SelectItem value="weekly">毎週</SelectItem>
                      <SelectItem value="monthly">毎月</SelectItem>
                      <SelectItem value="yearly">毎年</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                
                <!-- 週次の曜日指定 -->
                <div v-if="form.recurrence.recurrence_type === 'weekly'">
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-2">曜日</Label>
                  <div class="flex gap-1">
                    <Button
                      v-for="day in weekdays"
                      :key="day.value"
                      type="button"
                      :variant="form.recurrence.by_day.includes(day.value) ? 'default' : 'outline'"
                      size="sm"
                      @click="toggleByDay(day.value)"
                      class="rounded-full w-8 h-8 p-0"
                    >
                      {{ day.label }}
                    </Button>
                  </div>
                </div>
                
                <div>
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-1">繰り返し間隔</Label>
                  <Input type="number" min="1" v-model.number="form.recurrence.recurrence_interval" class="w-24" />
                </div>
                
                <div>
                  <Label class="text-sm text-gray-500 dark:text-gray-400 mb-1">繰り返し終了日</Label>
                  <VueDatePicker
                    v-model="form.recurrence.end_date"
                    placeholder="終了日を選択"
                    :locale="ja"
                    :week-start="0"
                    auto-apply
                    teleport-center
                  />
                  <p class="text-xs text-gray-500 mt-1">空白の場合は無期限で繰り返されます</p>
                </div>
              </div>
            </div>
            
            <!-- 表示モード -->
            <p v-else-if="event.recurrence" class="text-sm text-gray-600 dark:text-gray-300">
              {{ recurrenceText }}
            </p>
            <p v-else class="text-sm text-gray-500">繰り返しなし</p>
          </div>
        </div>
      </div>

      <DialogFooter class="flex-row justify-between gap-2">
        <Button variant="outline" @click="isEditMode ? handleCancelEdit() : closeDialog()">
          {{ isEditMode ? 'キャンセル' : '閉じる' }}
        </Button>
        <div class="flex gap-2">
          <Button v-if="!isEditMode" variant="outline" @click="handleEditOrView">
            {{ canEdit ? '編集' : '確認完了' }}
          </Button>
          <Button v-if="!isEditMode && canEdit" variant="outline" @click="handleDelete" size="sm" class="text-red-600 hover:text-red-700">
            <Trash2 class="h-4 w-4 mr-1" />
            削除
          </Button>
          <Button v-if="isEditMode && canEdit" @click="handleSave" :disabled="form.processing" class="gap-2">
            <Save class="h-4 w-4" />
            {{ form.processing ? '保存中...' : '保存' }}
          </Button>
        </div>
      </DialogFooter>
    </DialogContent>


    
    <!-- メッセージ表示 -->
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[70] p-3 text-white rounded-lg shadow-lg',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
          <Button 
            v-if="messageType === 'delete' && lastDeletedEvent"
            variant="link"
            class="text-white hover:bg-red-400 p-1 h-auto ml-2"
            @click.stop="handleUndoDelete"
          >
            <Undo2 class="h-4 w-4 mr-1" />
            <span class="underline">元に戻す</span>
          </Button>
        </div>
      </div>
    </Transition>

</Dialog>

  <!-- Recurrence Edit Dialog -->
  <Teleport to="body">
    <RecurrenceEditDialog
      :open="showRecurrenceDialog"
      @update:open="showRecurrenceDialog = $event"
      :mode="recurrenceMode"
      :event-date="event?.start_date || ''"
      @confirm="handleRecurrenceConfirm"
    />
  </Teleport>
</template>
