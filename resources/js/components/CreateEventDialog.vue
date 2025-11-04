<script setup lang="ts">
import { ref } from 'vue'
import { Calendar as CalendarIcon, Clock, Users, MapPin, FileText, AlertCircle, X, Save } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast/use-toast'

interface Participant {
  id: string
  name: string
}

const availableMembers: Participant[] = [
  { id: '1', name: '田中' },
  { id: '2', name: '佐藤' },
  { id: '3', name: '鈴木' },
  { id: '4', name: '山田' },
]

defineProps<{ 
    open: boolean 
}>()
const emit = defineEmits(['update:open'])

const title = ref('')
const isAllDay = ref(false)
const startDate = ref('')
const startTime = ref('09:00')
const endDate = ref('')
const endTime = ref('10:00')
const participants = ref<Participant[]>([])
const location = ref('')
const description = ref('')
const category = ref('会議')
const importance = ref('中')

const { toast } = useToast()

const handleAddParticipant = (memberId: string) => {
  const member = availableMembers.find((m) => m.id === memberId)
  if (member && !participants.value.find((p) => p.id === member.id)) {
    participants.value.push(member)
  }
}

const handleRemoveParticipant = (participantId: string) => {
  participants.value = participants.value.filter((p) => p.id !== participantId)
}

const handleSave = () => {
  if (!title.value.trim()) {
    toast({ title: 'Error', description: 'タイトルを入力してください', variant: 'destructive' })
    return
  }
  if (!startDate.value) {
    toast({ title: 'Error', description: '開始日を選択してください', variant: 'destructive' })
    return
  }
  if (!endDate.value) {
    toast({ title: 'Error', description: '終了日を選択してください', variant: 'destructive' })
    return
  }

  toast({ title: 'Success', description: '予定を作成しました' })
  handleClose()
}

const handleClose = () => {
  title.value = ''
  isAllDay.value = false
  startDate.value = ''
  startTime.value = '09:00'
  endDate.value = ''
  endTime.value = '10:00'
  participants.value = []
  location.value = ''
  description.value = ''
  category.value = '会議'
  importance.value = '中'
  emit('update:open', false)
}

const getImportanceColor = (imp: string) => {
  switch (imp) {
    case '高': return 'text-red-600'
    case '中': return 'text-yellow-600'
    case '低': return 'text-gray-600'
    default: return 'text-gray-600'
  }
}

const getCategoryColor = (cat: string) => {
  switch (cat) {
    case '会議': return 'bg-purple-500'
    case 'MTG': return 'bg-green-500'
    case '期限': return 'bg-orange-500'
    case '重要': return 'bg-red-500'
    case '有給': return 'bg-teal-500'
    default: return 'bg-gray-500'
  }
}

</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="max-w-3xl max-h-[90vh]">
      <DialogHeader>
        <DialogTitle>新規予定作成</DialogTitle>
        <DialogDescription>部署内共有カレンダーに予定を追加します</DialogDescription>
      </DialogHeader>

      <Tabs default-value="basic" class="w-full">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="basic">基本情報</TabsTrigger>
          <TabsTrigger value="datetime">日時・参加者</TabsTrigger>
          <TabsTrigger value="details">詳細</TabsTrigger>
        </TabsList>

        <ScrollArea class="max-h-[60vh] mt-4">
          <TabsContent value="basic" class="space-y-4">
            <div class="space-y-2">
              <Label for="title">タイトル / 件名 *</Label>
              <Input id="title" placeholder="例：部署ミーティング" v-model="title" autofocus />
            </div>
            <div class="space-y-2">
              <Label for="category">ジャンル</Label>
              <Select v-model="category">
                <SelectTrigger id="category">
                  <div class="flex items-center gap-2">
                    <div :class="['w-3 h-3 rounded-full', getCategoryColor(category)]"></div>
                    <SelectValue />
                  </div>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="会議">会議</SelectItem>
                  <SelectItem value="MTG">MTG</SelectItem>
                  <SelectItem value="期限">期限</SelectItem>
                  <SelectItem value="重要">重要</SelectItem>
                  <SelectItem value="有給">有給</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="importance">重要度</Label>
              <Select v-model="importance">
                <SelectTrigger id="importance">
                  <div class="flex items-center gap-2">
                    <AlertCircle :class="['h-4 w-4', getImportanceColor(importance)]" />
                    <SelectValue />
                  </div>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="高">高</SelectItem>
                  <SelectItem value="中">中</SelectItem>
                  <SelectItem value="低">低</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label for="location" class="flex items-center gap-2">
                <MapPin class="h-4 w-4" />
                場所・会議室
              </Label>
              <Input id="location" placeholder="例：会議室A、オンライン（Zoom）" v-model="location" />
            </div>
          </TabsContent>

          <TabsContent value="datetime" class="space-y-4">
            <div class="flex items-center space-x-2">
              <Checkbox id="allDay" :checked="isAllDay" @update:checked="isAllDay = $event" />
              <Label for="allDay" class="text-sm cursor-pointer">終日</Label>
            </div>
            <div class="space-y-2">
              <Label class="flex items-center gap-2">
                <CalendarIcon class="h-4 w-4" />
                開始日時 *
              </Label>
              <div class="flex gap-2">
                <Input type="date" v-model="startDate" class="flex-1" />
                <Input v-if="!isAllDay" type="time" v-model="startTime" class="w-32" />
              </div>
            </div>
            <div class="space-y-2">
              <Label class="flex items-center gap-2">
                <Clock class="h-4 w-4" />
                終了日時 *
              </Label>
              <div class="flex gap-2">
                <Input type="date" v-model="endDate" class="flex-1" />
                <Input v-if="!isAllDay" type="time" v-model="endTime" class="w-32" />
              </div>
            </div>
            <div class="space-y-2">
              <Label for="addParticipant" class="flex items-center gap-2">
                <Users class="h-4 w-4" />
                参加者を追加
              </Label>
              <Select @update:model-value="handleAddParticipant">
                <SelectTrigger id="addParticipant">
                  <SelectValue placeholder="メンバーを選択..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="member in availableMembers.filter(m => !participants.find(p => p.id === m.id))" :key="member.id" :value="member.id">
                    {{ member.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div v-if="participants.length > 0" class="space-y-2">
              <Label>選択済み参加者</Label>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="participant in participants" :key="participant.id" variant="secondary" class="text-sm px-3 py-1 gap-2">
                  {{ participant.name }}
                  <button @click="handleRemoveParticipant(participant.id)" class="hover:text-red-600">
                    <X class="h-3 w-3" />
                  </button>
                </Badge>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="details" class="space-y-4">
            <div class="space-y-2">
              <Label for="description" class="flex items-center gap-2">
                <FileText class="h-4 w-4" />
                詳細・メモ
              </Label>
              <Textarea id="description" placeholder="予定の詳細、準備事項、議題など..." v-model="description" rows="8" />
            </div>
          </TabsContent>
        </ScrollArea>
      </Tabs>

      <DialogFooter>
        <Button variant="outline" @click="handleClose">キャンセル</Button>
        <Button @click="handleSave" class="gap-2">
          <Save class="h-4 w-4" />
          作成
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
