<script setup lang="ts">
import { formatDate } from '@/lib/utils'
import { ref, computed } from 'vue'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, Users, Clock } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
const props = defineProps<{
    events: App.Models.Event[]
}>()

const currentDate = ref(new Date(2025, 9, 14))
const viewMode = ref<ViewMode>('month')
const selectedEvent = ref<App.Models.Event | null>(null)
const isCreateEventDialogOpen = ref(false)

const monthNames = [
  '1月', '2月', '3月', '4月', '5月', '6月',
  '7月', '8月', '9月', '10月', '11月', '12月'
]

const daysOfWeek = ['日', '月', '火', '水', '木', '金', '土']


const getEventColor = (event: App.Models.Event) => {
    const categoryColorMap: { [key: string]: string } = {
        '会議': 'bg-purple-500',
        '期限': 'bg-orange-500',
        'MTG': 'bg-green-500',
        '重要': 'bg-red-500',
        '休暇': 'bg-teal-500',
        '業務': 'bg-blue-500',
        '来客': 'bg-yellow-500',
        '報告': 'bg-indigo-500',
        '研修': 'bg-pink-500',
    };
    if (event.importance === '高') return 'bg-red-500';
    return categoryColorMap[event.category] || 'bg-gray-500';
}

const getEventsForDate = (day: number, month: number, year: number) => {
  const date = new Date(year, month, day);
  date.setHours(0, 0, 0, 0);
  return props.events.filter(event => {
    const startDate = new Date(event.start_date);
    const endDate = new Date(event.end_date);
    startDate.setHours(0, 0, 0, 0);
    endDate.setHours(0, 0, 0, 0);
    return date >= startDate && date <= endDate;
  });
}

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
      const events = getEventsForDate(i, month, year)
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
        events: getEventsForDate(day.getDate(), day.getMonth(), day.getFullYear())
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
        const events = getEventsForDate(i, month, year)
        daysArr.push({ day: i, hasEvent: events.length > 0 })
      }
      
      months.push({
        month,
        monthName: monthNames[month],
        days: daysArr
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
  '14:00', '15:00', '16:00', '17:00', '18:00'
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
  <Card class="h-full flex flex-col">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <CalendarIcon class="h-6 w-6 text-blue-600" />
          <CardTitle>部署内共有カレンダー</CardTitle>
          <Badge variant="outline" class="gap-1">
            <Users class="h-3 w-3" />
            4名で共有
          </Badge>
        </div>
        <Button
          variant="outline"
          size="sm"
          class="gap-2"
          @click="isCreateEventDialogOpen = true"
        >
          <Plus class="h-4 w-4" />
          新規作成
        </Button>
      </div>

      <div class="flex items-center justify-between mt-4 gap-4">
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
      <TooltipProvider>
        <Button
          size="sm"
          variant="outline"
          class="absolute bottom-4 bg-white right-4 z-10 shadow-lg gap-2"
          @click="handleTodayClick"
        >
          <CalendarIcon class="h-4 w-4" />
          今日
        </Button>

        <div v-if="viewMode === 'year'" class="grid grid-cols-3 md:grid-cols-4 gap-4">
          <div
            v-for="(monthData, monthIndex) in yearMonths"
            :key="monthIndex"
            class="border border-gray-200 rounded-lg overflow-hidden bg-white hover:shadow-md transition-shadow cursor-pointer"
            @click="() => { currentDate = new Date(currentDate.getFullYear(), monthData.month, 1); viewMode = 'month'; }"
          >
            <div class="bg-gray-50 border-b border-gray-200 p-2 text-center">
              <h4 class="text-sm">{{ monthData.monthName }}</h4>
            </div>
            <div class="p-2">
              <div class="grid grid-cols-7 gap-0.5 mb-1">
                <div
                  v-for="(day, index) in ['日', '月', '火', '水', '木', '金', '土']"
                  :key="index"
                  :class="['text-xs text-center', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : 'text-gray-600']"
                >
                  {{ day }}
                </div>
              </div>
              <div class="grid grid-cols-7 gap-0.5">
                <div
                  v-for="(dayObj, dayIndex) in monthData.days"
                  :key="dayIndex"
                  :class="['text-xs text-center p-1 rounded', dayObj.day === 0 ? '' : dayObj.hasEvent ? 'bg-blue-500 text-white' : 'hover:bg-gray-100']"
                >
                  {{ dayObj.day > 0 ? dayObj.day : '' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="viewMode === 'month'" class="border border-gray-200 rounded-lg overflow-hidden">
          <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
            <div
              v-for="(day, index) in daysOfWeek"
              :key="index"
              :class="['p-2 text-center border-r border-gray-200 last:border-r-0', index === 0 ? 'text-red-600' : index === 6 ? 'text-blue-600' : '']"
            >
              {{ day }}
            </div>
          </div>
          <div class="grid grid-cols-7">
            <div
              v-for="(dayObj, index) in days"
              :key="index"
              :class="['min-h-[100px] p-2 border-r border-b border-gray-200 last:border-r-0', !dayObj.isCurrentMonth ? 'bg-gray-50' : 'bg-white hover:bg-gray-50', dayObj.day === 14 ? 'ring-2 ring-blue-500 ring-inset' : '']"
            >
              <template v-if="dayObj.day">
                <div :class="['text-sm mb-1', dayObj.day === 14 ? 'bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center' : '']">
                  {{ dayObj.day }}
                </div>
                <div class="space-y-1">
                  <template v-for="(event, eventIndex) in dayObj.events">
                    <Tooltip v-if="(new Date(event.end_date) > new Date(event.start_date))" :key="eventIndex">
                      <TooltipTrigger as-child>
                        <div
                          :class="[getEventColor(event), 'text-white text-xs px-1 py-0.5 rounded truncate cursor-pointer hover:opacity-80']"
                          @click="selectedEvent = event"
                        >
                          {{ event.title }}
                        </div>
                      </TooltipTrigger>
                      <TooltipContent>
                        <div class="text-sm">
                          <div class="mb-1">{{ event.title }}</div>
                          <div class="text-xs opacity-90">
                            期間: {{ formatDate(event.start_date) }} 〜 {{ formatDate(event.end_date) }}
                          </div>
                        </div>
                      </TooltipContent>
                    </Tooltip>
                    <div
                      v-else
                      :key="event.event_id"
                      :class="[getEventColor(event), 'text-white text-xs px-1 py-0.5 rounded truncate cursor-pointer hover:opacity-80']"
                      @click="selectedEvent = event"
                    >
                      {{ event.title }}
                    </div>
                  </template>
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
                      <template v-for="(event, eventIndex) in dayObj.events.filter(e => e.start_time && e.start_time.startsWith(time))">
                        <Tooltip v-if="(new Date(event.end_date) > new Date(event.start_date))" :key="eventIndex">
                          <TooltipTrigger as-child>
                            <div :class="[getEventColor(event), 'text-white text-xs p-2 rounded mb-1 cursor-pointer hover:opacity-80']" @click="selectedEvent = event">
                              <div>{{ event.title }}</div>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent>
                            <div class="text-sm">
                              <div class="mb-1">{{ event.title }}</div>
                              <div class="text-xs opacity-90">
                                期間: {{ formatDate(event.start_date) }} 〜 {{ formatDate(event.end_date) }}
                              </div>
                            </div>
                          </TooltipContent>
                        </Tooltip>
                        <div v-else :key="event.event_id" :class="[getEventColor(event), 'text-white text-xs p-2 rounded mb-1 cursor-pointer hover:opacity-80']" @click="selectedEvent = event">
                          <div>{{ event.title }}</div>
                        </div>
                      </template>
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
                      <template v-for="(event, eventIndex) in getEventsForDate(currentDate.getDate(), currentDate.getMonth(), currentDate.getFullYear()).filter(e => e.start_time && e.start_time.startsWith(time))">
                        <Tooltip v-if="(new Date(event.end_date) > new Date(event.start_date))" :key="eventIndex">
                          <TooltipTrigger as-child>
                            <div :class="[getEventColor(event), 'text-white p-3 rounded mb-2 cursor-pointer hover:opacity-90']" @click="selectedEvent = event">
                              <div class="flex items-center justify-between mb-1">
                                <span>{{ event.title }}</span>
                              </div>
                              <div class="text-xs opacity-90">{{ time }}</div>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent>
                            <div class="text-sm">
                              <div class="mb-1">{{ event.title }}</div>
                              <div class="text-xs opacity-90">
                                期間: {{ formatDate(event.start_date) }} 〜 {{ formatDate(event.end_date) }}
                              </div>
                            </div>
                          </TooltipContent>
                        </Tooltip>
                        <div v-else :key="event.event_id" :class="[getEventColor(event), 'text-white p-3 rounded mb-2 cursor-pointer hover:opacity-90']" @click="selectedEvent = event">
                          <div class="flex items-center justify-between mb-1">
                            <span>{{ event.title }}</span>
                          </div>
                          <div class="text-xs opacity-90">{{ time }}</div>
                        </div>
                      </template>
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
      </TooltipProvider>
    </CardContent>

    <EventDetailDialog
      :event="selectedEvent"
      :open="selectedEvent !== null"
      @update:open="(isOpen) => !isOpen && (selectedEvent = null)"
    />

    <CreateEventDialog
      :open="isCreateEventDialogOpen"
      @update:open="isCreateEventDialogOpen = $event"
    />
  </Card>
</template>