<script setup lang="ts">
import { computed } from 'vue'
import { formatDate } from '@/lib/utils'
import { Calendar as CalendarIcon, Users, MapPin, Info } from 'lucide-vue-next'
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
const emit = defineEmits(['update:open'])

const editEvent = () => {
  // TODO: Implement edit functionality
  console.log("Edit event:", props.event?.event_id)
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

        <div v-if="event.description" class="flex items-start gap-4">
          <Info class="h-5 w-5 text-gray-400 mt-0.5 shrink-0" />
          <div>
            <p class="text-sm text-gray-500 mb-1">詳細</p>
            <p class="text-gray-700 whitespace-pre-wrap">{{ event.description }}</p>
          </div>
        </div>
      </div>

      <DialogFooter class="flex gap-2 justify-end mt-4">
        <Button variant="outline" @click="closeDialog">
          閉じる
        </Button>
        <!-- <Button @click="editEvent">編集</Button> -->
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
