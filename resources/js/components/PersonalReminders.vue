<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
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
const recentlyCompleted = ref<{ id: number; undoUrl: string } | null>(null)

const completedCount = computed(() => props.reminders.filter((r) => r.completed).length)

const handleToggleComplete = (id: number, checked: boolean) => {
  if (checked) {
    router.patch(route('reminders.complete', id), {}, {
      onSuccess: (page) => {
        const pageProps = page.props
        const message = pageProps?.success
        
        if (message) {
          recentlyCompleted.value = { 
            id: id, 
            undoUrl: route('reminders.restore', id)
          }
          
          setTimeout(() => {
            if (recentlyCompleted.value?.id === id) {
              recentlyCompleted.value = null
            }
          }, 5000)
        }
      },
    })
  }
}



// 元に戻すアクション
const undoAction = () => {
  if (recentlyCompleted.value) {
    // 生成されたundo_urlを使用してPOSTリクエスト
    router.post(recentlyCompleted.value.undoUrl, {}, {
      replace: true,
      onSuccess: () => {
        recentlyCompleted.value = null
      }
    })
  }
}
const handleUpdateReminder = (updatedReminder: App.Models.Reminder) => {}
const isCreateDialogOpen = ref(false)

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
          @click="isCreateDialogOpen = true"
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
            :class="['border-2 rounded-lg p-3 transition-all cursor-pointer relative min-h-12', reminder.completed ? 'border-gray-300 bg-gray-100 opacity-60' : 'border-gray-200 bg-gray-50 hover:shadow-md']"
@click="(e) => { 
              e.preventDefault();
              e.stopPropagation();
              if (!(e.target as HTMLElement).closest('input[type=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { 
                selectedReminder = reminder 
              } 
            }"
          >
            <div class="flex items-start gap-3">
              <input
                type="checkbox"
                :checked="reminder.completed"
                @click.stop
                @change="handleToggleComplete(reminder.reminder_id, ($event.target as HTMLInputElement).checked)"
                :data-reminder-id="reminder.reminder_id"
                class="mt-1 h-4 w-4 text-blue-600 rounded"
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
            
            <!-- 元に戻すボタンオーバーレイ -->
            <div 
              v-if="recentlyCompleted && String(recentlyCompleted.id) === String(reminder.reminder_id)"
              class="absolute inset-0 bg-red-500/90 flex items-center justify-end pr-4 rounded-lg z-[9999]"
              style="pointer-events: auto;"
            >
              <!-- デバッグ情報 -->
              <span class="text-xs text-white mr-2">RC:{{ recentlyCompleted?.id }} R:{{ reminder.reminder_id }}</span>
              <span class="text-sm text-white mr-4">タスク完了！</span>
              <Button 
                size="sm" 
                @click.stop="undoAction"
                variant="default"
                class="!bg-green-500 !text-white"
              >
                元に戻す
              </Button>
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

    <ReminderDetailDialog
      :reminder="null"
      :open="isCreateDialogOpen"
      @update:open="isCreateDialogOpen = $event"
      @update:reminder="handleUpdateReminder"
    />
  </Card>
</template>
