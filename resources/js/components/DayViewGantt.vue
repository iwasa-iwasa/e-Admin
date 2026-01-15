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

const snapTo15 = (min: number) => Math.floor(min / 15) * 15


// タブの定義
const scopeRanges = computed(() => {
    const now = new Date()
    const nowMin = now.getHours() * 60 + now.getMinutes()

    switch (props.timeScope) {
        case 'current':
            const currentHour = now.getHours()
            const start = Math.max(DAY_START_MIN, (currentHour - 2) * 60)
            const end = Math.min(DAY_END_MIN, start + 240)
            return { start, end }
        case 'before':
            return { start: 7 * 60, end: 11 * 60 }
        case 'middle':
            return { start: 11 * 60, end: 15 * 60 }
        case 'after':
            return { start: 15 * 60, end: 19 * 60 }
        default:
            return { start: 7 * 60, end: 19 * 60 }
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

const timeGridWidth = computed(() => {
  const { start, end } = scopeRanges.value
  return (end - start) * pxPerMin.value
})

const hourWidth = computed(() => 60 * pxPerMin.value)


// ========== ユーティリティ関数 ==========
const parseTime = (timeStr: string | null, fallback = 0): number => {
  if (!timeStr) return fallback
  const [hours, minutes] = timeStr.split(':').map(Number)
  return hours * 60 + minutes
}


// ========== 予定データ処理 ==========
const todayEvents = computed(() => {
    const dateStr = props.currentDate.toISOString().split('T')[0]
    return localEvents.value.filter(event => {
        const eventStart = event.start_date.split('T')[0]
        const eventEnd = event.end_date.split('T')[0]
        return eventStart <= dateStr && dateStr <= eventEnd
    })
})

const memberEvents = computed(() => {
    const scope = scopeRanges.value
    
    return teamMembers.value.map(member => {
        const events = todayEvents.value.filter(event => 
            event.participants?.some(p => p.id === member.id)
        )
        
        // 現在のスコープに一切かからない予定を除外
        const scopedEvents = events.filter(event => {
            const eventStart = parseTime(event.start_time || '00:00:00')
            const eventEnd = parseTime(event.end_time || '23:59:59')
            return eventEnd > scope.start && eventStart < scope.end
        })
        
        const sortedEvents = scopedEvents.sort((a, b) => {
            const aTime = a.start_time || '00:00:00'
            const bTime = b.start_time || '00:00:00'
            return aTime.localeCompare(bTime)
        })
        
        // レーン分け
        const lanes: App.Models.Event[][] = []
        sortedEvents.forEach(event => {
            const eventStart = parseTime(event.start_time || '00:00:00')
            const eventEnd = parseTime(event.end_time || '23:59:59')
            
            let placed = false
            for (let i = 0; i < lanes.length; i++) {
                const lane = lanes[i]
                const canPlace = lane.every(existing => {
                    const existingStart = parseTime(existing.start_time || '00:00:00')
                    const existingEnd = parseTime(existing.end_time || '23:59:59')
                    return eventEnd <= existingStart || eventStart >= existingEnd
                })
                
                if (canPlace) {
                    lane.push(event)
                    placed = true
                    break
                }
            }
            
            if (!placed) {
                lanes.push([event])
            }
        })
        
        return { member, lanes }
    })
})

// ========== スタイル計算（pxベース/分基準） ==========
const getEventStyle = (event: App.Models.Event) => {
    const start = parseTime(event.start_time, DAY_START_MIN)
    const end = parseTime(event.end_time, DAY_END_MIN)
    const scope = scopeRanges.value

    const visibleStart = Math.max(start, scope.start)
    const visibleEnd = Math.min(end, scope.end)

    if (visibleEnd <= visibleStart) {
        return { display: 'none' }
    }
    
    const leftPx = (visibleStart - scope.start) * pxPerMin.value
    const widthPx = (visibleEnd - visibleStart) * pxPerMin.value


    return {
        left: `${leftPx}px`,
        width: `${widthPx}px`
    }
}

const viewportWidth = ref(760)

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
    
    //viewport幅初期設定＆リサイズ監視
    updateViewportWidth()
    window.addEventListener('resize', updateViewportWidth)

    if (props.timeScope === 'current') {
        nextTick(focusToCurrentTime)
    }
})
    
onUnmounted(() => {
    removeListener?.()
    window.removeEventListener('resize', updateViewportWidth)
})


const MEMBER_COLUMN_WIDTH = 150
const CURRENT_SCALE = 0.934

const pxPerMin = computed(() => {
  const { start, end } = scopeRanges.value
  const duration = end - start

  // current は「見やすさ優先」
  if (props.timeScope === 'current') {
    return (
        (viewportWidth.value - MEMBER_COLUMN_WIDTH) / duration
    )* CURRENT_SCALE
  }
  if (props.timeScope !== 'all') {
    return (
        (viewportWidth.value - MEMBER_COLUMN_WIDTH) / duration
    )* CURRENT_SCALE
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
    const now = new Date()
    const min = now.getHours() * 60 + now.getMinutes()
    const { start, end } = scopeRanges.value
    
    if (min < start || min > end) return null
    
    return (min - start) * pxPerMin.value
})

const currentTimeText = computed(() => {
    const now = new Date()
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
    const events = todayEvents.value.filter(event =>
      event.participants?.some(p => p.id === member.id)
    )

    const countInRange = (start: number, end: number) => {
      return events.filter(e => {
        const s = parseTime(e.start_time)
        const eTime = parseTime(e.end_time)
        return eTime > start && s < end
      })
    }

    return {
      member,
      before: countInRange(7 * 60, 11 * 60),
      middle: countInRange(11 * 60, 15 * 60),
      after: countInRange(15 * 60, 19 * 60),
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
        </div>
        <!-- 俯瞰用（クリックでスコープ遷移） -->
        <div class="summary-total-card">
            <div class="summary-total-title">本日のサマリー</div>

            <div class="summary-total-grid">
                <button
                    class="summary-total-item"
                    @click="emit('select-scope','before')"
                >
                    <div class="label">前</div>
                    <div class="count">{{ formatCount(totalSummary.beforeEvents) }}</div>
                </button>

                <button
                    class="summary-total-item"
                    @click="emit('select-scope','middle')"
                >
                    <div class="label">中</div>
                    <div class="count">{{ formatCount(totalSummary.middleEvents) }}</div>
                </button>

                <button
                    class="summary-total-item"
                    @click="emit('select-scope','after')"
                >
                    <div class="label">後</div>
                    <div class="count">{{ formatCount(totalSummary.afterEvents) }}</div>
                </button>
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
                v-for="{ member, lanes } in memberEvents"
                :key="member.id"
                class="member-row"
                :style="{ minHeight: `${Math.max(4.375, lanes.length * 4)}rem` }"
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
                        <div
                            v-for="(lane, laneIndex) in lanes"
                            :key="laneIndex"
                            class="event-lane"
                        >
                            <div
                                v-for="event in lane"
                                :key="event.event_id"
                                class="event-bar"
                                :class="{ 'important': event.importance === '重要' }"
                                :style="{
                                    ...getEventStyle(event),
                                    backgroundColor: getEventColor(event.category),
                                    borderColor: event.importance === '重要' ? '#dc2626' : getEventColor(event.category)
                                }"
                                @click="handleEventClick(event)"
                                @mouseenter="handleEventHover(event, $event)"
                                @mouseleave="handleEventHover(null, $event)"
                            >
                                <span class="event-title">{{ event.title }}</span>
                            </div>
                        </div>
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
  margin-bottom: 0.75rem;
}

.summary-total-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.summary-total-item {
  all: unset;
  cursor: pointer;
  padding: 0.75rem;
  border-radius: 0.5rem;
  background: white;
  border: 1px solid #e5e7eb;
  text-align: center;
  transition: background-color 0.2s, box-shadow 0.2s;
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

.event-lane {
    position: relative;
    height: 3.75rem;
    margin: 0.25rem 0;
}

.is-current-scope .event-bar {
  transition: transform 0.12s;
}

.is-current-scope .current-time-line {
  width: 3px;
}

.is-current-scope .event-title {
  max-width: 100%;
}


.event-bar {
    position: absolute;
    height: 3.25rem;
    border-radius: 0.25rem;
    border: 2px solid;
    padding: 0 0.75rem;
    display: flex;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
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

.event-title {
    font-size: 0.875rem;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.2);
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
</style>
