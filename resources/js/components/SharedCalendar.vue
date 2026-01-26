<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, ArrowLeft, Search, ChevronUp, ChevronDown, Filter } from 'lucide-vue-next'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import EventDetailDialog from '@/components/EventDetailDialog.vue'
import CreateEventDialog from '@/components/CreateEventDialog.vue'
import ScrollArea from './ui/scroll-area/ScrollArea.vue'
import { GENRE_FILTERS, GENRE_ITEMS, getEventColor } from '@/constants/calendar'

const DayViewGantt = defineAsyncComponent(() => import('@/components/DayViewGantt.vue'))
const WeekSummaryView = defineAsyncComponent(() => import('@/components/WeekSummaryView.vue'))

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

// 検索アイコン展開用
const isSearchOpen = ref(false)
const headerRef = ref<HTMLElement | null>(null)
const layoutMode = ref<'default'|'filter-small'|'search-icon'|'title-hide'|'compact'|'minimal'|'ultra-minimal'>('default')

const compactCalendarTitle = computed(() => {
    if (layoutMode.value === 'default' || layoutMode.value === 'filter-small') {
        return calendarTitle.value
    }
    
    if (viewMode.value === 'timeGridDay') {
        return currentDayViewDate.value.toLocaleDateString('ja-JP', {
            month: 'long',
            day: 'numeric'
        })
    } else if (viewMode.value === 'timeGridWeek') {
        const start = new Date(currentWeekStart.value)
        const end = new Date(start)
        end.setDate(end.getDate() + 6)
        if (start.getMonth() === end.getMonth()) {
            return `${start.getMonth() + 1}月${start.getDate()}日〜${end.getDate()}日`
        } else {
            return `${start.getMonth() + 1}月${start.getDate()}日〜${end.getMonth() + 1}月${end.getDate()}日`
        }
    } else {
        return calendarTitle.value
    }
})

let resizeHandler: (() => void) | null = null
let resizeObserver: ResizeObserver | null = null
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
    
    // ResizeObserver for header
    if (headerRef.value) {
        resizeObserver = new ResizeObserver(entries => {
            const width = entries[0].contentRect.width
            layoutMode.value = 
                width < 480 ? 'ultra-minimal'
                : width < 540 ? 'minimal'
                : width < 600 ? 'compact'
                : width < 650 ? 'title-hide'
                : width < 700 ? 'search-icon'
                : width < 750 ? 'filter-small'
                : 'default'
        })
        resizeObserver.observe(headerRef.value)
    }
})

onUnmounted(() => {
    if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler)
    }
    if (removeInertiaListener) {
        removeInertiaListener()
    }
    if (resizeObserver) {
        resizeObserver.disconnect()
    }
})

// 検索バー閉じる処理
const searchInput = ref<HTMLInputElement | null>(null)
const toggleSearch = () => {
    isSearchOpen.value = !isSearchOpen.value
    if (!isSearchOpen.value) {
        searchQuery.value = ''
    }
}

// 検索バー展開時にフォーカス
// watch(isSearchOpen, (isOpen) => {
//     if (isOpen) {
//         nextTick(() => {
//             setTimeout(() => {
//                 searchInput.value?.focus()
//             }, 50)
//         })
//     }
// })

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
        <div ref="headerRef" class="p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 min-w-0 flex-shrink-0" 
                    :class="!showBackButton ? 'cursor-pointer hover:opacity-70 transition-opacity' : ''" 
                    @click="!showBackButton && router.visit('/calendar')">
                    <Button v-if="showBackButton" variant="ghost" size="icon" @click="router.get('/')" class="mr-1">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>

                    <CalendarIcon class="h-6 w-6 text-blue-700 flex-shrink-0" />

                    <Transition
                        enter-active-class="transition-all duration-300 ease-in-out"
                        leave-active-class="transition-all duration-300 ease-in-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <CardTitle 
                            v-if="layoutMode === 'default' || layoutMode === 'filter-small' || layoutMode === 'search-icon'"
                            class="transition-all duration-300 ease-in-out whitespace-nowrap"
                        >
                            共有カレンダー
                        </CardTitle>
                    </Transition>
                </div>
                <!-- 右上操作エリア -->
                <div class="flex items-center gap-2 min-w-0 flex-shrink">
                    <!-- ジャンル Select -->
                    <div class="transition-all duration-300 ease-in-out flex-shrink">
                        <Select v-model="genreFilter" :key="`genre-${layoutMode}`">
                            <SelectTrigger 
                                class="transition-all duration-300 ease-in-out w-10 justify-center px-0 [&>svg:last-child]:hidden"
                            >
                                <Filter class="h-4 w-4" />
                            </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="GENRE_FILTERS.ALL">すべて</SelectItem>
                            <SelectItem v-for="item in GENRE_ITEMS" :key="item.id" :value="item.id">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.hex }"></div>
                                    {{ item.label }}
                                </div>
                            </SelectItem>
                        </SelectContent>
                        </Select>
                    </div>

                    <!-- 検索エリア -->
                    <div class="relative transition-all duration-300 ease-in-out flex-shrink">
                        <div v-if="(layoutMode === 'default' || layoutMode === 'filter-small')" class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="タイトルなどで検索"
                                class="pl-10 min-w-0 transition-all duration-300 ease-in-out"
                                :class="layoutMode === 'filter-small' ? 'max-w-[200px]' : 'max-w-[280px]'"
                            />
                        </div>
                        <div v-else-if="layoutMode === 'search-icon' || layoutMode === 'title-hide' || layoutMode === 'compact' || layoutMode === 'minimal' || layoutMode === 'ultra-minimal'" class="flex items-center transition-all duration-300 ease-in-out">
                            <div class="relative flex items-center">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    @click="toggleSearch"
                                    class="transition-all duration-300 ease-in-out border-gray-300 dark:border-input"
                                    :class="isSearchOpen ? 'rounded-r-none border-r-0' : ''"
                                    tabindex="-1"
                                >
                                    <Search class="h-4 w-4"/>
                                </Button>
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-in-out"
                                    leave-active-class="transition-all duration-300 ease-in-out"
                                    enter-from-class="w-0 opacity-0"
                                    enter-to-class="w-[140px] opacity-100"
                                    leave-from-class="w-[140px] opacity-100"
                                    leave-to-class="w-0 opacity-0"
                                >
                                    <Input
                                        v-if="isSearchOpen"
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="タイトルなどで検索"
                                        class="rounded-l-none border-l-0 transition-all duration-300 ease-in-out"
                                        @blur="!searchQuery && toggleSearch()"
                                        @keydown.escape="toggleSearch()"
                                        ref="searchInput"
                                    />
                                </Transition>
                            </div>
                        </div>
                    </div>

                    <!-- 新規作成 -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-in-out"
                        leave-active-class="transition-all duration-300 ease-in-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div class="transition-all duration-300 ease-in-out">
                            <Button
                                :key="`create-${layoutMode}`"
                                variant="outline"
                                class="transition-all duration-300 ease-in-out flex-shrink-0 border-gray-300 dark:border-input"
                                :class="layoutMode === 'default' || layoutMode === 'filter-small' ? 'gap-2' : ''"
                                @click="openCreateDialog"
                                :title="layoutMode === 'search-icon' || layoutMode === 'title-hide' || layoutMode === 'compact' || layoutMode === 'minimal' || layoutMode === 'ultra-minimal' ? '新規作成' : undefined"
                            >
                                <Plus class="h-4 w-4" />
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-in-out"
                                    leave-active-class="transition-all duration-300 ease-in-out"
                                    enter-from-class="w-0 opacity-0"
                                    enter-to-class="w-auto opacity-100"
                                    leave-from-class="w-auto opacity-100"
                                    leave-to-class="w-0 opacity-0"
                                >
                                    <span v-if="layoutMode === 'default' || layoutMode === 'filter-small'" class="whitespace-nowrap">
                                        新規作成
                                    </span>
                                </Transition>
                            </Button>
                        </div>
                    </Transition>
                </div>
            </div>

            <div class="flex items-center gap-4 transition-all duration-300 ease-in-out" :class="layoutMode === 'default' ? 'justify-between' : 'justify-end'">
                <Transition
                    enter-active-class="transition-all duration-300 ease-in-out"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="layoutMode === 'default'" class="flex-1">
                        <div class="flex-1">
                        <div class="grid w-full max-w-[400px] grid-cols-4 gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                            <button
                                v-for="view in [
                                    { value: 'multiMonthYear', label: '年' },
                                    { value: 'dayGridMonth', label: '月' },
                                    { value: 'timeGridWeek', label: '週' },
                                    { value: 'timeGridDay', label: '日' },
                                ]"
                                :key="view.value"
                                @click="changeView(view.value)"
                                :class="[
                                    'flex items-center justify-center py-1 text-sm font-medium rounded-md transition-all duration-200',
                                    viewMode === view.value
                                        ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-gray-100'
                                        : 'text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                                ]"
                            >
                                {{ view.label }}
                            </button>
                        </div>
                    </div>
                    </div>
                </Transition>

                <div class="flex items-center gap-3 transition-all duration-300 ease-in-out">
                    <Button 
                        v-if="canGoBack" 
                        variant="outline" 
                        size="sm" 
                        @click="goBackOneLevel"
                        class="gap-1 transition-all duration-300 ease-in-out flex-shrink-0 border-gray-300 dark:border-input"
                    >
                        <ChevronUp class="h-4 w-4" />
                        <Transition
                            enter-active-class="transition-all duration-300 ease-in-out"
                            leave-active-class="transition-all duration-300 ease-in-out"
                            enter-from-class="w-0 opacity-0"
                            enter-to-class="w-auto opacity-100"
                            leave-from-class="w-auto opacity-100"
                            leave-to-class="w-0 opacity-0"
                        >
                            <span v-if="layoutMode === 'default' || layoutMode === 'filter-small'" class="whitespace-nowrap">戻る</span>
                        </Transition>
                    </Button>
                    <Button variant="outline" size="sm" @click="previousPeriod" class="flex-shrink-0 border-gray-300 dark:border-input">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <div 
                        class="text-center font-semibold truncate transition-all duration-300 ease-in-out flex-shrink-0"
                    >
                        {{ compactCalendarTitle }}
                    </div>
                    <Button variant="outline" size="sm" @click="nextPeriod" class="flex-shrink-0 border-gray-300 dark:border-input">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="handleTodayClick" class="flex-shrink-0 border-gray-300 dark:border-input">{{ todayButtonText }}</Button>
                </div>
            </div>
        </div>

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
                    <div v-for="item in GENRE_ITEMS" :key="item.id" class="flex items-center gap-1.5 transition-all duration-200"
                        :class="{
                            'ring-2 ring-blue-500 ring-offset-1 rounded px-1 py-0.5': 
                                (genreFilter as string) === item.id
                        }"
                    >
                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.hex }"></div>
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
                        ]"
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
            @update:open="isEventFormOpen = $event"
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


/* Dark Mode Overrides for FullCalendar */
.dark .fc {
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: theme('colors.gray.800');
    --fc-list-event-hover-bg-color: theme('colors.gray.700');
    --fc-today-bg-color: rgba(59, 130, 246, 0.15);
    --fc-border-color: theme('colors.gray.700');
    --fc-neutral-text-color: theme('colors.white');
    --fc-now-indicator-color: theme('colors.red.500');
}

.dark .fc-theme-standard td, 
.dark .fc-theme-standard th {
    border-color: theme('colors.gray.700');
}

/* Specific overrides for Week/Day view in Dark Mode */
.dark .fc-timegrid-slot {
    border-bottom-color: theme('colors.gray.800');
}

.dark .fc-timegrid-axis-cushion,
.dark .fc-col-header-cell-cushion,
.dark .fc-timegrid-slot-label-cushion {
    color: theme('colors.gray.100');
}

.dark .fc-timegrid-body tr:hover {
    background-color: rgba(59, 130, 246, 0.1) !important;
}

.dark .fc-timegrid-slot:hover {
    background-color: rgba(59, 130, 246, 0.12) !important;
}

.dark .fc-col-header-cell {
    background-color: theme('colors.background');
}

.dark .fc-scrollgrid-section-header > * {
    background-color: theme('colors.background');
}

.dark .fc-daygrid-day-number {
    color: theme('colors.gray.100');
}

/* Hover popup dark mode */
.dark .bg-white.border-gray-200 {
    @apply bg-card border-border text-card-foreground;
}

.dark .text-gray-600 {
    @apply text-muted-foreground;
}

.dark .text-gray-500 {
    @apply text-muted-foreground opacity-80;
}


.dark .fc-col-header-cell-cushion,
.dark .fc-daygrid-day-number {
    color: theme('colors.gray.300');
}

.dark .fc-toolbar-title {
    color: theme('colors.gray.100');
}

.dark .fc-daygrid-day:hover,
.dark .fc-timegrid-slot:hover {
    background-color: rgba(59, 130, 246, 0.1) !important;
}

.dark .fc-scroller::-webkit-scrollbar-track {
    background: theme('colors.gray.800');
}
.dark .fc-scroller::-webkit-scrollbar-thumb {
    background: theme('colors.gray.600');
}
.dark .fc-scroller::-webkit-scrollbar-thumb:hover {
    background: theme('colors.gray.500');
}

/* Week/Day View Specifics */
.dark .fc-theme-standard .fc-scrollgrid {
    border-color: theme('colors.gray.700');
}

.dark .fc-timegrid-slot {
    background-color: transparent;
    border-color: theme('colors.gray.800');
}

.dark .fc-timegrid-axis {
    background-color: transparent;
    color: theme('colors.gray.300');
}

.dark .fc-col-header {
    background-color: transparent;
}

.dark .fc-timegrid-now-indicator-line {
    border-color: theme('colors.blue.400');
}
.dark .fc-timegrid-now-indicator-arrow {
    border-color: theme('colors.blue.400');
    border-bottom-color: theme('colors.blue.400');
}
</style>