<script setup lang="ts">
import { ref } from 'vue'
import { Bell, Calendar, StickyNote, BarChart3, Clock } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Separator } from '@/components/ui/separator'
import { useToast } from '@/components/ui/toast/use-toast'

defineProps<{ 
    open: boolean 
}>()
const emit = defineEmits(['update:open'])

const importantEvents = ref(true)
const importantNotes = ref(true)
const pendingSurveys = ref(true)
const personalReminders = ref(true)

const { toast } = useToast()

const handleSave = () => {
  toast({
    title: 'Success',
    description: '設定を保存しました',
  })
  emit('update:open', false)
}

const handleCancel = () => {
  importantEvents.value = true
  importantNotes.value = true
  pendingSurveys.value = true
  personalReminders.value = true
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="handleCancel">
    <DialogContent class="sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Bell class="h-5 w-5 text-blue-600" />
          通知設定
        </DialogTitle>
        <DialogDescription>
          各種通知の受け取り設定を管理できます
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-6 py-4">
        <!-- 重要な予定の通知 -->
        <div class="flex items-center justify-between space-x-4">
          <div class="flex items-start gap-3 flex-1">
            <Calendar class="h-5 w-5 text-red-500 mt-0.5" />
            <div class="space-y-0.5">
              <Label for="important-events" class="text-sm cursor-pointer">
                重要な予定の通知
              </Label>
              <p class="text-xs text-gray-500">
                重要度が高い予定やイベントの通知を受け取ります
              </p>
            </div>
          </div>
          <Switch
            id="important-events"
            v-model:checked="importantEvents"
          />
        </div>

        <Separator />

        <!-- 重要なメモの通知 -->
        <div class="flex items-center justify-between space-x-4">
          <div class="flex items-start gap-3 flex-1">
            <StickyNote class="h-5 w-5 text-yellow-600 mt-0.5" />
            <div class="space-y-0.5">
              <Label for="important-notes" class="text-sm cursor-pointer">
                重要なメモの通知
              </Label>
              <p class="text-xs text-gray-500">
                期限が近づいている重要なメモの通知を受け取ります
              </p>
            </div>
          </div>
          <Switch
            id="important-notes"
            v-model:checked="importantNotes"
          />
        </div>

        <Separator />

        <!-- 未回答アンケートの通知 -->
        <div class="flex items-center justify-between space-x-4">
          <div class="flex items-start gap-3 flex-1">
            <BarChart3 class="h-5 w-5 text-blue-600 mt-0.5" />
            <div class="space-y-0.5">
              <Label for="pending-surveys" class="text-sm cursor-pointer">
                未回答アンケートの通知
              </Label>
              <p class="text-xs text-gray-500">
                回答期限が近づいているアンケートの通知を受け取ります
              </p>
            </div>
          </div>
          <Switch
            id="pending-surveys"
            v-model:checked="pendingSurveys"
          />
        </div>

        <Separator />

        <!-- 個人リマインダーの通知 -->
        <div class="flex items-center justify-between space-x-4">
          <div class="flex items-start gap-3 flex-1">
            <Clock class="h-5 w-5 text-purple-600 mt-0.5" />
            <div class="space-y-0.5">
              <Label for="personal-reminders" class="text-sm cursor-pointer">
                個人リマインダーの通知
              </Label>
              <p class="text-xs text-gray-500">
                設定した個人リマインダーの通知を受け取ります
              </p>
            </div>
          </div>
          <Switch
            id="personal-reminders"
            v-model:checked="personalReminders"
          />
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="handleCancel">
          キャンセル
        </Button>
        <Button @click="handleSave">保存</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
