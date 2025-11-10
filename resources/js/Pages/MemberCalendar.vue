<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowLeft, ChevronLeft, ChevronRight, Plus, Search, Users, Eye, EyeOff, Calendar as CalendarIcon, Clock, MapPin, User, Edit, Trash2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

interface Member {
  id: string
  name: string
  initial: string
  color: string
  isCurrentUser: boolean
}

interface Event {
  id: string
  title: string
  date: string
  time: string
  location?: string
  memberId: string
  memberName: string
  color: string
  description?: string
  genre?: string
}

const members: Member[] = [
  { id: '1', name: '田中', initial: '田', color: '#3b82f6', isCurrentUser: true },
  { id: '2', name: '佐藤', initial: '佐', color: '#10b981', isCurrentUser: false },
  { id: '3', name: '鈴木', initial: '鈴', color: '#f59e0b', isCurrentUser: false },
  { id: '4', name: '山田', initial: '山', color: '#ef4444', isCurrentUser: false },
]

const mockEvents: Event[] = [
  { id: '1', title: 'チームミーティング', date: '2025-10-20', time: '10:00', location: '会議室A', memberId: '1', memberName: '田中', color: '#3b82f6', description: '週次のチームミーティング', genre: '会議' },
  { id: '2', title: 'クライアント打ち合わせ', date: '2025-10-20', time: '14:00', location: '会議室B', memberId: '1', memberName: '田中', color: '#3b82f6', description: '新規プロジェクトの打ち合わせ', genre: '会議' },
  { id: '3', title: '備品発注', date: '2025-10-21', time: '09:00', location: '', memberId: '2', memberName: '佐藤', color: '#10b981', description: '月次の備品発注作業', genre: '業務' },
  { id: '4', title: '勤怠システム説明会', date: '2025-10-22', time: '13:00', location: '大会議室', memberId: '3', memberName: '鈴木', color: '#f59e0b', description: '新システムの説明会', genre: '研修' },
  { id: '5', title: '書類整理', date: '2025-10-23', time: '10:00', location: '', memberId: '4', memberName: '山田', color: '#ef4444', description: '年末の書類整理', genre: '業務' },
  { id: '6', title: '部署会議', date: '2025-10-24', time: '15:00', location: '会議室A', memberId: '1', memberName: '田中', color: '#3b82f6', description: '月次の部署会議', genre: '会議' },
  { id: '7', title: '来客対応', date: '2025-10-22', time: '10:00', location: 'エントランス', memberId: '2', memberName: '佐藤', color: '#10b981', description: 'A社の山本様', genre: '来客' },
]

const currentDate = ref(new Date(2025, 9, 20))
const selectedEvent = ref<Event | null>(null)
const visibleMembers = ref<string[]>(members.map((m) => m.id))
const searchQuery = ref('')

const currentUser = computed(() => members.find((m) => m.isCurrentUser))

const toggleMemberVisibility = (memberId: string) => {
  if (visibleMembers.value.includes(memberId)) {
    visibleMembers.value = visibleMembers.value.filter((id) => id !== memberId)
  } else {
    visibleMembers.value.push(memberId)
  }
}

const toggleAllMembers = () => {
  if (visibleMembers.value.length === members.length) {
    visibleMembers.value = []
  } else {
    visibleMembers.value = members.map((m) => m.id)
  }
}

const showOnlyMember = (memberId: string) => {
  visibleMembers.value = [memberId]
}

const changeMonth = (delta: number) => {
  const newDate = new Date(currentDate.value)
  newDate.setMonth(newDate.getMonth() + delta)
  currentDate.value = newDate
}

const filteredEvents = computed(() => {
  return mockEvents.filter((event) => {
    const matchesMember = visibleMembers.value.includes(event.memberId)
    const matchesSearch =
      event.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      event.memberName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      event.location?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      ''

    return matchesMember && matchesSearch
  })
})

const currentMonthEvents = computed(() => {
  return filteredEvents.value.filter((event) => {
    const eventDate = new Date(event.date)
    return (
      eventDate.getMonth() === currentDate.value.getMonth() &&
      eventDate.getFullYear() === currentDate.value.getFullYear()
    )
  })
})

const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startingDayOfWeek = firstDay.getDay()
  const daysInMonth = lastDay.getDate()

  const days = []
  for (let i = 0; i < startingDayOfWeek; i++) {
    days.push(null)
  }
  for (let i = 1; i <= daysInMonth; i++) {
    days.push(i)
  }

  return days
})

const getEventsForDay = (day: number | null) => {
  if (!day) return []
  const dateStr = `${currentDate.value.getFullYear()}-${String(currentDate.value.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return currentMonthEvents.value.filter((event) => event.date === dateStr)
}

const handleEventClick = (event: Event) => {
  selectedEvent.value = event
}

const handleEditEvent = () => {
  if (selectedEvent.value && selectedEvent.value.memberId === currentUser.value?.id) {
    router.get('/create-event')
    selectedEvent.value = null
  }
}

const handleDeleteEvent = () => {
  if (selectedEvent.value && selectedEvent.value.memberId === currentUser.value?.id) {
    if (window.confirm(`「${selectedEvent.value.title}」を削除しますか？`)) {
      selectedEvent.value = null
    }
  }
}

const weekDays = ['日', '月', '火', '水', '木', '金', '土']

</script>

<template>
  <Head title="部署共有カレンダー" />
  <div class="min-h-screen bg-gray-50 flex">
    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <Users class="h-5 w-5 text-blue-600" />
            <h2 class="text-blue-600">部署メンバー</h2>
          </div>
          <Button variant="ghost" size="icon" @click="router.get('/')">
            <ArrowLeft class="h-5 w-5" />
          </Button>
        </div>
        <div class="flex items-center gap-2 mb-4">
          <Checkbox :checked="visibleMembers.length === members.length" @update:checked="toggleAllMembers" />
          <span class="text-sm text-gray-600">すべて表示</span>
        </div>
      </div>
      <ScrollArea class="flex-1">
        <div class="p-4 space-y-2">
          <Card v-for="member in members" :key="member.id" :class="['cursor-pointer transition-all', visibleMembers.includes(member.id) ? 'border-2 shadow-sm' : 'border border-gray-200 opacity-60']" :style="{ borderColor: visibleMembers.includes(member.id) ? member.color : undefined }">
            <CardContent class="p-4">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <Checkbox :checked="visibleMembers.includes(member.id)" @update:checked="toggleMemberVisibility(member.id)" />
                  <Avatar class="h-10 w-10" :style="{ backgroundColor: member.color }">
                    <AvatarFallback class="text-white">{{ member.initial }}</AvatarFallback>
                  </Avatar>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">{{ member.name }}</span>
                      <Badge v-if="member.isCurrentUser" variant="secondary" class="text-xs">自分</Badge>
                    </div>
                    <p class="text-xs text-gray-500">{{ currentMonthEvents.filter(e => e.memberId === member.id).length }}件の予定</p>
                  </div>
                </div>
                <Button variant="ghost" size="sm" @click="showOnlyMember(member.id)">
                  <EyeOff v-if="visibleMembers.includes(member.id) && visibleMembers.length === 1" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </Button>
              </div>
              <div class="flex items-center gap-2">
                <div class="w-full h-2 rounded-full" :style="{ backgroundColor: member.color }" />
              </div>
            </CardContent>
          </Card>
        </div>
      </ScrollArea>
      <div class="p-4 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-2">カレンダーの見方</p>
        <div class="space-y-1 text-xs text-gray-600">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-blue-500" />
            <span>自分の予定は編集・削除可能</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-gray-400" />
            <span>他メンバーの予定は閲覧のみ</span>
          </div>
        </div>
      </div>
    </aside>

    <div class="flex-1 flex flex-col">
      <header class="bg-white border-b border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-4">
            <h1 class="text-blue-600">部署共有カレンダー</h1>
            <Badge variant="secondary">{{ visibleMembers.length }}/{{ members.length }}名表示中</Badge>
          </div>
          <Button @click="router.get('/create-event')" class="gap-2">
            <Plus class="h-4 w-4" />
            新しい予定
          </Button>
        </div>
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <Input placeholder="予定、メンバー、場所で検索..." v-model="searchQuery" class="pl-9" />
        </div>
      </header>

      <div class="flex-1 bg-white p-6">
        <div class="flex items-center justify-between mb-6">
          <h2>{{ currentDate.getFullYear() }}年 {{ currentDate.getMonth() + 1 }}月</h2>
          <div class="flex items-center gap-2">
            <Button variant="outline" size="icon" @click="changeMonth(-1)">
              <ChevronLeft class="h-4 w-4" />
            </Button>
            <Button variant="outline" @click="currentDate = new Date(2025, 9, 20)">今月</Button>
            <Button variant="outline" size="icon" @click="changeMonth(1)">
              <ChevronRight class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
            <div v-for="(day, index) in weekDays" :key="day" :class="['p-3 text-center text-sm', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : 'text-gray-700']">
              {{ day }}
            </div>
          </div>
          <div class="grid grid-cols-7">
            <div v-for="(day, index) in calendarDays" :key="index" :class="['min-h-[120px] p-2 border-r border-b border-gray-200', day ? 'bg-white' : 'bg-gray-50', index % 7 === 0 ? 'border-l-0' : '']">
              <template v-if="day">
                <div :class="['text-sm mb-2', day === 20 && currentDate.getMonth() === 9 && currentDate.getFullYear() === 2025 ? 'bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center' : 'text-gray-700']">
                  {{ day }}
                </div>
                <div class="space-y-1">
                  <div v-for="event in getEventsForDay(day)" :key="event.id" class="text-xs p-1 rounded cursor-pointer hover:shadow-md transition-shadow truncate" :style="{ backgroundColor: event.color + '20', borderLeft: `3px solid ${event.color}` }" @click="handleEventClick(event)">
                    <div class="flex items-center gap-1">
                      <Clock class="h-3 w-3 flex-shrink-0" />
                      <span class="truncate">{{ event.time }} {{ event.title }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Dialog :open="selectedEvent !== null" @update:open="(open) => !open && (selectedEvent = null)">
      <DialogContent v-if="selectedEvent" class="max-w-md">
        <DialogHeader>
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <DialogTitle class="text-xl mb-2">{{ selectedEvent.title }}</DialogTitle>
              <div class="flex items-center gap-2">
                <Avatar class="h-6 w-6" :style="{ backgroundColor: selectedEvent.color }">
                  <AvatarFallback class="text-white text-xs">{{ members.find((m) => m.id === selectedEvent.memberId)?.initial }}</AvatarFallback>
                </Avatar>
                <span class="text-sm text-gray-600">{{ selectedEvent.memberName }}</span>
                <Badge v-if="selectedEvent.memberId === currentUser?.id" variant="secondary" class="text-xs">自分の予定</Badge>
              </div>
            </div>
          </div>
        </DialogHeader>
        <Separator />
        <div class="space-y-4">
          <div class="flex items-start gap-3">
            <CalendarIcon class="h-5 w-5 text-gray-400 mt-0.5" />
            <div>
              <p class="text-sm text-gray-500">日時</p>
              <p>{{ selectedEvent.date }} {{ selectedEvent.time }}</p>
            </div>
          </div>
          <div v-if="selectedEvent.location" class="flex items-start gap-3">
            <MapPin class="h-5 w-5 text-gray-400 mt-0.5" />
            <div>
              <p class="text-sm text-gray-500">場所</p>
              <p>{{ selectedEvent.location }}</p>
            </div>
          </div>
          <div v-if="selectedEvent.genre" class="flex items-start gap-3">
            <User class="h-5 w-5 text-gray-400 mt-0.5" />
            <div>
              <p class="text-sm text-gray-500">ジャンル</p>
              <Badge>{{ selectedEvent.genre }}</Badge>
            </div>
          </div>
          <div v-if="selectedEvent.description" class="flex items-start gap-3">
            <div class="w-5" />
            <div>
              <p class="text-sm text-gray-500 mb-1">詳細</p>
              <p class="text-gray-700">{{ selectedEvent.description }}</p>
            </div>
          </div>
        </div>
        <DialogFooter>
          <div v-if="selectedEvent.memberId === currentUser?.id" class="flex gap-2 w-full">
            <Button variant="destructive" @click="handleDeleteEvent" class="gap-2">
              <Trash2 class="h-4 w-4" />
              削除
            </Button>
            <Button @click="handleEditEvent" class="gap-2 flex-1">
              <Edit class="h-4 w-4" />
              編集
            </Button>
          </div>
          <div v-else class="w-full">
            <p class="text-sm text-gray-500 text-center mb-3">他のメンバーの予定は編集できません</p>
            <Button variant="outline" @click="selectedEvent = null" class="w-full">閉じる</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
