<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Bell, Plus, Clock, ArrowLeft, Trash2 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Checkbox } from '@/components/ui/checkbox'
import { useToast } from '@/components/ui/toast/use-toast'
import ReminderDetailDialog from '@/Components/ReminderDetailDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps<{
  reminders: App.Models.Reminder[]
}>()

const page = usePage()
const { toast } = useToast()
const isCreateDialogOpen = ref(false)
const selectedReminder = ref<App.Models.Reminder | null>(null)
const completedReminderId = ref<number | null>(null)
const showCompleted = ref(false)

// 元に戻す処理
const restoreReminder = () => {
  if (completedReminderId.value) {
    router.post(route('reminders.restore'), {
      reminder_id: completedReminderId.value,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        completedReminderId.value = null
      },
    })
  }
}

// タスク完了処理
const handleToggleComplete = (id: number) => {
  if (confirm('タスクを完了しますか？')) {
    router.patch(route('reminders.complete', id), {}, {
      preserveScroll: true,
      onError: (errors) => {
        console.error('完了エラー:', errors)
      },
    })
  }
}

const handleDeletePermanently = (id: number) => {}
const handleUpdateReminder = (updatedReminder: App.Models.Reminder) => {}

// フラッシュメッセージを監視
watch(() => page.props.flash, (flash: any) => {
  if (flash?.success && flash?.reminder_id) {
    completedReminderId.value = flash.reminder_id
    
    toast({
      title: "タスク完了",
      description: flash.success,
      action: {
        label: "元に戻す",
        onClick: restoreReminder,
      },
      duration: 5000,
    })
  }
}, { deep: true, immediate: true })

const activeReminders = computed(() => props.reminders.filter((r) => !r.completed))
const completedReminders = computed(() => props.reminders.filter((r) => r.completed))

</script>

<template>
  <Head title="個人リマインダー" />
  <div class="h-full bg-gray-50 flex flex-col ">
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
          <Button variant="outline" @click="isCreateDialogOpen = true" class="gap-2">
            <Plus class="h-4 w-4" />
            新規作成
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
                <div v-for="reminder in activeReminders" :key="reminder.reminder_id" class="border-2 border-gray-200 bg-white rounded-lg p-4 hover:shadow-md transition-all cursor-pointer" @click="(e) => { if (!(e.target as HTMLElement).closest('button[role=\'checkbox\']') && !(e.target as HTMLElement).closest('button')) { selectedReminder = reminder } }">
                  <div class="flex items-start gap-3">
                    <Checkbox :checked="reminder.completed" @update:checked="handleToggleComplete(reminder.reminder_id)" class="mt-1" />
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
                    <Button variant="ghost" size="sm" @click.stop="handleDeletePermanently(reminder.reminder_id)" disabled>
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
              <Button 
                variant="ghost" 
                @click="showCompleted = !showCompleted"
                class="flex items-center gap-2 p-0 h-auto font-semibold text-lg"
              >
                <span>完了済み</span>
                <Badge variant="secondary">{{ completedReminders.length }}件</Badge>
              </Button>
            </CardTitle>
          </CardHeader>
          <CardContent v-if="showCompleted">
            <ScrollArea class="max-h-[300px]">
              <div class="space-y-3">
                <div v-for="reminder in completedReminders" :key="reminder.reminder_id" class="border-2 border-gray-300 bg-gray-100 rounded-lg p-4 opacity-60">
                  <div class="flex items-start gap-3">
                    <Checkbox :checked="reminder.completed" @update:checked="() => {}" class="mt-1" disabled />
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
                    <Button variant="ghost" size="sm" @click="handleDeletePermanently(reminder.reminder_id)" disabled>
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
  </div>
</template>
