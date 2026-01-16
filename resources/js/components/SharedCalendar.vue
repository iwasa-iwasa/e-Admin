<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import ScrollArea from './ui/scroll-area/ScrollArea.vue'
import { CATEGORY_COLORS, CATEGORY_LABELS, GENRE_FILTERS, getEventColor, CATEGORY_ITEMS } from '@/constants/calendar'

const DayViewGantt = defineAsyncComponent(() => import('@/components/DayViewGantt.vue'))
const WeekSummaryView = defineAsyncComponent(() => import('@/components/WeekSummaryView.vue'))
import SharedCalendarHeader from '@/components/SharedCalendarHeader.vue'

// Composables
import { useCalendarEvents } from '@/composables/calendar/useCalendarEvents'
import { useCalendarView } from '@/composables/calendar/useCalendarView'
import { useCalendarDom } from '@/composables/calendar/useCalendarDom'
import { useFullCalendarConfig } from '@/composables/calendar/useFullCalendarConfig'

const props = defineProps<{
    events: App.Models.Event[]
    showBackButton?: boolean
    filteredMemberId?: number | null
}>()

const fullCalendar = ref<any>(null)
const selectedEvent = ref<App.Models.Event | null>(null)
const isEventFormOpen = ref(false)
const editingEvent = ref<App.Models.Event | null>(null)
const currentEvents = ref<App.Models.Event[]>([])

// 1. Events Logic (only for filters state)
const { 
    searchQuery, 
    genreFilter, 
    canEditEvent,
    fetchEvents
} = useCalendarEvents()

// 2. View Logic
const { 
    viewMode, 
    currentDayViewDate, 
    currentWeekStart, 
    calendarTitle, 
    isTodayInViewForFullCalendar, 
    canGoBack, 
    todayButtonText,
    changeView, 
    previousPeriod, 
    nextPeriod, 
    goBackOneLevel, 
    handleTodayClick, 
    handleDateClickFromWeek,
    handleDateClick: handleDateClickFromCalendar,
    updateCalendarTitle 
} = useCalendarView(fullCalendar)

// 3. DOM Logic
const {
    hoveredEvent,
    hoveredEvents,
    hoverPosition,
    handleDatesSet,
    handleEventMouseEnter,
    handleEventMouseLeave,
    getMoreLinkClassNames,
    handleMoreLinkDidMount,
    handleDayCellDidMount
} = useCalendarDom(fullCalendar, viewMode, isTodayInViewForFullCalendar, calendarTitle)

// Component Specific Logic for Event Click (Navigation vs Selection)
const handleEventClick = (info: any) => {
    // 年表示では月遷移を優先
    if (viewMode.value === 'multiMonthYear') {
        const clickedDate = info.event.start
        viewMode.value = 'dayGridMonth'
        const api = fullCalendar.value?.getApi()
        if (api) {
            api.changeView('dayGridMonth')
            api.gotoDate(clickedDate)
        }
    } else {
        info.jsEvent.stopPropagation()
        selectedEvent.value = info.event.extendedProps
    }
}

const handleEventsFetched = (events: any[]) => {
    currentEvents.value = events
}

// 4. FullCalendar Config
const { calendarOptions } = useFullCalendarConfig(
    fetchEvents,
    computed(() => props.filteredMemberId),
    viewMode,
    fullCalendar,
    getEventColor,
    {
        eventClick: handleEventClick,
        dateClick: handleDateClickFromCalendar,
        eventMouseEnter: handleEventMouseEnter,
        eventMouseLeave: handleEventMouseLeave,
        datesSet: handleDatesSet,
        moreLinkClassNames: getMoreLinkClassNames,
        moreLinkDidMount: handleMoreLinkDidMount,
        dayCellDidMount: handleDayCellDidMount
    },
    handleEventsFetched
)

// Manual Fetch Logic for Custom Views (Day/Week)
const fetchEventsForCustomView = async () => {
    let startStr = ''
    let endStr = ''

    if (viewMode.value === 'timeGridDay') {
        // Pre-fetch: current day +/- 1 week
        const start = new Date(currentDayViewDate.value)
        start.setDate(start.getDate() - 7)
        start.setHours(0, 0, 0, 0)
        startStr = start.toISOString().split('T')[0]
        
        const end = new Date(currentDayViewDate.value)
        end.setDate(end.getDate() + 8) // +1 (next day) +7 (buffer)
        endStr = end.toISOString().split('T')[0]
    } else if (viewMode.value === 'timeGridWeek') {
        // Pre-fetch: current week +/- 2 weeks
        const start = new Date(currentWeekStart.value)
        start.setDate(start.getDate() - 14)
        start.setHours(0, 0, 0, 0)
        startStr = start.toISOString().split('T')[0]
        
        const end = new Date(currentWeekStart.value)
        end.setDate(end.getDate() + 21) // +7 (next week start) + 14 (buffer)
        endStr = end.toISOString().split('T')[0]
    } else {
        return // FullCalendar handles other views
    }

    const events = await fetchEvents(startStr, endStr, props.filteredMemberId)
    currentEvents.value = events
}

// Watch filters to refetch events
watch([searchQuery, genreFilter, () => props.filteredMemberId], () => {
    const api = fullCalendar.value?.getApi()
    if (api && (viewMode.value === 'dayGridMonth' || viewMode.value === 'multiMonthYear')) {
        api.refetchEvents()
    } else {
        fetchEventsForCustomView()
    }
})

// Watch view navigation for custom views
watch([viewMode, currentDayViewDate, currentWeekStart], () => {
    fetchEventsForCustomView()
}, { immediate: true })

// Helper Methods
const openCreateDialog = () => {
    editingEvent.value = null
    selectedEvent.value = null
    isEventFormOpen.value = true
}

const openEditDialog = (eventId: number) => {
    // Try to find in current events first
    let event = currentEvents.value.find(e => e.event_id === eventId)
    
    // If not found (might be outside range, but user clicked edit on something visible? should correspond to currentEvents)
    if (event) {
        selectedEvent.value = null
        editingEvent.value = event
        isEventFormOpen.value = true
    }
}

const handleDialogClose = (value: boolean) => {
    isEventFormOpen.value = value
    if (!value) {
        editingEvent.value = null
    }
}



const handleEventClickFromGantt = (event: App.Models.Event) => {
    selectedEvent.value = event
}

const handleEventHoverFromGantt = (event: App.Models.Event | null, position: { x: number, y: number }) => {
    hoveredEvent.value = event
    hoverPosition.value = position
}

// Highlight Logic
const page = usePage()
const highlightId = computed(() => (page.props as any).highlight)

watch(highlightId, (id) => {
    if (id) {
        nextTick(() => {
            const event = currentEvents.value.find(e => e.event_id === id)
            if (event) {
                selectedEvent.value = event
            }
        })
    }
}, { immediate: true })

// 検索アイコン展開用（削除済み：SharedCalendarHeaderへ移動）


let resizeHandler: (() => void) | null = null
let removeInertiaListener: (() => void) | null = null

onMounted(() => {
    // Resize handler
    resizeHandler = () => {
        const api = fullCalendar.value?.getApi()
        if (api) {
            api.updateSize()
        }
    }
    window.addEventListener('resize', resizeHandler)
    
    // Inertia success listener
    removeInertiaListener = router.on('success', () => {
        const api = fullCalendar.value?.getApi()
        if (api && (viewMode.value === 'dayGridMonth' || viewMode.value === 'multiMonthYear')) {
            api.refetchEvents()
        } else {
            fetchEventsForCustomView()
        }
    })
    
    // ResizeObserver for header (削除済み: useCalendarLayoutへ移動)
})

onUnmounted(() => {
    if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler)
    }
    if (removeInertiaListener) {
        removeInertiaListener()
    }
})

const activeScope = ref<'all'|'current'|'before'|'middle'|'after'>('current')

function handleSelectScope(scope: 'before' | 'middle' | 'after') {
  activeScope.value = scope
}

function handleScopeButtonClick(
  scope: 'all' | 'current' | 'before' | 'middle' | 'after'
) {
  activeScope.value = scope
}


</script>

<template>
    <Card class="flex flex-col h-full overflow-hidden min-w-0">
        <SharedCalendarHeader
            :show-back-button="showBackButton"
            :search-query="searchQuery"
            :genre-filter="genreFilter"
            :view-mode="viewMode"
            :calendar-title="calendarTitle"
            :today-button-text="todayButtonText"
            :can-go-back="canGoBack"
            :current-day-view-date="currentDayViewDate"
            :current-week-start="currentWeekStart"
            @update:search-query="searchQuery = $event"
            @update:genre-filter="genreFilter = $event"
            @update:view-mode="changeView"
            @create="openCreateDialog"
            @previous="previousPeriod"
            @next="nextPeriod"
            @today="handleTodayClick"
            @go-back-one-level="goBackOneLevel"
        />

        <CardContent class="flex flex-1 overflow-y-auto min-w-0">
            <ScrollArea class="h-full w-full min-w-0">
            <div class="w-full h-full flex-1">
                <DayViewGantt
                    v-if="viewMode === 'timeGridDay'"
                    :events="currentEvents"
                    :current-date="currentDayViewDate"
                    @event-click="handleEventClickFromGantt"
                    @event-hover="handleEventHoverFromGantt"
                    :time-scope="activeScope"
                    @select-scope="handleSelectScope"
                    :class="{'is-current-scope': activeScope === 'current'}"
                />
                <WeekSummaryView
                    v-else-if="viewMode === 'timeGridWeek'"
                    :events="currentEvents"
                    :week-start="currentWeekStart"
                    @event-click="handleEventClickFromGantt"
                    @date-click="handleDateClickFromWeek"
                    @event-hover="handleEventHoverFromGantt"
                />
                <FullCalendar
                    v-else
                    ref="fullCalendar"
                    :options="calendarOptions"
                />
            </div>
            </ScrollArea>
        </CardContent>

        <div class="px-4 pb-4">
            <div class="flex items-start justify-between gap-3 min-w-0">
                <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs mt-1 flex-1 min-w-0">
                    <div v-for="item in CATEGORY_ITEMS" :key="item.label" class="flex items-center gap-1.5 transition-all duration-200"
                        :class="{
                            'ring-2 ring-blue-500 ring-offset-1 rounded px-1 py-0.5': 
                                (genreFilter as string) === GENRE_FILTERS.BLUE && item.label === '会議' ||
                                (genreFilter as string) === GENRE_FILTERS.GREEN && item.label === '業務' ||
                                (genreFilter as string) === GENRE_FILTERS.YELLOW && item.label === '来客' ||
                                (genreFilter as string) === GENRE_FILTERS.PURPLE && item.label === '出張' ||
                                (genreFilter as string) === GENRE_FILTERS.PINK && item.label === '休暇' ||
                                (genreFilter as string) === GENRE_FILTERS.OTHER && item.label === 'その他'
                        }"
                    >
                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color }"></div>
                        <span class="whitespace-nowrap">{{ item.label }}</span>
                    </div>
                </div>
                <!-- 右：日表示専用タブ -->
                <div 
                    v-if="viewMode === 'timeGridDay'"
                    class="flex gap-1 text-xs flex-shrink-0 overflow-hidden"
                >
                    <Button v-for="s in [
                        ['all','全体'],
                        ['current','現在'],
                        ['before','前'],
                        ['middle','中'],
                        ['after','後']
                        ] as const"
                        :key="s[0]"
                        @click="handleScopeButtonClick(s[0])"
                        class="px-2 py-1 rounded transition whitespace-nowrap"
                        :class="activeScope === s[0]
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        {{ s[1] }}
                    </Button>
                </div>
            </div>
        </div>

        <EventDetailDialog
            :event="selectedEvent"
            :open="selectedEvent !== null"
            @update:open="(isOpen) => !isOpen && (selectedEvent = null)"
            @edit="() => selectedEvent && openEditDialog(selectedEvent.event_id)"
        />

        <CreateEventDialog
            :key="editingEvent ? editingEvent.event_id : 'create'"
            :open="isEventFormOpen"
            @update:open="handleDialogClose"
            :event="editingEvent"
            :readonly="editingEvent ? !canEditEvent(editingEvent) : false"
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
                v-if="hoveredEvent || hoveredEvents.length > 0"
                class="fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg p-3 max-w-xs pointer-events-none"
                :style="{ left: hoverPosition.x + 'px', top: hoverPosition.y + 'px', transform: 'translateX(-50%) translateY(-100%)' }"
            >
                <!-- 単一イベントのホバー -->
                <div v-if="hoveredEvent" class="space-y-2">
                    <div class="font-semibold text-sm">{{ hoveredEvent.title }}</div>
                    <div class="text-xs text-gray-600">
                        <span class="text-gray-500">重要度:</span> 
                        <span :class="{
                            'text-red-600 font-semibold': hoveredEvent.importance === '重要',
                            'text-yellow-600': hoveredEvent.importance === '中',
                            'text-gray-600': hoveredEvent.importance === '低'
                        }">{{ hoveredEvent.importance }}</span>
                    </div>
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
                
                <!-- +moreホバー：複数イベント -->
                <div v-else-if="hoveredEvents.length > 0" class="space-y-3">
                    <div v-for="(event, idx) in hoveredEvents" :key="idx" class="space-y-1" :class="{ 'border-t border-gray-200 pt-2': idx > 0 }">
                        <div class="font-semibold text-sm">{{ event.title }}</div>
                        <div class="text-xs text-gray-600">
                            <span class="text-gray-500">重要度:</span> 
                            <span :class="{
                                'text-red-600 font-semibold': event.importance === '重要',
                                'text-yellow-600': event.importance === '中',
                                'text-gray-600': event.importance === '低'
                            }">{{ event.importance }}</span>
                        </div>
                        <div class="text-xs text-gray-600">
                            <span class="text-gray-500">締切:</span> 
                            {{ new Date(event.end_date).toLocaleDateString('ja-JP') }}
                            {{ event.end_time && !event.is_all_day ? ' ' + event.end_time.slice(0, 5) : '' }}
                        </div>
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
.fc-event.important-event {
    border: 2px solid #dc2626 !important;
    border-left-width: 4px !important;
    font-weight: 600;
}
.fc-daygrid-event-harness .fc-event.important-event {
    border: 2px solid #dc2626 !important;
    border-top-width: 3px !important;
    font-weight: 600;
}
/* 年表示での日をまたぐ重要イベント */
.fc-multiMonthYear-view .fc-daygrid-event.important-event {
    border: 2px solid #dc2626 !important;
    font-weight: 600;
}
.fc-multiMonthYear-view .fc-daygrid-event-harness .important-event {
    border: 2px solid #dc2626 !important;
    font-weight: 600;
}
.fc-daygrid-more-link.has-important-event {
    border: 2px solid #dc2626 !important;
    border-radius: 4px !important;
    padding: 1px 4px !important;
    font-weight: 600 !important;
    background-color: #fee2e2 !important;
}
/* ドリルダウン用ホバー効果 */
.fc-multimonth-month:hover {
    background-color: rgba(59, 130, 246, 0.05) !important;
    transition: background-color 0.2s;
}
.fc-dayGridMonth-view .fc-daygrid-day:hover {
    background-color: rgba(59, 130, 246, 0.05) !important;
    transition: background-color 0.2s;
}
/* 週表示の横ストライプ（行ハイライト） */
.fc-timegrid-body tr:hover {
    background-color: rgba(59, 130, 246, 0.05) !important;
}
.fc-timegrid-slot:hover {
    background-color: rgba(59, 130, 246, 0.08) !important;
}
/* 月表示の行ハイライト */
.fc-daygrid-body tr:hover {
    background-color: rgba(59, 130, 246, 0.05) !important;
}
/* スクロールバーのスタイル改善 */
.fc-scroller::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.fc-scroller::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 5px;
}
.fc-scroller::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}
.fc-scroller::-webkit-scrollbar-thumb:hover {
    background: #555;
}
@media (max-width: 480px) {
  .time-scope-tabs button:not(.active) {
    display: none;
  }
}

</style>