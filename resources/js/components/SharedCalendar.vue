<script setup lang="ts">
import { ref, computed } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'
import { CalendarOptions } from '@fullcalendar/core'
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

const viewMode = ref('dayGridMonth')
const selectedEvent = ref<App.Models.Event | null>(null)
const isEventFormOpen = ref(false)
const editingEvent = ref<App.Models.Event | null>(null)
const fullCalendar = ref<any>(null)
const calendarTitle = ref('')

const openCreateDialog = () => {
  editingEvent.value = null
  isEventFormOpen.value = true
}

const openEditDialog = (eventId: number) => {
  const event = props.events.find(e => e.event_id === eventId)
  if (event) {
    editingEvent.value = event
    isEventFormOpen.value = true
  }
}

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
    { label: '有休', color: '#14b8a6' },
    { label: '業務', color: '#3b82f6' },
];

const calendarOptions = computed((): CalendarOptions => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin, rrulePlugin],
  initialView: viewMode.value,
  headerToolbar: false,
  contentHeight: 'auto',
  events: props.events.map(event => {
    const commonProps = {
      id: String(event.event_id),
      title: event.title,
      backgroundColor: getEventColor(event.category, event.importance),
      borderColor: getEventColor(event.category, event.importance),
      extendedProps: event,
      allDay: event.is_all_day,
    };

    if (event.rrule) {
      return {
        ...commonProps,
        rrule: event.rrule,
        duration: event.duration,
      };
    }

    // Non-recurring events
    if (event.is_all_day) {
        // For all-day events, the end date is exclusive.
        // Add one day to the end date for it to display correctly.
        // IMPORTANT: Create date in UTC to avoid timezone shifts.
        const startDate = new Date(event.start_date + 'T00:00:00Z');
        const endDate = new Date(event.end_date + 'T00:00:00Z');
        endDate.setUTCDate(endDate.getUTCDate() + 1);
        return {
            ...commonProps,
            start: startDate.toISOString().split('T')[0], // Format back to 'YYYY-MM-DD' in UTC
            end: endDate.toISOString().split('T')[0], // Format back to 'YYYY-MM-DD' in UTC
        };
    }

    // For time-based events, use local timezone (no timezone suffix)
    // FullCalendar will interpret date-time strings without timezone as local time
    const startDateStr = event.start_date.split('T')[0];
    const endDateStr = event.end_date.split('T')[0];
    const startTime = event.start_time || '00:00:00';
    const endTime = event.end_time || '00:00:00';
    
    // Format time to ensure HH:mm:ss format (remove seconds if needed, but keep consistent)
    const formatTime = (time: string) => {
      const parts = time.split(':');
      if (parts.length === 2) return time + ':00'; // Add seconds if missing
      return time.substring(0, 8); // Ensure HH:mm:ss format
    };
    
    return {
      ...commonProps,
      start: `${startDateStr}T${formatTime(startTime)}`,
      end: `${endDateStr}T${formatTime(endTime)}`,
    };
  }),
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
  },
}))

const previousPeriod = () => {
  fullCalendar.value?.getApi().prev()
}

const nextPeriod = () => {
  fullCalendar.value?.getApi().next()
}

const handleTodayClick = () => {
  fullCalendar.value?.getApi().today()
}

const changeView = (view: any) => {
  viewMode.value = view
  fullCalendar.value?.getApi().changeView(view)
}



</script>

<template>
  <Card class="flex flex-col h-full">
    <CardHeader>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <CalendarIcon class="h-6 w-6 text-blue-600" />
          <CardTitle>部署内共有カレンダー</CardTitle>
        </div>
        <Button
          variant="outline"
          size="sm"
          class="gap-2"
          @click="openCreateDialog"
        >
          <Plus class="h-4 w-4" />
          新規作成
        </Button>
      </div>

      <div class="flex items-center justify-between mt-4 gap-4">
        <Tabs :model-value="viewMode" @update:model-value="changeView" class="flex-1">
          <TabsList class="grid w-full max-w-[400px] grid-cols-4 bg-gray-100">
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

    <CardContent class="flex flex-1 overflow-y-auto">
      <div class="w-full h-full flex-1">
        <FullCalendar ref="fullCalendar" :options="calendarOptions"/>
      </div>
    </CardContent>

    <CardContent>
    <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs mt-1">
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
      @edit="openEditDialog"
    />

    <CreateEventDialog
      :key="editingEvent ? editingEvent.event_id : 'create'"
      :open="isEventFormOpen"
      @update:open="isEventFormOpen = $event"
      :event="editingEvent"
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