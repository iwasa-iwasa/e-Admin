import { ref } from 'vue'
import axios from 'axios'

export interface DaySummary {
    busyScore: number
    totalHours: number
    eventCount: number
    alldayCount: number
    importantCount: number
}

export interface YearSummary {
    year: number
    calendar_id: number
    days: Record<string, DaySummary>
}

export interface HeatmapLevel {
    level: number
    color: string
    minScore: number
    maxScore: number
}

export function useYearHeatmap() {
    const yearSummary = ref<YearSummary | null>(null)
    const loading = ref(false)
    const heatmapLevels = ref<HeatmapLevel[]>([])
    const heatmapReady = ref(false)

    const fetchYearSummary = async (year: number, calendarId: number, memberId?: number) => {
        console.log('[YearHeatmap] Fetching year summary:', { year, calendarId, memberId })

        // 明示的にリセット（エラー時に前回のデータが残る事故を防ぐ）
        heatmapReady.value = false
        yearSummary.value = null
        heatmapLevels.value = []
        loading.value = true

        try {
            const response = await axios.get('/api/calendar/year-summary', {
                params: { year, calendar_id: calendarId, member_id: memberId }
            })

            yearSummary.value = response.data
            const daysCount = Object.keys(response.data.days || {}).length
            console.log('[YearHeatmap] Summary received:', { days: daysCount })

            calculateHeatmapLevels()
            heatmapReady.value = true
            console.log('[YearHeatmap] Heatmap ready')

            return true
        } catch (error) {
            console.error('[YearHeatmap] Failed to fetch year summary:', error)
            return false
        } finally {
            loading.value = false
        }
    }

    const calculateHeatmapLevels = () => {
        if (!yearSummary.value) {
            heatmapLevels.value = []
            return
        }

        const scores = Object.values(yearSummary.value.days).map(d => d.busyScore)
        if (scores.length === 0) {
            heatmapLevels.value = []
            return
        }

        const minScore = Math.min(...scores)
        const maxScore = Math.max(...scores)
        const range = maxScore - minScore

        console.log('[YearHeatmap] Calculated min/max:', { min: minScore, max: maxScore })

        const colors = [
            'var(--heatmap-level-0)', // 0: 予定なし/軽い
            'var(--heatmap-level-1)', // 1: 軽い
            'var(--heatmap-level-2)', // 2: 普通
            'var(--heatmap-level-3)', // 3: 忙しい
            'var(--heatmap-level-4)'  // 4: 非常に忙しい
        ]

        heatmapLevels.value = colors.map((color, index) => ({
            level: index,
            color,
            minScore: minScore + (range * index / 4),
            maxScore: minScore + (range * (index + 1) / 4)
        }))
    }

    const getDayLevel = (date: string): HeatmapLevel | null => {
        if (!heatmapReady.value) return null

        const summary = yearSummary.value?.days[date]
        if (!summary || heatmapLevels.value.length === 0) return null

        const score = summary.busyScore
        if (score === 0) return heatmapLevels.value[0]

        for (let i = heatmapLevels.value.length - 1; i >= 0; i--) {
            const level = heatmapLevels.value[i]
            if (score >= level.minScore) {
                return level
            }
        }
        return heatmapLevels.value[0]
    }

    const getDaySummary = (date: string): DaySummary | null => {
        if (!heatmapReady.value) return null
        return yearSummary.value?.days[date] || null
    }

    return {
        yearSummary,
        loading,
        heatmapLevels,
        heatmapReady,
        fetchYearSummary,
        getDayLevel,
        getDaySummary
    }
}