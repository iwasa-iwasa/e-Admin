<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted } from 'vue'
import { useYearHeatmap } from '@/composables/calendar/useYearHeatmap'

interface DayInfo {
    date: Date
    dateStr: string
    day: number
    isCurrentMonth: boolean
}

const props = defineProps<{
    year: number
    memberId?: number | null
    calendarId: number
}>()

const emit = defineEmits<{
    dateClick: [date: Date]
}>()

const { heatmapReady, loading, fetchYearSummary, getDayLevel, getDaySummary } = useYearHeatmap()

// 月の配列
const months = computed(() => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])

// 指定された月の日付を生成（前月・翌月含む）
const getDaysInMonth = (month: number): DayInfo[] => {
    const firstDay = new Date(props.year, month - 1, 1)
    const lastDay = new Date(props.year, month, 0)
    const daysInMonth = lastDay.getDate()
    const startDayOfWeek = firstDay.getDay() // 0=日曜
    
    const days: DayInfo[] = []
    
    // 前月の日付を追加
    if (startDayOfWeek > 0) {
        const prevMonthLastDay = new Date(props.year, month - 1, 0).getDate()
        for (let i = startDayOfWeek - 1; i >= 0; i--) {
            const day = prevMonthLastDay - i
            const date = new Date(props.year, month - 2, day)
            const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
            days.push({ date, dateStr, day, isCurrentMonth: false })
        }
    }
    
    // 当月の日付を追加
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(props.year, month - 1, day)
        const dateStr = `${props.year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        days.push({ date, dateStr, day, isCurrentMonth: true })
    }
    
    // 翌月の日付を追加（6週分確保）
    const remainingCells = 42 - days.length // 6週 × 7日
    for (let day = 1; day <= remainingCells; day++) {
        const date = new Date(props.year, month, day)
        const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        days.push({ date, dateStr, day, isCurrentMonth: false })
    }
    
    return days
}

// フィルター変更時に必ず再取得
watch(
    [() => props.year, () => props.memberId, () => props.calendarId],
    async ([year, mId, cId]) => {
        console.log('[YearHeatmapView] Props changed:', { year, memberId: mId, calendarId: cId })
        await fetchYearSummary(year, cId, mId || undefined)
    },
    { immediate: true }
)

const handleDateClick = (date: Date) => {
    emit('dateClick', date)
}
</script>

<template>
    <div class="year-heatmap-view">
        <!-- 読み込み中 -->
        <div v-if="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>年間データを読み込み中...</p>
        </div>
        
        <!-- エラー状態 -->
        <div v-else-if="!heatmapReady" class="error-state">
            <p>データを取得できませんでした</p>
        </div>
        
        <!-- グリッド表示（heatmapReadyの時のみ） -->
        <div v-else class="year-grid">
            <div v-for="month in months" :key="month" class="month-container">
                <div class="month-header">{{ month }}月</div>
                <div class="weekday-header">
                    <div class="weekday">日</div>
                    <div class="weekday">月</div>
                    <div class="weekday">火</div>
                    <div class="weekday">水</div>
                    <div class="weekday">木</div>
                    <div class="weekday">金</div>
                    <div class="weekday">土</div>
                </div>
                <div class="days-grid">
                    <div
                        v-for="day in getDaysInMonth(month)"
                        :key="day.dateStr"
                        class="day-cell"
                        :class="{ 'inactive-day': !day.isCurrentMonth }"
                        :style="{ 
                            backgroundColor: day.isCurrentMonth ? (getDayLevel(day.dateStr)?.color || '#f9fafb') : '#fafafa'
                        }"
                        @click="day.isCurrentMonth && handleDateClick(day.date)"
                    >
                        <div class="day-number" :class="{ 'inactive-number': !day.isCurrentMonth, 'white-text': day.isCurrentMonth && getDayLevel(day.dateStr)?.level === 4 }">{{ day.day }}</div>
                        <div v-if="day.isCurrentMonth && getDaySummary(day.dateStr)" class="day-info" :class="{ 'white-text': getDayLevel(day.dateStr)?.level >= 3 }">
                            <div v-if="getDaySummary(day.dateStr)!.totalHours > 0" class="hours ">{{ Math.round(getDaySummary(day.dateStr)!.totalHours) }}h</div>
                            <div v-if="getDaySummary(day.dateStr)!.eventCount > 0 || getDaySummary(day.dateStr)!.importantCount > 0" class="dots">
                                <div v-if="getDaySummary(day.dateStr)!.alldayCount > 0" class="allday-dot"></div>
                                <div v-if="getDaySummary(day.dateStr)!.importantCount > 0" class="important-dot"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.year-heatmap-view {
    padding: 1.5rem;
    height: 100%;
    overflow-y: auto;
    background: #fafafa;
}

.loading-state,
.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    color: #6b7280;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.year-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 350px), 1fr));
    gap: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

.month-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.month-header {
    padding: 1rem;
    background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
    font-weight: 700;
    font-size: 1rem;
    text-align: center;
    border-bottom: 2px solid #e5e7eb;
    color: #374151;
}

.weekday-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f3f4f6;
    border-bottom: 1px solid #e5e7eb;
}

.weekday {
    padding: 0.5rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-template-rows: repeat(6, 1fr);
    gap: 2px;
    padding: 0.5rem;
    background: #f9fafb;
    min-height: 240px;
}

.day-cell {
    padding: 0.25rem;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    border-radius: 0.25rem;
    overflow: hidden;
}

.day-cell.inactive-day {
    cursor: default;
    opacity: 0.4;
}

.day-cell:not(.inactive-day):hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10;
}

.day-number {
    font-size: 0.625rem;
    font-weight: 500;
    color: #1f2937;
    align-self: flex-start;
    line-height: 1;
}

.day-number.inactive-number {
    color: #9ca3af;
    font-weight: 400;
}

.day-number.white-text,
.day-info.white-text .hours {
    color: #ffffff;
}

.day-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    flex: 1;
}

.dots {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.hours {
    font-size: 1.125rem;
    font-weight: 700;
    color: #374151;
    line-height: 1;
}

.allday-dot {
    width: 6px;
    height: 6px;
    background: #eab308;
    border-radius: 50%;
    box-shadow: 0 0 4px rgba(234, 179, 8, 0.8);
    flex-shrink: 0;
}

.important-dot {
    width: 6px;
    height: 6px;
    background: #dc2626;
    border-radius: 50%;
    box-shadow: 0 0 4px rgba(220, 38, 38, 0.8);
    flex-shrink: 0;
}

.error-state p {
    font-size: 1rem;
    font-weight: 500;
}
</style>
