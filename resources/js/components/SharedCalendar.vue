<script setup lang="ts">
import { ref, computed } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'
import { CalendarOptions } from '@fullcalendar/core'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, Users, ArrowLeft } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import { all } from 'axios'
import ScrollArea from './ui/scroll-area/ScrollArea.vue'

const props = defineProps<{
    events: App.Models.Event[]
    showBackButton?: boolean
}>()

const viewMode = ref('dayGridMonth')
const selectedEvent = ref<App.Models.Event | null>(null)
const isEventFormOpen = ref(false)
const editingEvent = ref<App.Models.Event | null>(null)
const fullCalendar = ref<any>(null)
const calendarTitle = ref('')
const hoveredEvent = ref<any>(null)
const hoverPosition = ref({ x: 0, y: 0 })

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
        '休暇': '#14b8a6', // teal
        '業務': '#3b82f6', // blue
        '来客': '#eab308', // yellow
        '出張': '#22c55e', // green
    };
    if (importance === '高') return '#ef4444'; // red
    return categoryColorMap[category] || '#6b7280'; // gray
}

const legendItems = [
    { label: '会議', color: '#8b5cf6' },
    { label: '休暇', color: '#14b8a6' },
    { label: '業務', color: '#3b82f6' },
    { label: '来客', color: '#eab308' },
    { label: '出張', color: '#22c55e' },
];

const calendarOptions = computed((): CalendarOptions => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin, rrulePlugin],
  initialView: viewMode.value,
  headerToolbar: false,
  // timeZone: 'UTC', // REMOVED: Revert to local timezone to fix interpretation issues.
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

    // Convert UTC date strings from backend to local Date objects
    // event.start_date and event.end_date are like "YYYY-MM-DDTHH:mm:ss.SSSSSSZ"
    const localStartDate = new Date(event.start_date);
    const localEndDate = new Date(event.end_date);

    // Format time to ensure HH:mm:ss format (remove seconds if needed, but keep consistent)
    const formatTime = (time: string | null): string => {
      if (!time) return '00:00:00';
      const parts = time.split(':');
      return parts.length === 2 ? `${time}:00` : time.substring(0, 8);
    };
    const fcStartTime = formatTime(event.start_time);
    const fcEndTime = formatTime(event.end_time);

    // Get YYYY-MM-DD string from local Date objects
    const getLocalYMD = (date: Date): string => {
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    const fcStartDate = getLocalYMD(localStartDate);
    const fcEndDate = getLocalYMD(localEndDate);

    if (event.rrule) {
      // For recurring events, the rrule object itself contains dtstart which is UTC.
      // FullCalendar handles this internally, but if dtstart needs to be local,
      // the rrule object would need to be reconstructed here.
      // For now, assume FullCalendar's rrule plugin handles UTC dtstart correctly
      // when the calendar's timeZone is 'local' (default).
      return {
        ...commonProps,
        rrule: event.rrule,
        duration: event.duration,
      };
    }

    if (event.is_all_day) {
        // For all-day events, FullCalendar's `end` is exclusive.
        // We need to specify the day AFTER the actual end date.
        localEndDate.setDate(localEndDate.getDate() + 1);
        return {
            ...commonProps,
            start: fcStartDate,
            end: getLocalYMD(localEndDate),
        };
    }

    // For timed events, combine local date and time strings
    return {
      ...commonProps,
      start: `${fcStartDate}T${fcStartTime}`,
      end: `${fcEndDate}T${fcEndTime}`,
    };
  }),
  locale: 'ja',
  buttonText: {
    today: '今日',
  },
  eventClick: (info: any) => {
    selectedEvent.value = info.event.extendedProps
  },
  eventMouseEnter: (info: any) => {
    hoveredEvent.value = info.event.extendedProps
    const rect = info.el.getBoundingClientRect()
    hoverPosition.value = { x: rect.left + rect.width / 2, y: rect.top - 10 }
  },
  eventMouseLeave: () => {
    hoveredEvent.value = null
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
    <div class="p-4">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <Button v-if="showBackButton" variant="ghost" size="icon" @click="router.get('/')" class="mr-1">
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <CalendarIcon class="h-6 w-6 text-blue-700" />
          <CardTitle>部署内共有カレンダー</CardTitle>
        </div>
        <Button
          variant="outline"
          class="gap-2"
          @click="openCreateDialog"
        >
          <Plus class="h-4 w-4" />
          新規作成
        </Button>
      </div>

      <div class="flex items-center justify-between gap-4">
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
    </div>

    <CardContent class="flex flex-1 overflow-y-auto">
      <ScrollArea class="h-full">
      <div class="w-full h-full flex-1">
        <FullCalendar ref="fullCalendar" :options="calendarOptions"/>
      </div>
      </ScrollArea>
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

    <!-- ホバー表示 -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="hoveredEvent"
        class="fixed z-50 bg-white border border-gray-300 rounded-lg shadow-lg p-3 max-w-xs pointer-events-none"
        :style="{ left: hoverPosition.x + 'px', top: hoverPosition.y + 'px', transform: 'translateX(-50%) translateY(-100%)' }"
      >
        <div class="space-y-2">
          <div class="font-semibold text-sm">{{ hoveredEvent.title }}</div>
          <div v-if="hoveredEvent.progress !== undefined && hoveredEvent.progress !== null" class="flex items-center gap-2">
            <span class="text-xs text-gray-500">進捗:</span>
            <div 
              class="flex-1 h-1.5 rounded-full overflow-hidden"
              :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${hoveredEvent.progress}%, #e5e7eb ${hoveredEvent.progress}%, #e5e7eb 100%)` }"
            >
            </div>
            <span class="text-xs text-gray-600">{{ hoveredEvent.progress }}%</span>
          </div>
          <div class="text-xs text-gray-600">
            <span class="text-gray-500">締切:</span> 
            {{ new Date(hoveredEvent.end_date).toLocaleDateString('ja-JP') }}
            {{ hoveredEvent.end_time && !hoveredEvent.is_all_day ? ' ' + hoveredEvent.end_time.slice(0, 5) : '' }}
          </div>
        </div>
      </div>
    </Transition>
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