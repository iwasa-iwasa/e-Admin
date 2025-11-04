<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, Users, Clock, ArrowLeft, Search } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { type Event, getEventsForDate } from '@/data/events'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineOptions({
  layout: AuthenticatedLayout,
})

type ViewMode = 'year' | 'month' | 'week' | 'day'

const currentDate = ref(new Date(2025, 9, 14))
const viewMode = ref<ViewMode>('month')
const searchQuery = ref('')
const isSearchFocused = ref(false)
const selectedEvent = ref<Event | null>(null)

const insertSearchOption = (option: string) => {
  searchQuery.value += option
}

const monthNames = [
  '1月', '2月', '3月', '4月', '5月', '6月',
  '7月', '8月', '9月', '10月', '11月', '12月',
]

const daysOfWeek = ['日', '月', '火', '水', '木', '金', '土']

const days = computed(() => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const firstDay = new Date(year, month, 1)
    const lastDay = new Date(year, month + 1, 0)
    const daysInMonth = lastDay.getDate()
    const startingDayOfWeek = firstDay.getDay()

    const daysArr = []

    for (let i = 0; i < startingDayOfWeek; i++) {
      daysArr.push({ day: '', isCurrentMonth: false, events: [] })
    }

    for (let i = 1; i <= daysInMonth; i++) {
      const events = getEventsForDate(i, month)
      daysArr.push({ day: i, isCurrentMonth: true, events })
    }

    return daysArr
})

const weekDays = computed(() => {
    const dayOfWeek = currentDate.value.getDay()
    const weekStart = new Date(currentDate.value)
    weekStart.setDate(currentDate.value.getDate() - dayOfWeek)

    const daysArr = []
    for (let i = 0; i < 7; i++) {
      const day = new Date(weekStart)
      day.setDate(weekStart.getDate() + i)
      daysArr.push({
        date: day,
        day: day.getDate(),
        isToday: day.toDateString() === new Date(2025, 9, 14).toDateString(),
        events: getEventsForDate(day.getDate(), day.getMonth()),
      })
    }
    return daysArr
})

const yearMonths = computed(() => {
    const year = currentDate.value.getFullYear()
    const months = []
    for (let month = 0; month < 12; month++) {
      const firstDay = new Date(year, month, 1)
      const lastDay = new Date(year, month + 1, 0)
      const daysInMonth = lastDay.getDate()
      const startingDayOfWeek = firstDay.getDay()

      const daysArr = []

      for (let i = 0; i < startingDayOfWeek; i++) {
        daysArr.push({ day: 0, hasEvent: false })
      }

      for (let i = 1; i <= daysInMonth; i++) {
        const events = getEventsForDate(i, month)
        daysArr.push({ day: i, hasEvent: events.length > 0 })
      }

      months.push({
        month,
        monthName: monthNames[month],
        days: daysArr,
      })
    }
    return months
})

const previousPeriod = () => {
  if (viewMode.value === 'year') {
    currentDate.value = new Date(currentDate.value.getFullYear() - 1, 0, 1)
  } else if (viewMode.value === 'month') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  } else if (viewMode.value === 'week') {
    const newDate = new Date(currentDate.value)
    newDate.setDate(currentDate.value.getDate() - 7)
    currentDate.value = newDate
  } else {
    const newDate = new Date(currentDate.value)
    newDate.setDate(currentDate.value.getDate() - 1)
    currentDate.value = newDate
  }
}

const nextPeriod = () => {
  if (viewMode.value === 'year') {
    currentDate.value = new Date(currentDate.value.getFullYear() + 1, 0, 1)
  } else if (viewMode.value === 'month') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  } else if (viewMode.value === 'week') {
    const newDate = new Date(currentDate.value)
    newDate.setDate(currentDate.value.getDate() + 7)
    currentDate.value = newDate
  } else {
    const newDate = new Date(currentDate.value)
    newDate.setDate(currentDate.value.getDate() + 1)
    currentDate.value = newDate
  }
}

const getDateRangeText = computed(() => {
  if (viewMode.value === 'year') {
    return `${currentDate.value.getFullYear()}年`
  } else if (viewMode.value === 'month') {
    return `${currentDate.value.getFullYear()}年 ${monthNames[currentDate.value.getMonth()]}`
  } else if (viewMode.value === 'week') {
    const weekStart = weekDays.value[0].date
    const weekEnd = weekDays.value[6].date
    return `${weekStart.getMonth() + 1}月${weekStart.getDate()}日 - ${weekEnd.getMonth() + 1}月${weekEnd.getDate()}日`
  } else {
    return `${currentDate.value.getFullYear()}年${currentDate.value.getMonth() + 1}月${currentDate.value.getDate()}日`
  }
})

const timeSlots = [
  '09:00', '10:00', '11:00', '12:00', '13:00',
  '14:00', '15:00', '16:00', '17:00', '18:00',
]

const handleTodayClick = () => {
  const today = new Date(2025, 9, 14)
  currentDate.value = today

  if (viewMode.value === 'year') {
    viewMode.value = 'month'
  } else if (viewMode.value === 'month') {
    viewMode.value = 'week'
  } else if (viewMode.value === 'week') {
    viewMode.value = 'day'
  }
}

</script>

<template>
  <div class="h-screen bg-gray-50 flex flex-col">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Button
              variant="ghost"
              size="icon"
              @click="router.get('/')"
            >
              <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="flex items-center gap-2">
              <CalendarIcon class="h-6 w-6 text-blue-600" />
              <div>
                <h1 class="text-blue-600">部署内共有カレンダー</h1>
                <p class="text-xs text-gray-500">
                  予定の衝突を一括管理
                </p>
              </div>
            </div>
            <Badge variant="outline" class="gap-1">
              <Users class="h-3 w-3" />
              4名で共有
            </Badge>
          </div>
          <Button
            class="gap-2"
            @click="router.get('/create-event')"
          >
            <Plus class="h-4 w-4" />
            予定追加
          </Button>
        </div>

        <div class="max-w-2xl">
          <Popover v-model:open="isSearchFocused">
            <PopoverTrigger as-child>
              <div
                class="relative"
                @mouseenter="isSearchFocused = true"
                @mouseleave="isSearchFocused = false"
              >
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                <Input
                  type="text"
                  placeholder="日付、名前、キーワードで検索... (例: 2025-10-20, 田中, 会議)"
                  class="pl-9 pr-4 py-2 w-full"
                  v-model="searchQuery"
                />
              </div>
            </PopoverTrigger>
            <PopoverContent
              class="w-80 p-2"
              align="start"
              side="bottom"
              @mouseenter="isSearchFocused = true"
              @mouseleave="isSearchFocused = false"
            >
                <div class="space-y-1">
                  <p class="text-xs text-gray-500 px-2 py-1">
                    検索オプション
                  </p>
                  <button @click="() => { insertSearchOption('タイトル:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span class="text-blue-600">T</span>
                    <span>タイトル</span>
                  </button>
                  <button @click="() => { insertSearchOption('重要度:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span class="text-red-600">!!</span>
                    <span>重要度</span>
                  </button>
                  <button @click="() => { insertSearchOption('日付:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span>🗓️</span>
                    <span>日付</span>
                  </button>
                  <button @click="() => { insertSearchOption('終了日:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span class="text-orange-600">End</span>
                    <span>ある日付までの予定</span>
                  </button>
                  <button @click="() => { insertSearchOption('開始日:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span class="text-green-600">Start</span>
                    <span>ある日付からの予定</span>
                  </button>
                  <button @click="() => { insertSearchOption('ジャンル:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span class="text-purple-600">#</span>
                    <span>ジャンル</span>
                  </button>
                  <button @click="() => { insertSearchOption('メンバー:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span>👤</span>
                    <span>メンバー</span>
                  </button>
                  <button @click="() => { insertSearchOption('会議室:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span>🚪</span>
                    <span>会議室</span>
                  </button>
                  <button @click="() => { insertSearchOption('メモ:'); isSearchFocused = false; }" class="w-full flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded text-sm">
                    <span>📝</span>
                    <span>メモ</span>
                  </button>
                </div>
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </header>

    <main class="flex-1 overflow-hidden p-6">
      <Card class="h-full flex flex-col">
        <CardHeader>
          <div class="flex items-center justify-between gap-4">
            <Tabs v-model="viewMode" class="flex-1">
              <TabsList class="grid w-full max-w-[400px] grid-cols-4">
                <TabsTrigger value="year">年</TabsTrigger>
                <TabsTrigger value="month">月</TabsTrigger>
                <TabsTrigger value="week">週</TabsTrigger>
                <TabsTrigger value="day">日</TabsTrigger>
              </TabsList>
            </Tabs>

            <div class="flex items-center gap-3">
              <Button variant="outline" size="sm" @click="previousPeriod">
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <div class="min-w-[200px] text-center">
                {{ getDateRangeText }}
              </div>
              <Button variant="outline" size="sm" @click="nextPeriod">
                <ChevronRight class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardHeader>

        <CardContent class="flex-1 overflow-auto relative">
          <Button size="sm" variant="outline" class="absolute bottom-4 right-4 z-10 shadow-lg gap-2" @click="handleTodayClick">
            <CalendarIcon class="h-4 w-4" />
            今日
          </Button>

          <div v-if="viewMode === 'year'" class="grid grid-cols-3 md:grid-cols-4 gap-4">
            <div v-for="(monthData, monthIndex) in yearMonths" :key="monthIndex" class="border border-gray-200 rounded-lg overflow-hidden bg-white hover:shadow-md transition-shadow cursor-pointer" @click="() => { currentDate = new Date(currentDate.getFullYear(), monthData.month, 1); viewMode = 'month'; }">
              <div class="bg-gray-50 border-b border-gray-200 p-2 text-center">
                <h4 class="text-sm">{{ monthData.monthName }}</h4>
              </div>
              <div class="p-2">
                <div class="grid grid-cols-7 gap-0.5 mb-1">
                  <div v-for="(day, index) in ['日', '月', '火', '水', '木', '金', '土']" :key="index" :class="['text-xs text-center', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : 'text-gray-600']">
                    {{ day }}
                  </div>
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                  <div v-for="(dayObj, dayIndex) in monthData.days" :key="dayIndex" :class="['text-xs text-center p-1 rounded', dayObj.day === 0 ? '' : dayObj.hasEvent ? 'bg-blue-500 text-white' : 'hover:bg-gray-100']">
                    {{ dayObj.day > 0 ? dayObj.day : '' }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="viewMode === 'month'" class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                <div v-for="(day, index) in daysOfWeek" :key="index" :class="['p-2 text-center border-r border-gray-200 last:border-r-0', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : '']">
                    {{ day }}
                </div>
            </div>
            <div class="grid grid-cols-7">
                <div v-for="(dayObj, index) in days" :key="index" :class="['min-h-[100px] p-2 border-r border-b border-gray-200 last:border-r-0', !dayObj.isCurrentMonth ? 'bg-gray-50' : 'bg-white hover:bg-gray-50', dayObj.day === 14 ? 'ring-2 ring-blue-500 ring-inset' : '']">
                    <template v-if="dayObj.day">
                        <div :class="['text-sm mb-1', dayObj.day === 14 ? 'bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center' : '']">
                            {{ dayObj.day }}
                        </div>
                        <div class="space-y-1">
                            <div v-for="(event, eventIndex) in dayObj.events" :key="eventIndex" :class="[event.color, 'text-white text-xs px-1 py-0.5 rounded truncate cursor-pointer hover:opacity-80']" :title="`${event.title} (${event.assignee})`">
                                {{ event.title }}
                            </div>
                        </div>
                    </template>
                </div>
            </div>
          </div>

          <div v-if="viewMode === 'week'" class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="grid grid-cols-8 bg-gray-50 border-b border-gray-200">
                <div class="p-2 text-center border-r border-gray-200">
                    <Clock class="h-4 w-4 mx-auto text-gray-500" />
                </div>
                <div v-for="(dayObj, index) in weekDays" :key="index" :class="['p-2 text-center border-r border-gray-200 last:border-r-0', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : '', dayObj.isToday ? 'bg-blue-100' : '']">
                    <div>{{ daysOfWeek[index] }}</div>
                    <div :class="['text-xs', dayObj.isToday ? 'bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center mx-auto mt-1' : 'mt-1']">
                        {{ dayObj.day }}
                    </div>
                </div>
            </div>
            <div class="max-h-[500px] overflow-y-auto">
                <div v-for="(time, timeIndex) in timeSlots" :key="timeIndex" class="grid grid-cols-8 border-b border-gray-200">
                    <div class="p-2 text-xs text-gray-500 text-center border-r border-gray-200 bg-gray-50">
                        {{ time }}
                    </div>
                    <div v-for="(dayObj, dayIndex) in weekDays" :key="dayIndex" :class="['min-h-[60px] p-1 border-r border-gray-200 last:border-r-0', dayObj.isToday ? 'bg-blue-50/30' : 'bg-white', 'hover:bg-gray-50']">
                        <div v-for="(event, eventIndex) in dayObj.events.filter(e => e.time === time)" :key="eventIndex" :class="[event.color, 'text-white text-xs p-2 rounded mb-1 cursor-pointer hover:opacity-80']">
                            <div>{{ event.title }}</div>
                            <div class="text-xs opacity-90">
                                {{ event.assignee }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <div v-if="viewMode === 'day'" class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3>{{ daysOfWeek[currentDate.getDay()] }}曜日</h3>
                        <p class="text-sm text-gray-600">
                            {{ currentDate.getFullYear() }}年{{ currentDate.getMonth() + 1 }}月{{ currentDate.getDate() }}日
                        </p>
                    </div>
                    <Badge :variant="currentDate.getDate() === 14 ? 'default' : 'outline'">
                        {{ currentDate.getDate() === 14 ? '今日' : '' }}
                    </Badge>
                </div>
            </div>
            <div class="max-h-[500px] overflow-y-auto">
                <div v-for="(time, timeIndex) in timeSlots" :key="timeIndex" class="flex border-b border-gray-200 hover:bg-gray-50">
                    <div class="w-20 p-3 text-sm text-gray-500 border-r border-gray-200 bg-gray-50">
                        {{ time }}
                    </div>
                    <div class="flex-1 p-3 min-h-[80px]">
                        <div v-for="(event, eventIndex) in getEventsForDate(currentDate.getDate(), currentDate.getMonth()).filter(e => e.time === time)" :key="eventIndex" :class="[event.color, 'text-white p-3 rounded mb-2 cursor-pointer hover:opacity-90']">
                            <div class="flex items-center justify-between mb-1">
                                <span>{{ event.title }}</span>
                                <Badge variant="secondary" class="text-xs">
                                    {{ event.assignee }}
                                </Badge>
                            </div>
                            <div class="text-xs opacity-90">
                                {{ time }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap gap-3 text-xs">
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-blue-500 rounded"></div>
              <span>今日</span>
            </div>
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-purple-500 rounded"></div>
              <span>会議</span>
            </div>
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-orange-500 rounded"></div>
              <span>期限</span>
            </div>
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-green-500 rounded"></div>
              <span>MTG</span>
            </div>
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-red-500 rounded"></div>
              <span>重要</span>
            </div>
            <div class="flex items-center gap-1">
              <div class="w-3 h-3 bg-teal-500 rounded"></div>
              <span>有給</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </main>
    <EventDetailDialog :event="selectedEvent" :open="selectedEvent !== null" @update:open="(isOpen) => !isOpen && (selectedEvent = null)" />
  </div>
</template>
