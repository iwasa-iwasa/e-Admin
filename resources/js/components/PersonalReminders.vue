<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { Bell, Plus, Clock } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import ReminderDetailDialog from './ReminderDetailDialog.vue'

interface Reminder {
  id: number
  title: string
  deadline: string
  category: string
  completed: boolean
  completedAt?: string
  description?: string
}

const reminders = ref<Reminder[]>([])

onMounted(() => {
  const saved = localStorage.getItem('personalReminders')
  if (saved) {
    reminders.value = JSON.parse(saved)
  } else {
    reminders.value = [
      {
        id: 1,
        title: '経費精算の提出',
        deadline: '2025-10-18',
        category: '業務',
        completed: false,
      },
      {
        id: 2,
        title: '年末調整書類の確認',
        deadline: '2025-10-25',
        category: '人事',
        completed: false,
      },
      {
        id: 3,
        title: '会議室予約（来週分）',
        deadline: '2025-10-20',
        category: '業務',
        completed: false,
      },
      {
        id: 4,
        title: '備品在庫チェック',
        deadline: '2025-10-22',
        category: '総務',
        completed: false,
      },
    ]
  }

  const lastSession = localStorage.getItem('lastSessionTime')
  const currentTime = new Date().getTime()

  if (lastSession) {
    const timeDiff = currentTime - parseInt(lastSession)
    if (timeDiff > 5 * 60 * 1000) {
      const completedItems = reminders.value.filter((r) => r.completed)
      if (completedItems.length > 0) {
        const trash = JSON.parse(localStorage.getItem('trash') || '[]')
        const trashItems = completedItems.map((item) => ({
          ...item,
          type: 'reminder',
          deletedAt: new Date().toISOString(),
        }))
        localStorage.setItem('trash', JSON.stringify([...trash, ...trashItems]))

        const activeReminders = reminders.value.filter((r) => !r.completed)
        reminders.value = activeReminders
        
        console.log('${completedItems.length}件のリマインダーをゴミ箱に移動しました')
      }
    }
  }

  localStorage.setItem('lastSessionTime', currentTime.toString())
})

watch(reminders, (newReminders) => {
  localStorage.setItem('personalReminders', JSON.stringify(newReminders))
}, { deep: true })

const isDialogOpen = ref(false)
const newReminder = ref({
  title: '',
  deadline: '',
  category: '業務',
  description: '',
})
const selectedReminder = ref<Reminder | null>(null)

const handleToggleComplete = (id: number) => {
  reminders.value = reminders.value.map((reminder) =>
    reminder.id === id
      ? {
          ...reminder,
          completed: !reminder.completed,
          completedAt: !reminder.completed ? new Date().toISOString() : undefined,
        }
      : reminder
  )
}

const handleAddReminder = () => {
  if (!newReminder.value.title.trim()) return

  const newReminderObj: Reminder = {
    id: Date.now(),
    title: newReminder.value.title,
    deadline: newReminder.value.deadline || new Date().toISOString().split('T')[0],
    category: newReminder.value.category,
    completed: false,
    description: newReminder.value.description,
  }

  reminders.value.push(newReminderObj)
  newReminder.value = {
    title: '',
    deadline: '',
    category: '業務',
    description: '',
  }
  isDialogOpen.value = false
}

const handleUpdateReminder = (updatedReminder: Reminder) => {
  reminders.value = reminders.value.map((reminder) =>
    reminder.id === updatedReminder.id ? updatedReminder : reminder
  )
}

const completedCount = computed(() => reminders.value.filter((r) => r.completed).length)

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
        >
          <Plus class="h-3 w-3" />
          追加
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
            :key="reminder.id"
            :class="['border-2 rounded-lg p-3 transition-all cursor-pointer', reminder.completed ? 'border-gray-300 bg-gray-100 opacity-60' : 'border-gray-200 bg-gray-50 hover:shadow-md']"
            @click="(e) => { if (!(e.target as HTMLElement).closest('button[role=\'checkbox\']')) { selectedReminder = reminder } }"
          >
            <div class="flex items-start gap-3">
              <Checkbox
                :checked="reminder.completed"
                @update:checked="handleToggleComplete(reminder.id)"
                class="mt-1"
              />
              <div class="flex-1">
                <h4 :class="['mb-2', reminder.completed ? 'line-through text-gray-500' : '']">
                  {{ reminder.title }}
                </h4>
                <div class="flex items-center gap-4 text-xs text-gray-600">
                  <div class="flex items-center gap-1">
                    <Clock class="h-3 w-3" />
                    期限: {{ reminder.deadline }}
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

    <Dialog v-model:open="isDialogOpen">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>新しいリマインダーを追加</DialogTitle>
          <DialogDescription>
            リマインダーの詳細を入力してください
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <Input
            placeholder="リマインダーのタイトル"
            v-model="newReminder.title"
            @keypress.enter="handleAddReminder"
            autofocus
          />
          <div class="flex gap-2">
            <Input
              type="date"
              v-model="newReminder.deadline"
              class="flex-1"
            />
            <Select v-model="newReminder.category">
              <SelectTrigger class="w-[120px]">
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
          <Textarea
            placeholder="詳細（任意）"
            v-model="newReminder.description"
            class="h-20"
          />
        </div>
        <DialogFooter>
          <Button
            @click="handleAddReminder"
            size="sm"
            class="w-full"
            :disabled="!newReminder.title.trim()"
          >
            追加
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ReminderDetailDialog
      :reminder="selectedReminder"
      :open="selectedReminder !== null"
      @update:open="(isOpen) => !isOpen && (selectedReminder = null)"
      @update:reminder="handleUpdateReminder"
    />
  </Card>
</template>
