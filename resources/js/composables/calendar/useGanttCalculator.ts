import { ref, computed, type Ref, watch } from 'vue'

export function useGanttCalculator(
    props: {
        timeScope: 'all' | 'current' | 'before' | 'middle' | 'after',
        currentDate: Date,
        events: App.Models.Event[]
    },
    teamMembers: Ref<any[]>,
    ganttContainerRef: Ref<HTMLElement | null>
) {
    const DAY_START_HOUR = 7
    const DAY_END_HOUR = 19
    const DAY_START_MIN = DAY_START_HOUR * 60
    const DAY_END_MIN = DAY_END_HOUR * 60
    const workStartHour = 8
    const workEndHour = 17

    const viewportWidth = ref(760)

    const updateViewportWidth = () => {
        if (props.timeScope !== 'all' && ganttContainerRef.value) {
            viewportWidth.value = ganttContainerRef.value.clientWidth
        }
    }

    // タブの定義
    const scopeRanges = computed(() => {
        const now = new Date()

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

    const MEMBER_COLUMN_WIDTH = 150
    const CURRENT_SCALE = 0.934

    const pxPerMin = computed(() => {
        const { start, end } = scopeRanges.value
        const duration = end - start

        // current は「見やすさ優先」
        if (props.timeScope === 'current') {
            return ((viewportWidth.value - MEMBER_COLUMN_WIDTH) / duration) * CURRENT_SCALE
        }
        if (props.timeScope !== 'all') {
            return ((viewportWidth.value - MEMBER_COLUMN_WIDTH) / duration) * CURRENT_SCALE
        }

        // all は従来どおり（疎）
        return 190 / 60
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

    // ユーティリティ関数
    const parseTime = (timeStr: string | null, fallback = 0): number => {
        if (!timeStr) return fallback
        const [hours, minutes] = timeStr.split(':').map(Number)
        return hours * 60 + minutes
    }

    // 予定データ処理
    const todayEvents = computed(() => {
        const dateStr = props.currentDate.toISOString().split('T')[0]
        return props.events.filter(event => {
            const eventStart = event.start_date.split('T')[0]
            const eventEnd = event.end_date.split('T')[0]
            return eventStart <= dateStr && dateStr <= eventEnd
        })
    })

    const memberEvents = computed(() => {
        const scope = scopeRanges.value

        return teamMembers.value.map(member => {
            const events = todayEvents.value.filter(event =>
                event.participants?.some((p: any) => p.id === member.id)
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

    const currentTimePosition = computed(() => {
        const now = new Date()
        const min = now.getHours() * 60 + now.getMinutes()
        const { start, end } = scopeRanges.value

        if (min < start || min > end) return null

        return (min - start) * pxPerMin.value
    })

    const summaryByMember = computed(() => {
        return teamMembers.value.map(member => {
            const events = todayEvents.value.filter(event =>
                event.participants?.some((p: any) => p.id === member.id)
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

    return {
        updateViewportWidth,
        scopeRanges,
        visibleHours,
        timeGridWidth,
        hourWidth,
        memberEvents,
        getEventStyle,
        currentTimePosition,
        summaryByMember,
        totalSummary,
        workStartHour,
        workEndHour
    }
}
