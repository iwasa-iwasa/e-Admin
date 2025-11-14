<script setup lang="ts">
import { computed } from 'vue'
import { formatDate } from '@/lib/utils'
import { Calendar as CalendarIcon, Users, MapPin, Info, Link as LinkIcon, Paperclip, Repeat } from 'lucide-vue-next'
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
import { router } from '@inertiajs/vue3'

const props = defineProps<{ 
    event: App.Models.Event | null,
    open: boolean 
}>()
const emit = defineEmits(['update:open', 'edit'])

const editEvent = () => {
  if (props.event) {
    emit('edit', props.event.event_id)
  }
  closeDialog()
}

const closeDialog = () => {
  emit('update:open', false)
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

        <div v-if="event.description" class="flex items-start gap-4">
          <Info class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 mb-1">詳細</p>
            <p class="text-gray-700 whitespace-pre-wrap">{{ event.description }}</p>
          </div>
        </div>
      </div>

      <DialogFooter class="flex-row justify-end gap-2">
        <Button variant="outline" @click="closeDialog">
          閉じる
        </Button>
        <Button @click="editEvent">編集</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
