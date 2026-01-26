<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted, nextTick} from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { getEventColor } from '@/constants/calendar'

const props = defineProps<{
    events: App.Models.Event[]
    currentDate: Date
    timeScope: 'all' | 'current' | 'before' | 'middle' | 'after'
}>()

const emit = defineEmits<{
    eventClick: [event: App.Models.Event]
    eventHover: [event: App.Models.Event | null, position: { x: number, y: number }]
    'select-scope': ['before' | 'middle' | 'after']
}>()

const page = usePage()
const teamMembers = computed(() => (page.props as any).teamMembers || [])
const localEvents = ref<App.Models.Event[]>([...props.events])

// propsの変更を監視してリアルタイム更新
watch(() => props.events, (newEvents) => {
    localEvents.value = [...newEvents]
}, { deep: true })



// ========== 時間軸設定 ==========
const DAY_START_HOUR = 7
const DAY_END_HOUR = 19
const DAY_START_MIN: number = DAY_START_HOUR * 60
const DAY_END_MIN: number = DAY_END_HOUR * 60
const workStartHour = 8
const workEndHour = 17

// ⑤ current スコープの時間更新修正
const nowRef = ref(new Date())
let currentScopeTimer: number | null = null
let minuteTimer: number | null = null


// タブの定義
const scopeRanges = computed(() => {
    switch (props.timeScope) {
        case 'current': {
            const currentHour = nowRef.value.getHours()
            const start = Math.max(DAY_START_MIN, currentHour * 60)
            const end = Math.min(DAY_END_MIN, start + 240)
            return { start, end }
        }
        case 'before':
            return { start: DAY_START_MIN, end: 11 * 60 }
        case 'middle':
            return { start: 11 * 60, end: 15 * 60 }
        case 'after':
            return { start: 15 * 60, end: DAY_END_MIN }
        default:
            return { start: DAY_START_MIN, end: DAY_END_MIN }
    }
})

const visibleHours = computed(() => {
  const { start, end } = scopeRanges.value
  const startHour = Math.floor(start / 60)
  const endHour = Math.ceil(end / 60)

  return Array.from(
    { length: endHour - startHour },
    (_, i) => startHour + i
  )
})

// ② 表示時間長を表す computed を追加
const scopeDuration = computed(() => {
  const { start, end } = scopeRanges.value
  return end - start
})

const timeGridWidth = computed(() => {
  return scopeDuration.value * pxPerMin.value
})

const hourWidth = computed(() => 60 * pxPerMin.value)


// ========== ユーティリティ関数 ==========
const parseTime = (timeStr: string | null, fallback: number): number => {
  if (!timeStr) return fallback
  const [hours, minutes] = timeStr.split(':').map(Number)
  return hours * 60 + minutes
}


// ========== 予定データ処理 ==========
const todayEvents = computed(() => {
    const d = props.currentDate
    const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    return localEvents.value.filter(event => {
        const eventStart = event.start_date.split('T')[0]
        const eventEnd = event.end_date.split('T')[0]
        return eventStart <= dateStr && dateStr <= eventEnd
    })
})

// ========== 正規化イベント（日付補正のみ） ==========
const normalizedEvents = computed<DisplayEvent[]>(() => {
  return todayEvents.value.map(event => {
    const { start, end } = getEventTimeForDate(event, props.currentDate)

    const priorityMap = { '重要': 2, '中': 1, '低': 0 }
    const priority = priorityMap[event.importance as keyof typeof priorityMap] ?? 0

    return {
      id: event.event_id,
      original: event,
      start,
      end,
      priority,
    }
  })
})

// ========== メンバー別分解（Map一本化・軽量） ==========
const memberEventMap = computed<Map<number, DisplayEvent[]>>(() => {
  const map = new Map<number, DisplayEvent[]>()

  for (const event of normalizedEvents.value) {
    event.original.participants?.forEach(p => {
      if (!map.has(p.id)) map.set(p.id, [])
      map.get(p.id)!.push(event)
    })
  }

  return map
})

const memberEvents = computed(() => {
  const scope = scopeRanges.value

  return teamMembers.value.map(member => {
    const events =
      memberEventMap.value.get(member.id)?.filter(e =>
        e.end > scope.start && e.start < scope.end
      ) ?? []

    const sorted = [...events].sort((a, b) =>
      a.priority !== b.priority
        ? b.priority - a.priority
        : a.start - b.start
    )

    const lanesEnd: number[] = []
    const stacked: StackedEvent[] = []

    for (const e of sorted) {
      let lane = 0
      while (lanesEnd[lane] !== undefined && lanesEnd[lane] > e.start) {
        lane++
    }
      lanesEnd[lane] = e.end
      stacked.push({ ...e, lane })
    }

    const maxLane = Math.max(-1, ...stacked.map(e => e.lane))
    const laneGroups: StackedEvent[][] =
        Array.from({ length: maxLane + 1 }, () => [])


    stacked.forEach(e => laneGroups[e.lane].push(e))

    return { member, lanes: laneGroups, maxLaneIndex: maxLane }
  })
})

// ========== スタイル計算（laneベース描画） ==========
const EVENT_HEIGHT = 3.25
const ROW_GAP = 0.75
const ROW_HEIGHT = EVENT_HEIGHT + ROW_GAP

const getEventStyle = (event: StackedEvent) => {
    const scope = scopeRanges.value

    const visibleStart = Math.max(event.start, scope.start)
    const visibleEnd = Math.min(event.end, scope.end)

    if (visibleEnd <= visibleStart) {
        return { display: 'none' }
    }
    
    const leftPx = (visibleStart - scope.start) * pxPerMin.value
    const widthPx = (visibleEnd - visibleStart) * pxPerMin.value
    const topPx = event.lane * ROW_HEIGHT

    return {
        left: `${leftPx}px`,
        width: `${widthPx}px`,
        top: `${topPx}rem`
    }
}

const viewportWidth = ref(760)
let resizeObserver: ResizeObserver | null = null

const updateViewportWidth = () => {
  if (props.timeScope !== 'all' && ganttContainerRef.value) {
    viewportWidth.value = ganttContainerRef.value.clientWidth
  }
}

// Inertia訪問後の自動更新
let removeListener: (() => void) | null = null

onMounted(() => {
    removeListener = router.on('success', () => {
        localEvents.value = [...props.events]
    })
    
    // current 時のみ「1時間単位」で更新
    if (props.timeScope === 'current') {
        currentScopeTimer = window.setInterval(() => {
            const now = new Date()
            if (now.getMinutes() === 0) {
                nowRef.value = now
            }
        }, 60 * 1000)
    }
    
    // 現在時刻表示用に毎分更新（全スコープで必要）
    minuteTimer = window.setInterval(() => {
        nowRef.value = new Date()
    }, 60 * 1000)
    
    // ResizeObserverでコンテナ幅を監視
    if (ganttContainerRef.value) {
        resizeObserver = new ResizeObserver(entries => {
        viewportWidth.value = entries[0].contentRect.width
        })
        resizeObserver.observe(ganttContainerRef.value)
    }

    if (props.timeScope === 'current') {
        nextTick(focusToCurrentTime)
    }
})

// timeScope変更時のタイマー管理
watch(() => props.timeScope, (scope) => {
    if (scope === 'current' && !currentScopeTimer) {
        currentScopeTimer = window.setInterval(() => {
            const now = new Date()
            if (now.getMinutes() === 0) {
                nowRef.value = now
            }
        }, 60 * 1000)
    } else if (scope !== 'current' && currentScopeTimer) {
        clearInterval(currentScopeTimer)
        currentScopeTimer = null
    }
})
    
onUnmounted(() => {
    removeListener?.()
    resizeObserver?.disconnect()
    if (currentScopeTimer) clearInterval(currentScopeTimer)
    if (minuteTimer) clearInterval(minuteTimer)
})


const MEMBER_COLUMN_WIDTH = 150
const CURRENT_SCALE = 0.934

const pxPerMin = computed(() => {
  const rawWidth: number = viewportWidth.value - MEMBER_COLUMN_WIDTH
  const usableWidth = Math.max(rawWidth, 0)

  // current は「見やすさ優先」
  if (props.timeScope === 'current') {
    return (usableWidth / 240) * CURRENT_SCALE
  }

  if (props.timeScope !== 'all') {
    return (usableWidth / scopeDuration.value) * CURRENT_SCALE
  }

  // all は従来どおり（疎）
  return 190 / 60
})



// getEventColor replaced by import

// ========== 現在時刻表示 ==========
const currentTimePosition = computed(() => {
    const now = nowRef.value
    const min = now.getHours() * 60 + now.getMinutes()
    const { start, end } = scopeRanges.value
    
    if (min < start || min > end) return null
    
    return (min - start) * pxPerMin.value
})

const currentTimeText = computed(() => {
    const now = nowRef.value
    return now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' })
})

const isToday = computed(() => {
    const today = new Date()
    return props.currentDate.toDateString() === today.toDateString()
})

// ========== イベントハンドラ ==========
const handleEventClick = (event: App.Models.Event) => {
    emit('eventClick', event)
}

const handleEventHover = (event: App.Models.Event | null, mouseEvent: MouseEvent) => {
    if (event) {
        emit('eventHover', event, { x: mouseEvent.clientX, y: mouseEvent.clientY })
    } else {
        emit('eventHover', null, { x: 0, y: 0 })
    }
}

watch(
  () => props.timeScope,
  (scope) => {
    if (scope === 'current') {
      nextTick(() => {
        focusToCurrentTime()
      })
    }
  }
)

const ganttContainerRef = ref<HTMLElement | null>(null)

    const focusToCurrentTime = () => {
  if (!ganttContainerRef.value || currentTimePosition.value === null) return

  const container = ganttContainerRef.value
  const containerWidth = container.clientWidth

  const targetX =
    currentTimePosition.value - containerWidth / 2

  container.scrollTo({
    left: Math.max(0, targetX),
    behavior: 'smooth'
  })
}

const summaryByMember = computed(() => {
  return teamMembers.value.map(member => {
    const events = memberEventMap.value.get(member.id) ?? []

    const count = (s: number, e: number) =>
      events.filter(ev => ev.end > s && ev.start < e)

    return {
      member,
      before: count(DAY_START_MIN, 11 * 60),
      middle: count(11 * 60, 15 * 60),
      after: count(15 * 60, DAY_END_MIN),
    }
  })
})

const totalSummary = computed(() => {
  return {
    beforeEvents: summaryByMember.value.flatMap(r => r.before),
    middleEvents: summaryByMember.value.flatMap(r => r.middle),
    afterEvents: summaryByMember.value.flatMap(r => r.after),
  }
})


const formatCount = (events: { original: App.Models.Event }[]) => {
  const total = events.length
  const important = events.filter(e => e.original.importance === '重要').length
  return important > 0 ? `${total}件 (重要：${important}件)` : `${total}件`
}

// ========== ③ 日集計（本日全体サマリー）の正しい集計ロジック ==========
const dailySummary = computed(() => {
  // event_id でユニーク化して重複カウントを防ぐ
  const uniqueEvents = todayEvents.value.reduce((acc, event) => {
    if (!acc.some(e => e.event_id === event.event_id)) {
      acc.push(event)
    }
    return acc
  }, [] as App.Models.Event[])
  
  return uniqueEvents
})

// ========== 共通関数：複数日イベントの表示用時間正規化 ==========
type NormalizedTimeRange = {
  start: number
  end: number
}

const getEventTimeForDate = (
  event: App.Models.Event,
  date: Date
): NormalizedTimeRange => {
  const d = date
  const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const startDate = event.start_date.split('T')[0]
  const endDate = event.end_date.split('T')[0]

  // 初日
  if (dateStr === startDate) {
    return {
      start: parseTime(event.start_time, DAY_START_MIN),
      end: dateStr === endDate
        ? parseTime(event.end_time, DAY_END_MIN)
        : DAY_END_MIN,
    }
  }

  // 最終日
  if (dateStr === endDate) {
    return {
      start: DAY_START_MIN,
      end: parseTime(event.end_time, DAY_END_MIN),
    }
  }

  // 中日
  return {
    start: DAY_START_MIN,
    end: DAY_END_MIN,
  }
}

// 表示用イベント
type DisplayEvent = {
  id: number
  original: App.Models.Event
  start: number   // 分
  end: number     // 分
  priority: number
}

type StackedEvent = DisplayEvent & {
  lane: number
}

// 削除：全体でのstackedEventsは不要


</script>

<template>
    <div class="day-view-gantt"
    :class="{ 'is-current-scope': props.timeScope === 'current' }"
    ref="ganttContainerRef"
    >
    <template v-if="props.timeScope === 'all'">
        <!-- 詳細比較用（メンバー × 時間帯） -->
        <div class="summary-table">
            <div class="summary-header">
                <div class="summary-cell">メンバー</div>
                <div class="summary-cell clickable" @click="emit('select-scope','before')">前</div>
                <div class="summary-cell clickable" @click="emit('select-scope','middle')">中</div>
                <div class="summary-cell clickable" @click="emit('select-scope','after')">後</div>
            </div>
            <div v-for="{ member, before, middle, after } in summaryByMember" :key="member.id" class="summary-row">
                <div class="summary-cell">{{ member.name }}</div>
                <div class="summary-cell clickable" @click="emit('select-scope','before')">{{ formatCount(before) }}</div>
                <div class="summary-cell clickable" @click="emit('select-scope','middle')">{{ formatCount(middle) }}</div>
                <div class="summary-cell clickable" @click="emit('select-scope','after')">{{ formatCount(after) }}</div>
            </div>
            <div class="summary-row summary-total-row">
                <div class="summary-cell total">合計</div>
                <div class="summary-cell total">
                    {{ formatCount(totalSummary.beforeEvents) }}
                </div>
                <div class="summary-cell total">
                    {{ formatCount(totalSummary.middleEvents) }}
                </div>
                <div class="summary-cell total">
                    {{ formatCount(totalSummary.afterEvents) }}
                </div>
            </div>
        </div>

        <div class="summary-table"></div>

        <!-- ④ 本日全体サマリーのUI（横幅いっぱい） -->
        <div class="daily-summary-section">
            <div class="daily-summary-title">本日全体のサマリー</div>
            <div class="daily-summary-content">
                <div class="summary-stat">
                    <span class="stat-label">全予定数</span>
                    <span class="stat-value">{{ dailySummary.length }}件</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">重要予定数</span>
                    <span class="stat-value">{{ dailySummary.filter(e => e.importance === '重要').length }}件</span>
                </div>
            </div>
        </div>


        
    </template>



    <template v-else>
        <!-- ヘッダー：時間軸 -->
        <div class="gantt-header">
            <div class="member-column-header">メンバー</div>
            <div class="time-grid-header" :style="{ width: `${timeGridWidth}px` }">
                <div
                    v-for="hour in visibleHours"
                    :style="{ width: `${hourWidth}px` }"
                    :key="hour"
                    class="time-cell"
                    :class="{ 'work-hours': hour >= workStartHour && hour < workEndHour }"
                >
                    {{ hour }}:00
                </div>
            </div>
        </div>
        
        <!-- ボディ：メンバー × 予定 -->
        <div class="gantt-body">
            <div
                v-for="{ member, lanes, maxLaneIndex } in memberEvents"
                :key="member.id"
                class="member-row"
                :style="{ 
                    minHeight: maxLaneIndex >= 0 
                        ? `${Math.max(4.375, (maxLaneIndex + 1) * ROW_HEIGHT)}rem` 
                        : '4.375rem'
                }"
            >
                <div class="member-column">
                    <div class="member-info">
                        <div class="member-avatar" :style="{ backgroundColor: getEventColor('会議') }">
                            {{ member.name.charAt(0) }}
                        </div>
                        <span class="member-name">{{ member.name }}</span>
                    </div>
                </div>
                
                <div class="time-grid" :style="{ width: `${timeGridWidth}px` }">
                    <!-- 背景グリッド -->
                    <div class="time-grid-bg"  :style="{ width: `${timeGridWidth}px` }">
                        <div
                            v-for="hour in visibleHours"
                            :key="hour"
                            class="time-cell"
                            :style="{ width: `${hourWidth}px` }"
                        />
                    </div>
                    
                    <!-- 予定バー -->
                    <div class="events-container">
                        <template v-for="(lane, laneIndex) in lanes" :key="laneIndex">
                            <div
                                v-for="event in lane"
                                :key="event.id"
                                class="event-bar"
                                :class="{ 'important': event.original.importance === '重要' }"
                                :style="{
                                    ...getEventStyle(event),
                                    backgroundColor: getEventColor(event.original.category),
                                    borderColor: event.original.importance === '重要' ? '#dc2626' : getEventColor(event.original.category)
                                }"
                                @click="handleEventClick(event.original)"
                                @mouseenter="handleEventHover(event.original, $event)"
                                @mouseleave="handleEventHover(null, $event)"
                            >
                                <div class="event-content">
                                    <div class="event-title">{{ event.original.title }}</div>
                                    <div class="event-participants" v-if="event.original.participants && event.original.participants.length > 0">
                                        {{ event.original.participants.map(p => p.name).join(', ') }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- 現在時刻ライン -->
                    <div
                        v-if="isToday && currentTimePosition !== null"
                        class="current-time-line"
                        :style="{ left: `${currentTimePosition}px` }"
                    >
                        <div class="current-time-marker"/>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- フッター：現在時刻表示 -->
        <div v-if="isToday && currentTimePosition !== null" class="gantt-footer">
            <div class="member-column-footer"></div>
            <div class="time-grid-footer"  :style="{ width: `${timeGridWidth}px` }">
                <div class="current-time-indicator" :style="{ left: `${currentTimePosition}px` }">
                    <div class="current-time-text">現在時刻: {{ currentTimeText }}</div>
                </div>
            </div>
        </div>
    </template>
    </div>
</template>

<style scoped>
.summary-table {
    display: table;
    width: 100%;
    border-collapse: collapse;
}

.summary-header,
.summary-row {
    display: table-row;
}

.summary-cell {
    display: table-cell;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    text-align: center;
}

.dark .summary-cell {
    border-color: #374151;
}

.summary-header .summary-cell {
    background: #f9fafb;
    font-weight: 600;
}

.dark .summary-header .summary-cell {
    background: #1f2937;
    color: #e5e7eb;
}

.summary-cell.clickable {
    cursor: pointer;
    transition: background-color 0.2s;
}

.summary-cell.clickable:hover {
    background: #f3f4f6;
}

.dark .summary-cell.clickable:hover {
    background: #374151;
}

.summary-total {
    background: #f3f4f6;
    font-weight: 600;
}

.dark .summary-total {
    background: #1f2937;
}

.summary-total .summary-cell {
    background: #f3f4f6;
}

.dark .summary-total .summary-cell {
    background: #1f2937;
    color: #e5e7eb;
}

.summary-total-card {
  margin-top: 1.5rem;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: #f9fafb;
}

.dark .summary-total-card {
    border-color: #374151;
    background: #1f2937;
}

.summary-total-title {
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
    margin-bottom: 0.5rem;
}

.dark .summary-total-title {
    color: #e5e7eb;
}

.summary-total-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
}


.summary-total-item {
  all: unset;
  display: block;
  width: 100%;
  padding: 0.75rem;
  border-radius: 0.5rem;
  background: white;
  border: 1px solid #e5e7eb;
  cursor: default;
}

.dark .summary-total-item {
    background: #1f2937;
    border-color: #374151;
}

.summary-total-item:hover {
  background: #f3f4f6;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.dark .summary-total-item:hover {
    background: #374151;
    box-shadow: none;
}

.summary-total-item .label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.dark .summary-total-item .label {
    color: #9ca3af;
}

.summary-total-item .count {
  font-size: 0.875rem;
  font-weight: 600;
}

.dark .summary-total-item .count {
    color: #e5e7eb;
}

.summary-total-row .summary-cell {
  background: #f3f4f6;
  font-weight: 600;
}

.dark .summary-total-row .summary-cell {
    background: #1f2937;
    color: #e5e7eb;
}


/* ========== CSS変数：固定幅方式（B案） ========== */
.day-view-gantt {
    --member-column-width: 9.375rem;
}

/* ========== レイアウト ========== */
.day-view-gantt {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow-x: auto;
}

.gantt-header {
    display: flex;
    position: sticky;
    top: 0;
    z-index: 10;
    top: 0;
    z-index: 10;
    background: white;
    border-bottom: 2px solid #e5e7eb;
}

.dark .gantt-header {
    background: #09090b; /* bg-background */
    border-color: #374151;
}

.member-column-header {
    width: var(--member-column-width);
    min-width: var(--member-column-width);
    padding: 0.75rem;
    font-weight: 600;
    border-right: 1px solid #e5e7eb;
    background: #f9fafb;
    flex-shrink: 0;
}

.dark .member-column-header {
    border-color: #374151;
    background: #1f2937;
    color: #e5e7eb;
}

/* ========== 時間軸（固定幅） ========== */
.time-grid-header,
.time-grid-bg,
.time-grid-footer {
    display: flex;
    flex-shrink: 0;
}

.time-cell,
.time-cell-bg {
    flex: 0 0 auto;
}

.time-cell {
    padding: 0.75rem 0.5rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 500;
    border-right: 1px solid #e5e7eb;
    background: #f9fafb;
}

.dark .time-cell {
    border-color: #374151;
    background: #1f2937;
    color: #9ca3af;
}

.time-cell.work-hours {
    background: #eff6ff;
}

.dark .time-cell.work-hours {
    background: #1e293b; /* dark:bg-slate-900 like */
    color: #e5e7eb;
}

.time-cell-bg {
    border-right: 1px solid #e5e7eb;
}

.dark .time-cell-bg {
    border-color: #374151;
}

.time-cell-bg.work-hours {
    background: #f8fafc;
}

.dark .time-cell-bg.work-hours {
    background: rgba(30, 41, 59, 0.3);
}

/* ========== メンバー行 ========== */
.gantt-body {
    flex: 1;
}

.member-row {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    min-height: 4.375rem;
}

.dark .member-row {
    border-color: #374151;
}

.member-column {
    width: var(--member-column-width);
    min-width: var(--member-column-width);
    padding: 0.75rem;
    border-right: 1px solid #e5e7eb;
    background: white;
    flex-shrink: 0;
}

.dark .member-column {
    border-color: #374151;
    background: #09090b; /* bg-background */
}

.member-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.member-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.member-name {
    font-size: 0.875rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.dark .member-name {
    color: #e5e7eb;
}

/* ========== 時間グリッド ========== */
.time-grid {
    flex-shrink: 0;
    position: relative;
}

.time-grid-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

/* ========== 予定バー（%ベース配置） ========== */
.events-container {
    position: relative;
    height: 100%;
    padding: 0.25rem 0;
}

.event-bar {
    position: absolute;
    height: 3.25rem; /* EVENT_HEIGHT と同値 */
    border-radius: 0.25rem;
    border: 2px solid;
    padding: 0 0.75rem;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
}

.event-content {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    width: 100%;
    min-width: 0;
}

.event-title {
    font-size: 0.875rem;
    color: white;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.2);
}

.event-participants {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.2);
}

.event-bar:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.25rem 0.375rem -0.0625rem rgba(0, 0, 0, 0.1);
    z-index: 5;
}

.event-bar.important {
    border-width: 3px;
    font-weight: 600;
}

/* ========== 現在時刻表示 ========== */
.current-time-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #ef4444;
    z-index: 5;
    pointer-events: none;
}

.current-time-marker {
    position: absolute;
    top: -0.25rem;
    left: -0.25rem;
    width: 0.625rem;
    height: 0.625rem;
    border-radius: 50%;
    background: #ef4444;
}

/* ========== フッター ========== */
.gantt-footer {
    display: flex;
    border-top: 2px solid #e5e7eb;
    display: flex;
    border-top: 2px solid #e5e7eb;
    background: #f9fafb;
    min-height: 2.5rem;
}

.dark .gantt-footer {
    border-color: #374151;
    background: #1f2937;
}

.member-column-footer {
    width: var(--member-column-width);
    min-width: var(--member-column-width);
    border-right: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.dark .member-column-footer {
    border-color: #374151;
}

.time-grid-footer {
    flex-shrink: 0;
    position: relative;
}

.current-time-indicator {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
}

.current-time-text {
    background: #ef4444;
    color: white;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 0.125rem 0.375rem rgba(239, 68, 68, 0.3);
}

/* ========== ④ 本日全体サマリーのUI ========== */
.daily-summary-section {
  margin-top: 2rem;
  padding: 1.25rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
}

.dark .daily-summary-section {
    border-color: #374151;
    background: #1f2937;
}

.daily-summary-title {
  font-weight: 600;
  font-size: 1rem;
  color: #374151;
  margin-bottom: 1rem;
  text-align: center;
}

.dark .daily-summary-title {
    color: #e5e7eb;
}

.daily-summary-content {
  display: flex;
  justify-content: space-around;
  gap: 2rem;
}

.summary-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.stat-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.dark .stat-label {
    color: #9ca3af;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #374151;
}

.dark .stat-value {
    color: #e5e7eb;
}

</style>