import { ref, computed, type Ref, onMounted, onUnmounted } from 'vue'

export function useCalendarLayout(
    headerRef: Ref<HTMLElement | null>,
    calendarTitle: Ref<string>,
    viewMode: Ref<string>,
    currentDayViewDate: Ref<Date>,
    currentWeekStart: Ref<Date>
) {
    const layoutMode = ref<'default' | 'filter-small' | 'search-icon' | 'title-hide' | 'compact' | 'minimal' | 'ultra-minimal'>('default')

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

    let resizeObserver: ResizeObserver | null = null

    onMounted(() => {
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
        if (resizeObserver) {
            resizeObserver.disconnect()
        }
    })

    return {
        layoutMode,
        compactCalendarTitle
    }
}
