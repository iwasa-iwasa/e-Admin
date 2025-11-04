<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Bell, Plus, Clock, ArrowLeft, Trash2 } from 'lucide-vue-next'
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
import ReminderDetailDialog from '@/components/ReminderDetailDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useToast } from '@/components/ui/toast/use-toast'

defineOptions({
  layout: AuthenticatedLayout,
})

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
const isCreateDialogOpen = ref(false)
const selectedReminder = ref<Reminder | null>(null)
const newReminder = ref({
  title: '',
  deadline: '',
  category: '業務',
  description: '',
})

const { toast } = useToast()

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
        description: '9月分の経費精算を提出する',
      },
      {
        id: 2,
        title: '年末調整書類の確認',
        deadline: '2025-10-25',
        category: '人事',
        completed: false,
        description: '必要書類を確認し、準備する',
      },
      {
        id: 3,
        title: '会議室予約（来週分）',
        deadline: '2025-10-20',
        category: '業務',
        completed: false,
        description: '来週の会議用に会議室を予約',
      },
      {
        id: 4,
        title: '備品在庫チェック',
        deadline: '2025-10-22',
        category: '総務',
        completed: false,
        description: '月次の備品在庫確認',
      },
    ]
  }
})

watch(reminders, (newReminders) => {
  localStorage.setItem('personalReminders', JSON.stringify(newReminders))
}, { deep: true })

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

const handleDeletePermanently = (id: number) => {
  if (window.confirm('このリマインダーを完全に削除しますか？')) {
    reminders.value = reminders.value.filter((r) => r.id !== id)
  }
}

const handleCreateReminder = () => {
  if (!newReminder.value.title || !newReminder.value.deadline) {
    toast({
        title: 'Error',
        description: 'タイトルと期限は必須です',
        variant: 'destructive',
    })
    return
  }

  const reminder: Reminder = {
    id: Date.now(),
    title: newReminder.value.title,
    deadline: newReminder.value.deadline,
    category: newReminder.value.category,
    description: newReminder.value.description,
    completed: false,
  }

  reminders.value.push(reminder)
  isCreateDialogOpen.value = false
  newReminder.value = {
    title: '',
    deadline: '',
    category: '業務',
    description: '',
  }
}

const handleUpdateReminder = (updatedReminder: Reminder) => {
  reminders.value = reminders.value.map((reminder) =>
    reminder.id === updatedReminder.id ? updatedReminder : reminder
  )
}

const activeReminders = computed(() => reminders.value.filter((r) => !r.completed))
const completedReminders = computed(() => reminders.value.filter((r) => r.completed))

</script>

<template>
  <div class="h-screen bg-gray-50 flex flex-col">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Button variant="ghost" size="icon" @click="router.get('/')">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <Bell class="h-6 w-6 text-blue-600" />
          <div>
            <h1 class="text-blue-600">個人リマインダー</h1>
            <p class="text-xs text-gray-500">自分専用のタスク管理</p>
          </div>
        </div>
        <Button @click="isCreateDialogOpen = true" class="gap-2">
          <Plus class="h-4 w-4" />
          新規追加
        </Button>
      </div>
    </header>

    <main class="flex-1 overflow-auto p-6">
      <div class="max-w-4xl mx-auto space-y-6">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <span>アクティブ</span>
              <Badge>{{ activeReminders.length }}件</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ScrollArea class="max-h-[400px]">
              <div class="space-y-3">
                <div v-for="reminder in activeReminders" :key="reminder.id" class="border-2 border-gray-200 bg-white rounded-lg p-4 hover:shadow-md transition-all cursor-pointer" @click="(e) => { if (!(e.target as HTMLElement).closest('button[role=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { selectedReminder = reminder } }">
                  <div class="flex items-start gap-3">
                    <Checkbox :checked="reminder.completed" @update:checked="handleToggleComplete(reminder.id)" class="mt-1" />
                    <div class="flex-1">
                      <h3 class="mb-2">{{ reminder.title }}</h3>
                      <p v-if="reminder.description" class="text-sm text-gray-600 mb-2">{{ reminder.description }}</p>
                      <div class="flex items-center gap-4 text-xs text-gray-600">
                        <div class="flex items-center gap-1">
                          <Clock class="h-3 w-3" />
                          期限: {{ reminder.deadline }}
                        </div>
                        <Badge variant="outline" class="text-xs">{{ reminder.category }}</Badge>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" @click.stop="handleDeletePermanently(reminder.id)">
                      <Trash2 class="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
                <div v-if="activeReminders.length === 0" class="text-center py-12 text-gray-500">
                  <Bell class="h-12 w-12 mx-auto mb-4 opacity-30" />
                  <p>アクティブなリマインダーはありません</p>
                </div>
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <Card v-if="completedReminders.length > 0">
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <span>完了済み</span>
              <Badge variant="secondary">{{ completedReminders.length }}件</Badge>
            </CardTitle>
            <p class="text-xs text-gray-500 mt-2">※ 次回ログイン時にゴミ箱へ移動します</p>
          </CardHeader>
          <CardContent>
            <ScrollArea class="max-h-[300px]">
              <div class="space-y-3">
                <div v-for="reminder in completedReminders" :key="reminder.id" class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 opacity-60">
                  <div class="flex items-start gap-3">
                    <Checkbox :checked="reminder.completed" @update:checked="handleToggleComplete(reminder.id)" class="mt-1" />
                    <div class="flex-1">
                      <h3 class="mb-2 line-through text-gray-500">{{ reminder.title }}</h3>
                      <p v-if="reminder.description" class="text-sm text-gray-500 mb-2 line-through">{{ reminder.description }}</p>
                      <div class="flex items-center gap-4 text-xs text-gray-500">
                        <div class="flex items-center gap-1">
                          <Clock class="h-3 w-3" />
                          期限: {{ reminder.deadline }}
                        </div>
                        <Badge variant="outline" class="text-xs opacity-60">{{ reminder.category }}</Badge>
                      </div>
                    </div>
                    <Button variant="ghost" size="sm" @click="handleDeletePermanently(reminder.id)">
                      <Trash2 class="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              </div>
            </ScrollArea>
          </CardContent>
        </Card>
      </div>
    </main>

    <Dialog v-model:open="isCreateDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>新しいリマインダーを追加</DialogTitle>
          <DialogDescription>自分専用のリマインダーを作成します</DialogDescription>
        </DialogHeader>
        <div class="space-y-4">
          <div>
            <label class="text-sm mb-1 block">タイトル *</label>
            <Input placeholder="リマインダーのタイトル" v-model="newReminder.title" />
          </div>
          <div>
            <label class="text-sm mb-1 block">期限 *</label>
            <Input type="date" v-model="newReminder.deadline" />
          </div>
          <div>
            <label class="text-sm mb-1 block">カテゴリー</label>
            <Select v-model="newReminder.category">
              <SelectTrigger>
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
          <div>
            <label class="text-sm mb-1 block">詳細（任意）</label>
            <Textarea placeholder="リマインダーの詳細説明" v-model="newReminder.description" rows="3" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="isCreateDialogOpen = false">キャンセル</Button>
          <Button @click="handleCreateReminder">作成</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ReminderDetailDialog :reminder="selectedReminder" :open="selectedReminder !== null" @update:open="(isOpen) => !isOpen && (selectedReminder = null)" @update:reminder="handleUpdateReminder" />
  </div>
</template>
