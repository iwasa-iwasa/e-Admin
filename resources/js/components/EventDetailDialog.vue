<script setup lang="ts">
import { Calendar as CalendarIcon, Users, MapPin } from 'lucide-vue-next'
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
import { router } from '@inertiajs/vue3'

interface Event {
  id: string
  title: string
  color: string
  assignee: string
  time?: string
  department?: string
  location?: string
  description?: string
  date?: string
}

defineProps<{ 
    event: Event | null,
    open: boolean 
}>()
const emit = defineEmits(['update:open'])

const editEvent = () => {
  router.get('/create-event')
}

const closeDialog = () => {
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent v-if="event" class="max-w-md">
      <DialogHeader>
        <DialogTitle>{{ event.title }}</DialogTitle>
        <DialogDescription v-if="event.department">
          {{ event.department }}
        </DialogDescription>
      </DialogHeader>

      <Separator />

      <div class="space-y-4">
        <div class="flex items-start gap-3">
          <CalendarIcon class="h-5 w-5 text-gray-400 mt-0.5" />
          <div>
            <p class="text-sm text-gray-500">日時</p>
            <p>{{ event.date }} {{ event.time }}</p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <Users class="h-5 w-5 text-gray-400 mt-0.5" />
          <div>
            <p class="text-sm text-gray-500">担当者</p>
            <p>{{ event.assignee }}</p>
          </div>
        </div>

        <div v-if="event.location" class="flex items-start gap-3">
          <MapPin class="h-5 w-5 text-gray-400 mt-0.5" />
          <div>
            <p class="text-sm text-gray-500">場所</p>
            <p>{{ event.location }}</p>
          </div>
        </div>

        <div v-if="event.description" class="flex items-start gap-3">
          <div class="w-5" />
          <div>
            <p class="text-sm text-gray-500 mb-1">詳細</p>
            <p class="text-gray-700">{{ event.description }}</p>
          </div>
        </div>
      </div>

      <DialogFooter class="flex gap-2 justify-end mt-4">
        <Button variant="outline" @click="closeDialog">
          閉じる
        </Button>
        <Button @click="editEvent">編集</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
