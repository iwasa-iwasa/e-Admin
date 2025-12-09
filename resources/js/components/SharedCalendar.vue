<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useHighlight } from '@/composables/useHighlight'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'
import { CalendarOptions } from '@fullcalendar/core'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus, Users, ArrowLeft, Search } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
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
const searchQuery = ref('')

// 今日がビュー内にあるかどうかを保持
const isTodayInCurrentView = ref(false)

const filteredEvents = computed(() => {
    if (!searchQuery.value.trim()) return props.events
    
    const query = searchQuery.value.toLowerCase()
    return props.events.filter(event => {
        const title = event.title?.toLowerCase() || ''
        const description = event.description?.toLowerCase() || ''
        const creatorName = event.creator?.name?.toLowerCase() || ''
        const participantNames = event.participants?.map((p: any) => p.name?.toLowerCase()).join(' ') || ''
        
        return title.includes(query) || 
               description.includes(query) || 
               creatorName.includes(query) || 
               participantNames.includes(query)
    })
})

const openCreateDialog = () => {
    editingEvent.value = null
    isEventFormOpen.value = true
}

const canEditEvent = (event: App.Models.Event) => {
    const currentUserId = (usePage().props as any).auth?.user?.id ?? null
    const teamMembers = (usePage().props as any).teamMembers || []
    
    const isCreator = event.created_by === currentUserId
    if (isCreator) return true
    
    if (Array.isArray(teamMembers) && teamMembers.length > 0 && event.participants && event.participants.length === teamMembers.length) {
        return true
    }
    
    const isParticipant = event.participants?.some(p => p.id === currentUserId)
    return isParticipant || false
}

const openEditDialog = (eventId: number) => {
    const event = props.events.find(e => e.event_id === eventId)
    if (event) {
        selectedEvent.value = null
        editingEvent.value = event
        isEventFormOpen.value = true
    }
}

const getEventColor = (category: string) => {
    const categoryColorMap: { [key: string]: string } = {
        '会議': '#42A5F5', // blue
        '業務': '#66BB6A', // green
        '来客': '#FFA726', // orange
        '出張': '#9575CD', // purple
        '休暇': '#F06292', // pink
    };
    return categoryColorMap[category] || '#6b7280';
}

const legendItems = [
    { label: '会議', color: '#42A5F5' },
    { label: '業務', color: '#66BB6A' },
    { label: '来客', color: '#FFA726' },
    { label: '出張', color: '#9575CD' },
    { label: '休暇', color: '#F06292' },
];

const calendarOptions = computed((): CalendarOptions => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin, rrulePlugin],
    initialView: viewMode.value,
    headerToolbar: false,
    contentHeight: 'auto',
    eventOrder: (a: any, b: any) => {
        const aImportance = a.extendedProps?.importance || '低'
        const bImportance = b.extendedProps?.importance || '低'
        if (aImportance === '重要' && bImportance !== '重要') return -1
        if (aImportance !== '重要' && bImportance === '重要') return 1
        return 0
    },
    events: filteredEvents.value.map(event => {
        const commonProps = {
            id: String(event.event_id),
            title: event.title,
            backgroundColor: getEventColor(event.category),
            borderColor: event.importance === '重要' ? '#dc2626' : getEventColor(event.category),
            extendedProps: event,
            allDay: event.is_all_day,
            classNames: event.importance === '重要' ? ['important-event'] : [],
        };

        if (event.rrule) {
            return {
                ...commonProps,
                rrule: event.rrule,
                duration: event.duration,
            };
        }

        if (event.is_all_day) {
            // event.end_dateの翌日を計算
            const [year, month, day] = event.end_date.split('T')[0].split('-').map(Number);
            const endDate = new Date(Date.UTC(year, month - 1, day));
            endDate.setUTCDate(endDate.getUTCDate() + 1);
            const endStr = endDate.toISOString().split('T')[0];

            return {
                ...commonProps,
                start: event.start_date.split('T')[0],
                end: endStr,
            };
        }

        const startDateStr = event.start_date.split('T')[0];
        const endDateStr = event.end_date.split('T')[0];
        const startTime = event.start_time || '00:00:00';
        const endTime = event.end_time || '00:00:00';
        
        const formatTime = (time: string) => {
            const parts = time.split(':');
            if (parts.length === 2) return time + ':00';
            return time.substring(0, 8);
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
        
        // 今日がビュー内にあるか判定
        const todayMidnight = new Date()
        todayMidnight.setHours(0, 0, 0, 0)
        isTodayInCurrentView.value = info.view.currentStart <= todayMidnight && todayMidnight < info.view.currentEnd
        
        // +moreリンクに重要イベントのスタイルを適用
        setTimeout(() => {
            const api = fullCalendar.value?.getApi()
            if (!api) return
            
            document.querySelectorAll('.fc-daygrid-day').forEach(dayEl => {
                const dateStr = dayEl.getAttribute('data-date')
                if (!dateStr) return
                
                // その日の全イベントを取得（表示されていないものも含む）
                const dayStart = new Date(dateStr + 'T00:00:00')
                const dayEnd = new Date(dateStr + 'T23:59:59')
                const eventsOnDay = api.getEvents().filter((event: any) => {
                    const eventStart = event.start
                    const eventEnd = event.end || event.start
                    return eventStart <= dayEnd && eventEnd >= dayStart
                })
                
                const hasImportant = eventsOnDay.some((event: any) => 
                    event.extendedProps?.importance === '重要'
                )
                
                const moreLink = dayEl.querySelector('.fc-daygrid-more-link')
                if (hasImportant && moreLink) {
                    moreLink.classList.add('has-important-event')
                } else if (moreLink) {
                    moreLink.classList.remove('has-important-event')
                }
            })
        }, 0)
    },
    eventDidMount: () => {
        // イベント表示後にも+moreリンクをチェック
        setTimeout(() => {
            const api = fullCalendar.value?.getApi()
            if (!api) return
            
            document.querySelectorAll('.fc-daygrid-day').forEach(dayEl => {
                const dateStr = dayEl.getAttribute('data-date')
                if (!dateStr) return
                
                const dayStart = new Date(dateStr + 'T00:00:00')
                const dayEnd = new Date(dateStr + 'T23:59:59')
                const eventsOnDay = api.getEvents().filter((event: any) => {
                    const eventStart = event.start
                    const eventEnd = event.end || event.start
                    return eventStart <= dayEnd && eventEnd >= dayStart
                })
                
                const hasImportant = eventsOnDay.some((event: any) => 
                    event.extendedProps?.importance === '重要'
                )
                
                const moreLink = dayEl.querySelector('.fc-daygrid-more-link')
                if (hasImportant && moreLink) {
                    moreLink.classList.add('has-important-event')
                } else if (moreLink) {
                    moreLink.classList.remove('has-important-event')
                }
            })
        }, 0)
    },
}))

const previousPeriod = () => {
    const api = fullCalendar.value?.getApi()
    if (api) {
        api.prev()
    }
}

const nextPeriod = () => {
    const api = fullCalendar.value?.getApi()
    if (api) {
        api.next()
    }
}

/**
 * 今日ボタンのラベルを動的に変更
 */
const todayButtonText = computed(() => {
    const currentView = viewMode.value
    const isTodayInView = isTodayInCurrentView.value
    
    // 年表示モード
    if (currentView === 'multiMonthYear') {
        return isTodayInView ? '今月' : '今年'
    }
    
    // 月表示モード
    if (currentView === 'dayGridMonth') {
        return isTodayInView ? '今週' : '今月'
    }
    
    // 週表示モード
    if (currentView === 'timeGridWeek') {
        return isTodayInView ? '今日' : '今週'
    }
    
    // 日表示モード
    if (currentView === 'timeGridDay') {
        return '今日'
    }
    
    return '今日'
})

/**
 * 今日ボタンのクリック動作
 */
const handleTodayClick = () => {
    const api = fullCalendar.value?.getApi()
    if (!api) return
    
    const currentView = viewMode.value
    const today = new Date()
    const isTodayInView = isTodayInCurrentView.value
    
    // 年表示モード
    if (currentView === 'multiMonthYear') {
        if (isTodayInView) {
            // A: 今年表示中 → 今月へ
            changeView('dayGridMonth')
            api.gotoDate(today)
        } else {
            // B: 他の年表示中 → 今年へ
            api.gotoDate(today)
        }
        return
    }
    
    // 月表示モード
    if (currentView === 'dayGridMonth') {
        if (isTodayInView) {
            // C: 今月表示中 → 今週へ
            changeView('timeGridWeek')
            api.gotoDate(today)
        } else {
            // D: 他の月表示中 → 今月へ
            api.gotoDate(today)
        }
        return
    }
    
    // 週表示モード
    if (currentView === 'timeGridWeek') {
        if (isTodayInView) {
            // E: 今週表示中 → 今日へ
            changeView('timeGridDay')
            api.gotoDate(today)
        } else {
            // F: 他の週表示中 → 今週へ
            api.gotoDate(today)
        }
        return
    }
    
    // 日表示モード
    if (currentView === 'timeGridDay') {
        // G or H: 常に今日へ
        api.gotoDate(today)
        return
    }
}

const changeView = (view: any) => {
    viewMode.value = view
    const api = fullCalendar.value?.getApi()
    if (api) {
        api.changeView(view)
    }
}

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

// ハイライト機能
const page = usePage()
const highlightId = computed(() => (page.props as any).highlight)

watch(highlightId, (id) => {
    if (id) {
        nextTick(() => {
            const event = props.events.find(e => e.event_id === id)
            if (event) {
                selectedEvent.value = event
            }
        })
    }
}, { immediate: true })
</script>

<template>
    <Card class="flex flex-col h-full overflow-hidden">
        <div class="p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <Button v-if="showBackButton" variant="ghost" size="icon" @click="router.get('/')" class="mr-1">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div class="flex items-center gap-2" :class="!showBackButton ? 'cursor-pointer hover:opacity-70 transition-opacity' : ''" @click="!showBackButton && router.visit('/calendar')">
                        <CalendarIcon class="h-6 w-6 text-blue-700" />
                        <CardTitle class="whitespace-nowrap">部署内共有カレンダー</CardTitle>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="タイトル、詳細、名前で検索..."
                            class="pl-9 pr-4 w-[280px]"
                        />
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
                <Button variant="outline" size="sm" @click="handleTodayClick">{{ todayButtonText }}</Button>
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
                v-if="hoveredEvent"
                class="fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg p-3 max-w-xs pointer-events-none"
                :style="{ left: hoverPosition.x + 'px', top: hoverPosition.y + 'px', transform: 'translateX(-50%) translateY(-100%)' }"
            >
                <div class="space-y-2">
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