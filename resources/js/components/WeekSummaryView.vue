<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
    events: App.Models.Event[]
    weekStart: Date
}>()

const emit = defineEmits<{
    eventClick: [event: App.Models.Event]
    dateClick: [date: Date]
    eventHover: [event: App.Models.Event | null, position: { x: number, y: number }]
}>()

// リサイズ関連
const summaryHeight = ref(150)
const isResizing = ref(false)
const MIN_HEIGHT = 80
const MAX_HEIGHT = 300

// 時間範囲
const START_HOUR = 7
const END_HOUR = 20
const WORK_START_HOUR = 8
const WORK_END_HOUR = 17

// イベントバー型（分割しない）
interface EventBar {
    event: App.Models.Event
    startDayIndex: number
    endDayIndex: number
    startHour: number
    endHour: number
    stackIndex: number
}

const toLocalDateString = (date: Date) => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

// 👇 ここに追加（UTC地雷回避用）
const toComparableDate = (dateStr: string) => {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d)
}


// 週の日付配列を生成（日曜日から開始）
const weekDays = computed(() => {
    const days: Date[] = []
    const startDate = new Date(props.weekStart)

    // ローカル時間で曜日取得（日曜=0）
    const dayOfWeek = startDate.getDay()

    // 日曜開始に補正
    const sundayStart = new Date(startDate)
    sundayStart.setDate(startDate.getDate() - dayOfWeek)

    for (let i = 0; i < 7; i++) {
        const date = new Date(sundayStart)
        date.setDate(sundayStart.getDate() + i)
        days.push(date)
    }

    return days
})



// 週の開始日と終了日（日曜日ベース）
const weekStartDate = computed(() => {
    const start = new Date(weekDays.value[0])
    start.setHours(0, 0, 0, 0)
    return start
})

const weekEndDate = computed(() => {
    const end = new Date(weekDays.value[6])
    end.setHours(23, 59, 59, 999)
    return end
})

// 複数日またぎ・終日予定をタイムライン表示
const multiDayBars = computed(() => {
    const bars: EventBar[] = []
    
    // 週の各日の日付文字列を取得
    const weekDateStrings = weekDays.value.map(
        day => toLocalDateString(day)
    )
    
    props.events.forEach(event => {
        const eventStartStr = event.start_date.split('T')[0]
        const eventEndStr = (event.end_date || event.start_date).split('T')[0]
        
        const isMultiDay = eventStartStr !== eventEndStr || event.is_all_day
        if (!isMultiDay) return
        
        const weekStartStr = weekDateStrings[0]
        const weekEndStr = weekDateStrings[6]
        
        // 週と重ならない予定は除外
        if (eventEndStr < weekStartStr || eventStartStr > weekEndStr) {
            return
        }
        
        // 週境界でクリップ
        const actualStartStr = eventStartStr >= weekStartStr ? eventStartStr : weekStartStr
        const actualEndStr = eventEndStr <= weekEndStr ? eventEndStr : weekEndStr
        
        const startDayIndex = weekDateStrings.findIndex(d => d === actualStartStr)
        const endDayIndex = weekDateStrings.findIndex(d => d === actualEndStr)
        
        if (startDayIndex === -1 || endDayIndex === -1) return
        
        let startHour = START_HOUR
        let endHour = END_HOUR
        
        if (!event.is_all_day && event.start_time && event.end_time) {
            startHour = Math.max(START_HOUR, parseInt(event.start_time.split(':')[0]))
            endHour = Math.min(END_HOUR, parseInt(event.end_time.split(':')[0]) + 1)
        }
        
        bars.push({
            event,
            startDayIndex,
            endDayIndex,
            startHour,
            endHour,
            stackIndex: 0
        })
    })

    // 優先度と締切日でソート
    bars.sort((a, b) => {
        const getPriorityWeight = (importance: string | null) => {
            switch (importance) {
                case '重要': return 3
                case '中': return 2
                case '低': return 1
                default: return 0
            }
        }
        
        const priorityA = getPriorityWeight(a.event.importance)
        const priorityB = getPriorityWeight(b.event.importance)
        if (priorityA !== priorityB) return priorityB - priorityA
        
        const endDateA = toComparableDate(
            (a.event.end_date || a.event.start_date).split('T')[0]
        )
        const endDateB = toComparableDate(
            (b.event.end_date || b.event.start_date).split('T')[0]
        )

        if (endDateA.getTime() !== endDateB.getTime()) {
            return endDateA.getTime() - endDateB.getTime()
        }
        
        if (a.startDayIndex !== b.startDayIndex) {
            return a.startDayIndex - b.startDayIndex
        }
        return a.endDayIndex - b.endDayIndex
    })
    
    // スタックインデックスを計算（空きスペースを活用）
    bars.forEach((bar, i) => {
        let stackIndex = 0
        while (true) {
            const hasConflict = bars.slice(0, i).some(other =>
                other.stackIndex === stackIndex &&
                !(other.endDayIndex < bar.startDayIndex || other.startDayIndex > bar.endDayIndex)
            )
            if (!hasConflict) {
                bar.stackIndex = stackIndex
                break
            }
            stackIndex++
        }
    })

    return bars
})


// 各日の単日予定を抽出（単日予定のみ）
const dailyEvents = computed(() => {
    return weekDays.value.map(day => {
        const dateStr = toLocalDateString(day)
        return props.events.filter(event => {
            const start = event.start_date.split('T')[0]
            const end = (event.end_date || event.start_date).split('T')[0]
            // 単日予定のみを対象とし、複数日予定は週間サマリーエリアで表示
            return start === end && start === dateStr && !event.is_all_day
        }).sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''))
    })
})

const getEventColor = (category: string) => {
    const categoryColorMap: { [key: string]: string } = {
        '会議': '#42A5F5',
        '業務': '#66BB6A',
        '来客': '#FFA726',
        '出張': '#9575CD',
        '休暇': '#F06292',
    }
    return categoryColorMap[category] || '#6b7280'
}

const formatTime = (time: string | null) => {
    if (!time) return ''
    return time.substring(0, 5)
}

const getDayLabel = (date: Date) => {
    const weekdays = ['日', '月', '火', '水', '木', '金', '土']
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    const weekday = weekdays[date.getDay()]
    
    // 年跨ぎを考慮した表示
    const today = new Date()
    const showYear = year !== today.getFullYear()
    
    return showYear ? `${year}/${month}/${day}(${weekday})` : `${month}/${day}(${weekday})`
}

const isToday = (date: Date) => {
    return toLocalDateString(date) === toLocalDateString(new Date())
}


const getBarStyle = (bar: EventBar) => {
    const top = bar.stackIndex * 32
    const endColumn = Math.min(bar.endDayIndex + 2, 8)
    return {
        gridColumn: `${bar.startDayIndex + 1} / ${endColumn}`,
        top: `${top}px`
    }
}

const maxStackDepth = computed(() => {
    if (multiDayBars.value.length === 0) return 0
    return Math.max(...multiDayBars.value.map(b => b.stackIndex)) + 1
})

// リサイズハンドラー
const startResize = (e: MouseEvent) => {
    isResizing.value = true
    const startY = e.clientY
    const startHeight = summaryHeight.value
    
    const onMouseMove = (moveEvent: MouseEvent) => {
        const delta = moveEvent.clientY - startY
        const newHeight = Math.max(MIN_HEIGHT, Math.min(MAX_HEIGHT, startHeight + delta))
        summaryHeight.value = newHeight
    }
    
    const onMouseUp = () => {
        isResizing.value = false
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
        document.body.style.cursor = ''
        document.body.style.userSelect = ''
    }
    
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
    document.body.style.cursor = 'row-resize'
    document.body.style.userSelect = 'none'
}
</script>

<template>
    <div class="week-summary-view">
        <!-- 週間サマリーエリア -->
        <div v-if="multiDayBars.length > 0" class="summary-area" :style="{ height: summaryHeight + 'px' }">
            <div class="summary-header">複数日予定・終日予定</div>
            <div class="summary-timeline">
                <div class="timeline-grid">
                    <div
                        v-for="dayIndex in 7"
                        :key="dayIndex"
                        class="day-column"
                    >
                        <div class="day-column-header">{{ getDayLabel(weekDays[dayIndex - 1]) }}</div>
                    </div>
                </div>
                <div class="timeline-events" :style="{ height: maxStackDepth * 32 + 'px', minHeight: '32px' }">
                    <div
                        v-for="(bar, idx) in multiDayBars"
                        :key="idx"
                        class="timeline-event"
                        :class="{ 'important': bar.event.importance === '重要' }"
                        :style="{
                            ...getBarStyle(bar),
                            backgroundColor: getEventColor(bar.event.category),
                            borderColor: bar.event.importance === '重要' ? '#dc2626' : getEventColor(bar.event.category)
                        }"
                        @click="emit('eventClick', bar.event)"
                        @mouseenter="(e) => emit('eventHover', bar.event, { x: e.clientX, y: e.clientY })"
                        @mouseleave="() => emit('eventHover', null, { x: 0, y: 0 })"
                    >
                        <span class="event-title">{{ bar.event.title }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- リサイズハンドル -->
        <div 
            v-if="multiDayBars.length > 0"
            class="resize-handle"
            :class="{ 'resizing': isResizing }"
            @mousedown="startResize"
        >
            <svg class="resize-indicator" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="12" r="1"/>
                <circle cx="9" cy="5" r="1"/>
                <circle cx="9" cy="19" r="1"/>
                <circle cx="15" cy="12" r="1"/>
                <circle cx="15" cy="5" r="1"/>
                <circle cx="15" cy="19" r="1"/>
            </svg>
        </div>

        <!-- 曜日別レーン -->
        <div class="daily-lanes">
            <div
                v-for="(day, index) in weekDays"
                :key="index"
                class="day-lane"
                :class="{ 'today': isToday(day) }"
            >
                <div class="day-header" @click="emit('dateClick', day)">
                    <div class="day-label">{{ getDayLabel(day) }}</div>
                    <div class="event-count">{{ dailyEvents[index].length }}件</div>
                </div>
                <div class="day-events">
                    <div
                        v-for="event in dailyEvents[index]"
                        :key="event.event_id"
                        class="day-event"
                        :class="{ 'important': event.importance === '重要' }"
                        :style="{
                            backgroundColor: getEventColor(event.category),
                            borderColor: event.importance === '重要' ? '#dc2626' : getEventColor(event.category)
                        }"
                        @click="emit('eventClick', event)"
                        @mouseenter="(e) => emit('eventHover', event, { x: e.clientX, y: e.clientY })"
                        @mouseleave="() => emit('eventHover', null, { x: 0, y: 0 })"
                    >
                        <div class="event-time">
                            {{ formatTime(event.start_time) }}–{{ formatTime(event.end_time) }}
                        </div>
                        <div class="event-title">{{ event.title }}</div>
                    </div>
                    <div v-if="dailyEvents[index].length === 0" class="no-events">
                        予定なし
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.week-summary-view {
    display: flex;
    flex-direction: column;
    padding: 16px;
    height: 100%;
}

.summary-area {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.dark .summary-area {
    background: #09090b;
    border-color: #374151;
}

.summary-header {
    padding: 12px 16px;
    background: #f3f4f6;
    font-weight: 600;
    font-size: 14px;
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
}

.dark .summary-header {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
}

.summary-timeline {
    flex: 1;
    overflow-y: auto;
    position: relative;
}

.timeline-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-bottom: 1px solid #e5e7eb;
}

.day-column {
    border-right: 1px solid #e5e7eb;
    padding: 8px;
    padding: 8px;
    text-align: center;
}

.dark .day-column {
    border-color: #374151;
}

.day-column:last-child {
    border-right: none;
}

.day-column-header {
    font-size: 12px;
    font-weight: 600;
    font-weight: 600;
    color: #6b7280;
}

.dark .day-column-header {
    color: #9ca3af;
}

.timeline-events {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    position: relative;
    min-height: 64px;
}

.timeline-event {
    position: relative;
    height: 32px;
    border-radius: 4px;
    border: 2px solid;
    padding: 0 8px;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
    margin: 0px 4px;
}

.timeline-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.timeline-event.important {
    border-width: 3px;
}

.timeline-event .event-title {
    color: white;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.resize-handle {
    height: 12px;
    cursor: row-resize;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    transition: background 0.2s;
    margin: 6px 0;
    position: relative;
}

.resize-handle::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 10;
}

.resize-handle::after {
    content: '';
    height: 4px;
    width: 100%;
    background: #d1d5db;
    transition: background 0.2s;
    pointer-events: none;
    position: relative;
    z-index: 0;
}

.resize-handle:hover::after {
    background: #3b82f6;
}

.resize-handle.resizing::after {
    background: #3b82f6;
}

.resize-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(90deg);
    z-index: 5;
    pointer-events: none;
    color: #9ca3af;
    transition: color 0.2s;
}

.resize-handle:hover .resize-indicator {
    color: white;
}

.daily-lanes {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
    flex: 1;
    overflow-y: auto;
}

.day-lane {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    overflow: hidden;
    background: white;
}

.dark .day-lane {
    border-color: #374151;
    background: #09090b; /* bg-background */
}

.day-lane.today {
    border-color: #3b82f6;
    border-width: 2px;
}

.day-header {
    padding: 12px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
    transition: background 0.2s;
}

.dark .day-header {
    background: #1f2937;
    border-color: #374151;
}

.day-header:hover {
    background: #f3f4f6;
}

.dark .day-header:hover {
    background: #374151;
}

.day-lane.today .day-header {
    background: #eff6ff;
}

.dark .day-lane.today .day-header {
    background: rgba(59, 130, 246, 0.2);
}

.day-lane.today .day-label {
    color: #2563eb;
}

.dark .day-lane.today .day-label {
    color: #60a5fa;
}

.day-label {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}

.event-count {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
}

.dark .day-column-header {
    color: #9ca3af;
}

.day-events {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 150px;
    overflow-y: auto;
}

.day-event {
    padding: 8px 10px;
    border-radius: 4px;
    border: 2px solid;
    cursor: pointer;
    transition: all 0.2s;
}

.day-event:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.day-event.important {
    border-width: 3px;
}

.event-time {
    font-size: 11px;
    color: white;
    font-weight: 600;
    margin-bottom: 2px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.event-title {
    font-size: 13px;
    color: white;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.no-events {
    text-align: center;
    color: #9ca3af;
    font-size: 13px;
    padding: 40px 0;
}
</style>
