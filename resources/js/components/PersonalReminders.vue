<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed } from 'vue'
import { Bell, Plus, Clock } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Checkbox } from '@/components/ui/checkbox'
import ReminderDetailDialog from './ReminderDetailDialog.vue'

const props = defineProps<{
  reminders: App.Models.Reminder[]
}>()

const selectedReminder = ref<App.Models.Reminder | null>(null)

const completedCount = computed(() => props.reminders.filter((r) => r.completed).length)

// Data modification functions are disabled for now
const handleToggleComplete = (id: number) => {}
const handleUpdateReminder = (updatedReminder: App.Models.Reminder) => {}
const isDialogOpen = ref(false)

</script>

<template>
  <Card class="h-full flex flex-col">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Bell class="h-5 w-5 text-blue-600" />
          <CardTitle class="text-lg">個人リマインダー</CardTitle>
          <Badge v-if="completedCount > 0" variant="secondary" class="text-xs">
            {{ completedCount }}件完了
          </Badge>
        </div>
        <Button
          size="sm"
          variant="outline"
          class="h-8 gap-1"
          @click="isDialogOpen = true"
          disabled
        >
          <Plus class="h-3 w-3" />
          新規作成
        </Button>
      </div>
      <p v-if="completedCount > 0" class="text-xs text-gray-500 mt-2">
        ※ チェックした項目は次回ログイン時にゴミ箱へ移動します
      </p>
    </CardHeader>
    <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
      <ScrollArea class="h-full">
        <div class="space-y-3">
          <div
            v-for="reminder in reminders"
            :key="reminder.reminder_id"
            :class="['border-2 rounded-lg p-3 transition-all cursor-pointer', reminder.completed ? 'border-gray-300 bg-gray-100 opacity-60' : 'border-gray-200 bg-gray-50 hover:shadow-md']"
            @click="(e) => { if (!(e.target as HTMLElement).closest('button[role=\'checkbox\']')) { selectedReminder = reminder } }"
          >
            <div class="flex items-start gap-3">
              <Checkbox
                :checked="reminder.completed"
                @update:checked="handleToggleComplete(reminder.reminder_id)"
                class="mt-1"
              />
              <div class="flex-1">
                <h4 :class="['mb-2', reminder.completed ? 'line-through text-gray-500' : '']">
                  {{ reminder.title }}
                </h4>
                <div class="flex items-center gap-4 text-xs text-gray-600">
                  <div class="flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    期限: {{ formatDate(reminder.deadline) }}
                  </div>
                  <Badge variant="outline" class="text-xs">
                    {{ reminder.category }}
                  </Badge>
                </div>
              </div>
            </div>
          </div>
        </div>
      </ScrollArea>
    </CardContent>

    <ReminderDetailDialog
      :reminder="selectedReminder"
      :open="selectedReminder !== null"
      @update:open="(isOpen) => !isOpen && (selectedReminder = null)"
      @update:reminder="handleUpdateReminder"
    />
  </Card>
</template>
