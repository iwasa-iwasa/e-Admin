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
import { CATEGORY_COLORS, CATEGORY_LABELS, GENRE_FILTERS, getEventColor, CATEGORY_ITEMS } from '@/constants/calendar'

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

// Resize Handler
let resizeHandler: (() => void) | null = null

onMounted(() => {
    resizeHandler = () => {
        const api = fullCalendar.value?.getApi()
        if (api) {
            api.updateSize()
        }
    }
    window.addEventListener('resize', resizeHandler)
})

onUnmounted(() => {
    if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler)
    }
})

// Highlight Logic
const page = usePage()
const highlightId = computed(() => (page.props as any).highlight)

watch(highlightId, (id) => {
    if (id) {
        nextTick(() => {
            // Logic to handle highlight: might need to fetch event if not loaded
            const event = currentEvents.value.find(e => e.event_id === id)
            if (event) {
                selectedEvent.value = event
            }
        })
    }
}, { immediate: true })

// 🔽 検索アイコン展開用（minimal時）
const isSearchOpen = ref(false)
const headerRef = ref<HTMLElement | null>(null)
const layoutMode = ref<'default'|'filter-small'|'search-icon'|'title-hide'|'compact'|'minimal'|'ultra-minimal'>('default')

// 縮小時の日付表示用
const compactCalendarTitle = computed(() => {
    if (layoutMode.value === 'default' || layoutMode.value === 'filter-small') {
        return calendarTitle.value
    }
    
    if (viewMode.value === 'timeGridDay') {
        // 日表示: 「3月16日」のみ
        return currentDayViewDate.value.toLocaleDateString('ja-JP', {
            month: 'long',
            day: 'numeric'
        })
    } else if (viewMode.value === 'timeGridWeek') {
        // 週表示: 「3月16日〜22日」
        const start = new Date(currentWeekStart.value)
        const end = new Date(start)
        end.setDate(end.getDate() + 6)
        if (start.getMonth() === end.getMonth()) {
            return `${start.getMonth() + 1}月${start.getDate()}日〜${end.getDate()}日`
        } else {
            return `${start.getMonth() + 1}月${start.getDate()}日〜${end.getMonth() + 1}月${end.getDate()}日`
        }
    } else {
        // 月表示や年表示は既存のタイトルを使用
        return calendarTitle.value
    }
})

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
    if(!headerRef.value) return

    resizeObserver = new ResizeObserver(entries => {
        const width = entries[0].contentRect.width

        layoutMode.value = 
            width < 480 ? 'ultra-minimal' // 新規作成アイコン化
            : width < 540 ? 'minimal'     // 新規作成アイコン化
            : width < 600 ? 'compact'     // フィルターアイコン化
            : width < 650 ? 'title-hide'  // タイトル消える
            : width < 700 ? 'search-icon' // 検索バー縮小
            : width < 750 ? 'filter-small'// フィルター縮小
            : 'default'                   // 通常表示
    })

    resizeObserver.observe(headerRef.value)
})

onUnmounted(() => {
    resizeObserver?.disconnect()
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
</script>

<template>
    <Card class="flex flex-col h-full overflow-hidden">
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
                            <SelectItem :value="GENRE_FILTERS.BLUE">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['会議'] }"></div>
                                    {{ CATEGORY_LABELS['会議'] }}
                                </div>
                            </SelectItem>
                            <SelectItem :value="GENRE_FILTERS.GREEN">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['業務'] }"></div>
                                    {{ CATEGORY_LABELS['業務'] }}
                                </div>
                            </SelectItem>
                            <SelectItem :value="GENRE_FILTERS.YELLOW">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['来客'] }"></div>
                                    {{ CATEGORY_LABELS['来客'] }}
                                </div>
                            </SelectItem>
                            <SelectItem :value="GENRE_FILTERS.PURPLE">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['出張'] }"></div>
                                    {{ CATEGORY_LABELS['出張'] }}
                                </div>
                            </SelectItem>
                            <SelectItem :value="GENRE_FILTERS.PINK">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['休暇'] }"></div>
                                    {{ CATEGORY_LABELS['休暇'] }}
                                </div>
                            </SelectItem>
                            <SelectItem :value="GENRE_FILTERS.OTHER">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['その他'] }"></div>
                                    {{ CATEGORY_LABELS['その他'] }}
                                </div>
                            </SelectItem>
                        </SelectContent>
                        </Select>
                    </div>

                    <!-- 検索エリア -->
                    <div class="relative transition-all duration-300 ease-in-out flex-shrink">
                        <div v-if="!showBackButton && (layoutMode === 'default' || layoutMode === 'filter-small')" class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="タイトルなどで検索"
                                class="pl-10 min-w-0 transition-all duration-300 ease-in-out"
                                :class="layoutMode === 'filter-small' ? 'max-w-[200px]' : 'max-w-[280px]'"
                            />
                        </div>
                        <div v-else-if="!showBackButton || layoutMode === 'search-icon' || layoutMode === 'title-hide' || layoutMode === 'compact' || layoutMode === 'minimal' || layoutMode === 'ultra-minimal'" class="flex items-center transition-all duration-300 ease-in-out">
                            <div class="relative flex items-center">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    @click="toggleSearch"
                                    class="transition-all duration-300 ease-in-out"
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
                                class="transition-all duration-300 ease-in-out flex-shrink-0"
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
                        <Tabs :model-value="viewMode" @update:model-value="changeView" class="flex-1">
                            <TabsList class="grid w-full max-w-[400px] grid-cols-4 bg-gray-100">
                                <TabsTrigger value="multiMonthYear">年</TabsTrigger>
                                <TabsTrigger value="dayGridMonth">月</TabsTrigger>
                                <TabsTrigger value="timeGridWeek">週</TabsTrigger>
                                <TabsTrigger value="timeGridDay">日</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </Transition>

                <div class="flex items-center gap-3 transition-all duration-300 ease-in-out">
                    <Button 
                        v-if="canGoBack" 
                        variant="outline" 
                        size="sm" 
                        @click="goBackOneLevel"
                        class="gap-1 transition-all duration-300 ease-in-out flex-shrink-0"
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
                    <Button variant="outline" size="sm" @click="previousPeriod" class="flex-shrink-0">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <div 
                        class="text-center font-semibold truncate transition-all duration-300 ease-in-out flex-shrink-0"
                    >
                        {{ compactCalendarTitle }}
                    </div>
                    <Button variant="outline" size="sm" @click="nextPeriod" class="flex-shrink-0">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="handleTodayClick" class="flex-shrink-0">{{ todayButtonText }}</Button>
                </div>
            </div>
        </div>

        <CardContent class="flex flex-1 overflow-y-auto">
            <ScrollArea class="h-full w-full">
            <div class="w-full h-full flex-1">
                <DayViewGantt
                    v-if="viewMode === 'timeGridDay'"
                    :events="currentEvents"
                    :current-date="currentDayViewDate"
                    @event-click="handleEventClickFromGantt"
                    @event-hover="handleEventHoverFromGantt"
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

        <CardContent>
        <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs mt-1">
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
                <span>{{ item.label }}</span>
            </div>
        </div>
        </CardContent>

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
</style>