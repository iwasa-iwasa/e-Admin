<script setup lang="ts">
import { ref, computed } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, Users } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import { all } from 'axios'

const props = defineProps<{
    events: App.Models.Event[]
}>()

/**
 * ISO形式の日付文字列と、時刻文字列を結合します。
 * @param {string} fullDateStr - 元の日付時刻文字列 (例: '2025-10-16T15:00:00.000000Z')
 * @param {string} timeStr - 結合したい時刻文字列 (例: '17:00:00')
 * @returns {string} 結合された日付時刻文字列 (例: '2025-10-16T17:00:00')
 */
function combineDateAndTime(fullDateStr:string, timeStr:string): string {
  // 'T' を基準に文字列を分割し、日付部分（[0]）を取得します
  const datePart = fullDateStr.split('T')[0];
  
  // 取得した日付部分と、新しい時刻の文字列を 'T' で結合します
  return `${datePart}T${timeStr}`;
}

const viewMode = ref('dayGridMonth')
const selectedEvent = ref<App.Models.Event | null>(null)
const isCreateEventDialogOpen = ref(false)
const fullCalendar = ref<any>(null)
const calendarTitle = ref('')

const getEventColor = (category: string, importance: string) => {
    const categoryColorMap: { [key: string]: string } = {
        '会議': '#8b5cf6', // purple
        '期限': '#f97316', // orange
        'MTG': '#22c55e', // green
        '重要': '#ef4444', // red
        '有給': '#14b8a6', // teal
        '業務': '#3b82f6', // blue
        '来客': '#eab308', // yellow
        '報告': '#6366f1', // indigo
        '研修': '#ec4899', // pink
    };
    if (importance === '高') return '#ef4444'; // red
    return categoryColorMap[category] || '#6b7280'; // gray
}

const legendItems = [
    { label: '会議', color: '#8b5cf6' },
    { label: '期限', color: '#f97316' },
    { label: 'MTG', color: '#22c55e' },
    { label: '重要', color: '#ef4444' },
    { label: '休暇', color: '#14b8a6' },
    { label: '業務', color: '#3b82f6' },
];

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin],
  initialView: viewMode.value,
  headerToolbar: false,
  events: props.events.map(event => ({
    id: event.event_id,
    title: event.title,
    start: combineDateAndTime(event.start_date,event.start_time ? event.start_time : '00:00:00'),
    end: combineDateAndTime(event.end_date,event.end_time ? event.end_time : '00:00:00'),
    backgroundColor: getEventColor(event.category, event.importance),
    borderColor: getEventColor(event.category, event.importance),
    extendedProps: event,
    allDay: event.is_all_day,
  })),
  locale: 'ja',
  buttonText: {
    today: '今日',
  },
  eventClick: (info: any) => {
    selectedEvent.value = info.event.extendedProps
  },
  datesSet: (info: any) => {
    calendarTitle.value = info.view.title
    viewMode.value = info.view.type
  }
}))

// console.log(props.events)

const previousPeriod = () => {
  fullCalendar.value?.getApi().prev()
}

const nextPeriod = () => {
  fullCalendar.value?.getApi().next()
}

const handleTodayClick = () => {
  fullCalendar.value?.getApi().today()
}

const changeView = (view: string) => {
  viewMode.value = view
  fullCalendar.value?.getApi().changeView(view)
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
        <Tabs :model-value="viewMode" @update:model-value="changeView" class="flex-1">
          <TabsList class="grid w-full max-w-[400px] grid-cols-4">
            <TabsTrigger value="multiMonthYear">年</TabsTrigger>
            <TabsTrigger value="dayGridMonth">月</TabsTrigger>
            <TabsTrigger value="timeGridWeek">週</TabsTrigger>
            <TabsTrigger value="timeGridDay">日</TabsTrigger>
          </TabsList>
        </Tabs>

        <div class="flex items-center gap-3">
          <Button variant="outline" size="sm" @click="previousPeriod">
            <ChevronLeft class="h-4 w-4" />
          </Button>
          <div class="min-w-[200px] text-center font-semibold">
            {{ calendarTitle }}
          </div>
          <Button variant="outline" size="sm" @click="nextPeriod">
            <ChevronRight class="h-4 w-4" />
          </Button>
        </div>
        <Button variant="outline" size="sm" @click="handleTodayClick">今日</Button>
      </div>
    </CardHeader>

    <CardContent class="flex-1 overflow-auto relative flex flex-col">
      <div class="flex-1">
        <FullCalendar ref="fullCalendar" :options="calendarOptions" class="h-full" />
      </div>
      <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">
          <div v-for="item in legendItems" :key="item.label" class="flex items-center gap-1.5">
            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color }"></div>
            <span>{{ item.label }}</span>
          </div>
      </div>
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

<style>
.fc .fc-toolbar.fc-header-toolbar {
    margin: 0;
    padding: 0 0 1rem;
}
.fc-dayGridYear-view .fc-daygrid-day-frame {
    padding: 2px;
}
.fc-dayGridYear-view .fc-daygrid-day-top {
    justify-content: center;
}
.fc-day-today {
  background-color: hsl(var(--primary) / 0.1) !important;
}
.fc-event {
    cursor: pointer;
}
</style>