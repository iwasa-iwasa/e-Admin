<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted, nextTick} from 'vue'
import { usePage, router } from '@inertiajs/vue3'

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
const DAY_START_MIN = DAY_START_HOUR * 60
const DAY_END_MIN = DAY_END_HOUR * 60
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

// 性能最適化：メンバー別にdisplayEventsを事前分解
const displayEventsByMember = computed(() => {
    const eventsByMember = new Map<number, DisplayEvent[]>()
    
    displayEvents.value.forEach(event => {
        // 参加者が設定されている場合は参加者に割り当て
        if (event.original.participants && event.original.participants.length > 0) {
            event.original.participants.forEach(participant => {
                if (!eventsByMember.has(participant.id)) {
                    eventsByMember.set(participant.id, [])
                }
                eventsByMember.get(participant.id)!.push(event)
            })
        } else {
            // 参加者未設定の場合は作成者のレーンに表示
            const creatorId = event.original.created_by
            if (creatorId) {
                if (!eventsByMember.has(creatorId)) {
                    eventsByMember.set(creatorId, [])
                }
                eventsByMember.get(creatorId)!.push(event)
            }
        }
    })
    
    return eventsByMember
})

const memberEvents = computed(() => {
    const scope = scopeRanges.value
    
    return teamMembers.value.map(member => {
        // 最適化：事前分解されたメンバー別イベントを取得
        const memberDisplayEvents = displayEventsByMember.value.get(member.id) || []
        
        // 現在のスコープに一切かからない予定を除外
        const scopedEvents = memberDisplayEvents.filter(event => {
            return event.end > scope.start && event.start < scope.end
        })
        
        // メンバーごとにスタック計算
        const sorted = [...scopedEvents].sort((a, b) => {
            if (a.priority !== b.priority) {
                return b.priority - a.priority // 高い方が先
            }
            return a.start - b.start
        })

        const lanes: number[] = []
        const stackedEvents: StackedEvent[] = []

        for (const event of sorted) {
            let lane = 0
            while (lanes[lane] !== undefined && lanes[lane] > event.start) {
                lane++
            }

            lanes[lane] = event.end
            stackedEvents.push({ ...event, lane })
        }
        
        // laneごとにグループ化
        const maxLane = Math.max(-1, ...stackedEvents.map(e => e.lane))
        const laneGroups: StackedEvent[][] = Array.from({ length: maxLane + 1 }, () => [])
        
        stackedEvents.forEach(event => {
            laneGroups[event.lane].push(event)
        })
        
        return { member, lanes: laneGroups, maxLaneIndex: stackedEvents.length === 0 ? -1 : maxLane }
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
    
    // current 時のみ「1時間単位」で更新（二重実行防止ガード追加）
    if (props.timeScope === 'current' && !currentScopeTimer) {
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
        resizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) {
                if (props.timeScope !== 'all') {
                    viewportWidth.value = entry.contentRect.width
                }
            }
        })
        resizeObserver.observe(ganttContainerRef.value)
        updateViewportWidth()
    }

    if (props.timeScope === 'current') {
        nextTick(focusToCurrentTime)
    }
})

// timeScope変更時のタイマー管理とviewportWidth更新
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
    
    // ResizeObserver幅更新問題の対策
    updateViewportWidth()
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
  const usableWidth = Math.max(0, viewportWidth.value - MEMBER_COLUMN_WIDTH)
  
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
    const events = todayEvents.value.filter(event => {
      // 参加者が設定されている場合は参加者でフィルタリング
      if (event.participants && event.participants.length > 0) {
        return event.participants.some(p => p.id === member.id)
      }
      // 参加者未設定の場合は作成者でフィルタリング
      return event.created_by === member.id
    })

    const countInRange = (start: number, end: number) => {
      // 最適化：事前分解されたメンバー別イベントを使用
      const memberEvents = displayEventsByMember.value.get(member.id) || []
      return memberEvents.filter(e => {
        return e.end > start && e.start < end
      })
    }

    return {
      member,
      before: countInRange(DAY_START_MIN, 11 * 60),
      middle: countInRange(11 * 60, 15 * 60),
      after: countInRange(15 * 60, DAY_END_MIN),
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


const formatCount = (events: App.Models.Event[]) => {
  const total = events.length
  const important = events.filter(e => e.importance === '重要').length
  return important > 0 ? `${total}件 (重要：${important}件)` : `${total}件`
}

// ========== ③ 日集計（本日全体サマリー）の正しい集計ロジック ==========
const dailySummary = computed(() => {
  // Map化でO(n²)をO(n)に最適化
  const eventMap = new Map<number, App.Models.Event>()
  
  todayEvents.value.forEach(event => {
    eventMap.set(event.event_id, event)
  })
  
  return Array.from(eventMap.values())
})

// ========== 共通関数：複数日イベントの表示用時間正規化 ==========
const getEventTimeForDate = (
  event: App.Models.Event,
  date: Date
) => {
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

const displayEvents = computed<DisplayEvent[]>(() => {
  return todayEvents.value.map(event => {
    const { start, end } = getEventTimeForDate(event, props.currentDate)

    // importance を priority に変換（重要=2, 中=1, 低=0）
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

.summary-header .summary-cell {
    background: #f9fafb;
    font-weight: 600;
}

.summary-cell.clickable {
    cursor: pointer;
    transition: background-color 0.2s;
}

.summary-cell.clickable:hover {
    background: #f3f4f6;
}

.summary-total {
    background: #f3f4f6;
    font-weight: 600;
}

.summary-total .summary-cell {
    background: #f3f4f6;
}

.summary-total-card {
  margin-top: 1.5rem;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  background: #f9fafb;
}

.summary-total-title {
  font-weight: 600;
  font-size: 0.875rem;
  color: #374151;
  margin-bottom: 0.5rem;
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


.summary-total-item:hover {
  background: #f3f4f6;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.summary-total-item .label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.summary-total-item .count {
  font-size: 0.875rem;
  font-weight: 600;
}

.summary-total-row .summary-cell {
  background: #f3f4f6;
  font-weight: 600;
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
    background: white;
    border-bottom: 2px solid #e5e7eb;
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

.time-cell.work-hours {
    background: #eff6ff;
}

.time-cell-bg {
    border-right: 1px solid #e5e7eb;
}

.time-cell-bg.work-hours {
    background: #f8fafc;
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

.member-column {
    width: var(--member-column-width);
    min-width: var(--member-column-width);
    padding: 0.75rem;
    border-right: 1px solid #e5e7eb;
    background: white;
    flex-shrink: 0;
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
    font-weight: 500;
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
    background: #f9fafb;
    min-height: 2.5rem;
}

.member-column-footer {
    width: var(--member-column-width);
    min-width: var(--member-column-width);
    border-right: 1px solid #e5e7eb;
    flex-shrink: 0;
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

.daily-summary-title {
  font-weight: 600;
  font-size: 1rem;
  color: #374151;
  margin-bottom: 1rem;
  text-align: center;
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

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #374151;
}

</style>
