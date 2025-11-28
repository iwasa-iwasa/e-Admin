<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatDate } from '@/lib/utils'
import { Calendar as CalendarIcon, Users, MapPin, Info, Link as LinkIcon, Paperclip, Repeat, Trash2, CheckCircle, Undo2, Clock } from 'lucide-vue-next'
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
import { router, usePage } from '@inertiajs/vue3'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

const props = defineProps<{ 
    event: App.Models.Event | null,
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
  const isCreator = event.creator_id === currentUserId.value
  
  // 参加者が空：作成者のみ編集可能
  if (!event.participants || event.participants.length === 0) {
    return isCreator
  }
  
  // 全員が参加者：全員編集可能
  if (Array.isArray(teamMembers.value) && teamMembers.value.length > 0 && event.participants.length === teamMembers.value.length) {
    return true
  }
  
  // 個人指定：作成者または参加者のみ編集可能
  const isParticipant = event.participants.some(p => p.id === currentUserId.value)
  return isCreator || isParticipant
})


const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedEvent = ref<App.Models.Event | null>(null)

const handleEditOrView = () => {
  emit('edit')
}

const closeDialog = () => {
  emit('update:open', false)
}

const handleDelete = () => {
  if (props.event) {
    lastDeletedEvent.value = props.event
    
    router.delete(route('events.destroy', props.event.event_id), {
      onSuccess: () => {
        closeDialog()
        // ダイアログを閉じた後にメッセージを表示
        setTimeout(() => {
          showMessage('イベントを削除しました。', 'delete')
        }, 100)
      },
      onError: (errors) => {
        console.error('Delete error:', errors)
        lastDeletedEvent.value = null
        showMessage('イベントの削除に失敗しました。', 'success')
      }
    })
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

</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent v-if="event" class="max-w-md">
      <DialogHeader>
        <DialogTitle>{{ event.title }}</DialogTitle>
        <DialogDescription>
          予定の詳細
        </DialogDescription>
      </DialogHeader>

      <Separator />

      <div class="space-y-4 py-4">
        <div class="flex items-start gap-4">
          <CalendarIcon class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="font-semibold">{{ displayDate }}</p>
            <p class="text-sm text-gray-600">{{ displayTime }}</p>
            <p v-if="event.recurrence" class="text-sm text-gray-600 flex items-center gap-1 mt-1">
              <Repeat class="h-4 w-4" />
              {{ recurrenceText }}
            </p>
          </div>
        </div>

        <div v-if="event.participants && event.participants.length > 0" class="flex items-start gap-4">
          <Users class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 mb-2">参加者</p>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="participant in event.participants" :key="participant.id" variant="secondary">
                    {{ participant.name }}
                </Badge>
            </div>
          </div>
        </div>

        <div v-if="event.location" class="flex items-start gap-4">
          <MapPin class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500">場所</p>
            <p>{{ event.location }}</p>
          </div>
        </div>

        <div v-if="event.url" class="flex items-start gap-4">
          <LinkIcon class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500">URL</p>
            <a :href="event.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">{{ event.url }}</a>
          </div>
        </div>

        <div v-if="event.attachments && event.attachments.length > 0" class="flex items-start gap-4">
          <Paperclip class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 mb-2">添付ファイル</p>
            <div class="space-y-2">
              <a v-for="file in event.attachments" :key="file.attachment_id" :href="`/storage/${file.file_path}`" target="_blank" class="flex items-center gap-2 text-sm text-blue-600 hover:underline">
                {{ file.file_name }}
              </a>
            </div>
          </div>
        </div>

        <div v-if="event.progress !== undefined && event.progress !== null" class="flex items-start gap-4">
          <div class="h-5 w-5 text-gray-400 mt-0.5 shrink-0 flex items-center justify-center">
            <div class="w-3 h-3 rounded-full border-2 border-current"></div>
          </div>
          <div class="flex-1">
            <p class="text-sm text-gray-500 mb-2">進捗 ({{ event.progress }}%)</p>
            <div class="relative">
              <div 
                class="w-full h-2 rounded-lg overflow-hidden"
                :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${event.progress}%, #e5e7eb ${event.progress}%, #e5e7eb 100%)` }"
              >
              </div>
              <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-4">
          <Clock class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500">締切</p>
            <p class="text-gray-700">{{ formatDate(event.end_date) }}{{ event.end_time && !event.is_all_day ? ' ' + formatTime(event.end_time) : '' }}</p>
          </div>
        </div>

        <div v-if="event.description" class="flex items-start gap-4">
          <Info class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 mb-1">詳細</p>
            <p class="text-gray-700 whitespace-pre-wrap">{{ event.description }}</p>
          </div>
        </div>
      </div>

      <DialogFooter class="flex-row justify-between gap-2">
        <Button variant="outline" @click="closeDialog">
          閉じる
        </Button>
        <div class="flex gap-2">
          <Button variant="outline" @click="handleEditOrView">
            {{ canEdit ? '編集' : '確認' }}
          </Button>
          <Button v-if="canEdit" variant="outline" @click="handleDelete" size="sm" class="text-red-600 hover:text-red-700">
            <Trash2 class="h-4 w-4 mr-1" />
            削除
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
</template>
