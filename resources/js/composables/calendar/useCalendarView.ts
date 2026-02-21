import { ref, computed, nextTick, watch } from 'vue'

export function useCalendarView(fullCalendarRef: any, initialView?: string) {
    const viewMode = ref(initialView || 'dayGridMonth')
    const currentDayViewDate = ref(new Date())
    const currentWeekStart = ref(new Date())
    const currentYearViewYear = ref(new Date().getFullYear())
    const calendarTitle = ref('')
    const isTodayInViewForFullCalendar = ref(false)

    // Helper functions
    const getWeekStart = (date: Date) => {
        const d = new Date(date)
        const day = d.getDay()
        // 日曜開始に統一
        const diff = d.getDate() - day
        return new Date(d.setDate(diff))
    }

    const isDateInWeek = (date: Date, weekStart: Date) => {
        const weekEnd = new Date(weekStart)
        weekEnd.setDate(weekEnd.getDate() + 6)
        return date >= weekStart && date <= weekEnd
    }

    // Computed properties
    const isTodayInCurrentView = computed(() => {
        const today = new Date()
        today.setHours(0, 0, 0, 0)

        if (viewMode.value === 'timeGridDay') {
            const current = new Date(currentDayViewDate.value)
            current.setHours(0, 0, 0, 0)
            return current.getTime() === today.getTime()
        }

        if (viewMode.value === 'timeGridWeek') {
            const weekEnd = new Date(currentWeekStart.value)
            weekEnd.setDate(weekEnd.getDate() + 6)
            weekEnd.setHours(23, 59, 59, 999)
            return today >= currentWeekStart.value && today <= weekEnd
        }

        return isTodayInViewForFullCalendar.value
    })

    const canGoBack = computed(() => {
        return viewMode.value !== 'yearView'
    })

    const todayButtonText = computed(() => {
        const currentView = viewMode.value
        const isTodayInView = isTodayInCurrentView.value
        const today = new Date()
        const currentYear = today.getFullYear()

        if (currentView === 'yearView') {
            return currentYearViewYear.value === currentYear ? '今月' : '今年'
        }
        if (currentView === 'dayGridMonth') return isTodayInView ? '今週' : '今月'
        if (currentView === 'timeGridWeek') return isTodayInView ? '今日' : '今週'

        return '今日'
    })

    // Actions
    const updateCalendarTitle = () => {
        if (viewMode.value === 'yearView') {
            calendarTitle.value = `${currentYearViewYear.value}年`
        } else if (viewMode.value === 'timeGridDay') {
            calendarTitle.value = currentDayViewDate.value.toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
        } else if (viewMode.value === 'timeGridWeek') {
            const start = new Date(currentWeekStart.value)
            const end = new Date(start)
            end.setDate(end.getDate() + 6)
            calendarTitle.value = `${start.getFullYear()}年${start.getMonth() + 1}月${start.getDate()}日 〜 ${end.getMonth() + 1}月${end.getDate()}日`
        }
    }

    const changeView = (view: string | number) => {
        const viewStr = String(view)
        viewMode.value = viewStr

        if (viewStr === 'yearView') {
            currentYearViewYear.value = new Date().getFullYear()
            updateCalendarTitle()
        } else if (viewStr === 'timeGridWeek') {
            currentWeekStart.value = getWeekStart(new Date())
            updateCalendarTitle()
        } else if (viewStr === 'timeGridDay') {
            currentDayViewDate.value = new Date()
            updateCalendarTitle()
        } else {
            nextTick(() => {
                const api = fullCalendarRef.value?.getApi()
                if (api) {
                    api.changeView(viewStr)
                }
            })
        }
    }

    const previousPeriod = () => {
        if (viewMode.value === 'yearView') {
            currentYearViewYear.value -= 1
        } else if (viewMode.value === 'timeGridDay') {
            const newDate = new Date(currentDayViewDate.value)
            newDate.setDate(newDate.getDate() - 1)
            currentDayViewDate.value = newDate
        } else if (viewMode.value === 'timeGridWeek') {
            const newDate = new Date(currentWeekStart.value)
            newDate.setDate(newDate.getDate() - 7)
            currentWeekStart.value = newDate
        } else {
            const api = fullCalendarRef.value?.getApi()
            if (api) {
                api.prev()
            }
        }
    }

    const nextPeriod = () => {
        if (viewMode.value === 'yearView') {
            currentYearViewYear.value += 1
        } else if (viewMode.value === 'timeGridDay') {
            const newDate = new Date(currentDayViewDate.value)
            newDate.setDate(newDate.getDate() + 1)
            currentDayViewDate.value = newDate
        } else if (viewMode.value === 'timeGridWeek') {
            const newDate = new Date(currentWeekStart.value)
            newDate.setDate(newDate.getDate() + 7)
            currentWeekStart.value = newDate
        } else {
            const api = fullCalendarRef.value?.getApi()
            if (api) {
                api.next()
            }
        }
    }

    const goBackOneLevel = () => {
        if (viewMode.value === 'timeGridDay') {
            currentWeekStart.value = getWeekStart(currentDayViewDate.value)
            viewMode.value = 'timeGridWeek'
        } else if (viewMode.value === 'timeGridWeek') {
            viewMode.value = 'dayGridMonth'
            nextTick(() => {
                const api = fullCalendarRef.value?.getApi()
                if (api) {
                    api.changeView('dayGridMonth')
                    api.gotoDate(currentWeekStart.value)
                }
            })
        } else if (viewMode.value === 'dayGridMonth') {
            viewMode.value = 'yearView'
        }
    }

    const handleTodayClick = () => {
        const today = new Date()
        today.setHours(0, 0, 0, 0)

        if (viewMode.value === 'yearView') {
            const currentYear = today.getFullYear()
            if (currentYearViewYear.value === currentYear) {
                // 今年表示中 → 今月へ遷移
                viewMode.value = 'dayGridMonth'
                nextTick(() => {
                    const api = fullCalendarRef.value?.getApi()
                    if (api) {
                        api.changeView('dayGridMonth')
                        api.gotoDate(today)
                    }
                })
            } else {
                // 別の年表示中 → 今年へ移動
                currentYearViewYear.value = currentYear
            }
        } else if (viewMode.value === 'timeGridWeek') {
            if (isDateInWeek(today, currentWeekStart.value)) {
                currentDayViewDate.value = today
                viewMode.value = 'timeGridDay'
            } else {
                currentWeekStart.value = getWeekStart(today)
            }
        } else if (viewMode.value === 'timeGridDay') {
            currentDayViewDate.value = today
        } else {
            const api = fullCalendarRef.value?.getApi()
            if (!api) return

            if (viewMode.value === 'dayGridMonth') {
                if (isTodayInCurrentView.value) {
                    currentWeekStart.value = getWeekStart(today)
                    viewMode.value = 'timeGridWeek'
                } else {
                    api.gotoDate(today)
                }
            }
        }
    }

    const handleDateClickFromWeek = (date: Date) => {
        currentDayViewDate.value = new Date(date)
        viewMode.value = 'timeGridDay'
    }

    const handleDateClick = (info: any) => {
        const clickedDate = new Date(info.dateStr || info.date)

        if (viewMode.value === 'dayGridMonth') {
            currentWeekStart.value = getWeekStart(clickedDate)
            viewMode.value = 'timeGridWeek'
        }
    }

    // Watchers
    watch([viewMode, currentDayViewDate, currentWeekStart, currentYearViewYear], () => {
        updateCalendarTitle()
    }, { immediate: true })

    return {
        viewMode,
        currentDayViewDate,
        currentWeekStart,
        currentYearViewYear,
        calendarTitle,
        isTodayInViewForFullCalendar,
        isTodayInCurrentView,
        canGoBack,
        todayButtonText,
        getWeekStart,
        changeView,
        previousPeriod,
        nextPeriod,
        goBackOneLevel,
        handleTodayClick,
        handleDateClickFromWeek,
        handleDateClick,
        updateCalendarTitle
    }
}
