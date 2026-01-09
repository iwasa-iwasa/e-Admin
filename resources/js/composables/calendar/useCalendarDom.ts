import { ref, Ref } from 'vue'

export function useCalendarDom(
    fullCalendarRef: Ref<any>,
    viewMode: Ref<string>,
    isTodayInViewForFullCalendar: Ref<boolean>,
    calendarTitle: Ref<string>
) {
    const hoveredEvent = ref<any>(null)
    const hoveredEvents = ref<any[]>([])
    const hoverPosition = ref({ x: 0, y: 0 })

    const handleEventMouseEnter = (info: any) => {
        hoveredEvent.value = info.event.extendedProps
        const rect = info.el.getBoundingClientRect()
        hoverPosition.value = { x: rect.left + rect.width / 2, y: rect.top - 10 }
    }

    const handleEventMouseLeave = () => {
        hoveredEvent.value = null
    }

    const getMoreLinkClassNames = (arg: any) => {
        const hasImportant = arg.hiddenSegs.some((seg: any) =>
            seg.event.extendedProps?.importance === '重要'
        )
        return hasImportant ? ['has-important-event'] : []
    }

    const handleMoreLinkDidMount = (arg: any) => {
        if (viewMode.value === 'multiMonthYear') {
            arg.el.addEventListener('mouseenter', (e: MouseEvent) => {
                const hiddenEvents = arg.hiddenSegs.map((seg: any) => seg.event)
                const sortedEvents = [...hiddenEvents].sort((a: any, b: any) => {
                    const aStart = new Date(a.start).toDateString()
                    const aEnd = new Date(a.end || a.start).toDateString()
                    const bStart = new Date(b.start).toDateString()
                    const bEnd = new Date(b.end || b.start).toDateString()

                    const aIsMultiDay = aStart !== aEnd
                    const bIsMultiDay = bStart !== bEnd
                    const aIsImportant = a.extendedProps?.importance === '重要'
                    const bIsImportant = b.extendedProps?.importance === '重要'

                    const aScore1 = aIsMultiDay && aIsImportant ? 1 : 0
                    const bScore1 = bIsMultiDay && bIsImportant ? 1 : 0
                    if (aScore1 !== bScore1) return bScore1 - aScore1

                    if (aIsMultiDay !== bIsMultiDay) return bIsMultiDay ? 1 : -1
                    if (aIsImportant !== bIsImportant) return bIsImportant ? 1 : -1

                    return 0
                })

                hoveredEvents.value = sortedEvents.slice(0, 2).map((e: any) => e.extendedProps)
                const rect = (e.target as HTMLElement).getBoundingClientRect()
                hoverPosition.value = { x: rect.left + rect.width / 2, y: rect.top - 10 }
            })

            arg.el.addEventListener('mouseleave', () => {
                hoveredEvents.value = []
            })
        }
    }

    const handleDayCellDidMount = (arg: any) => {
        arg.el.style.cursor = 'pointer'
    }

    const handleDatesSet = (info: any) => {
        // FullCalendar使用時（月・年表示）のみタイトルを更新
        if (info.view.type === 'dayGridMonth' || info.view.type === 'multiMonthYear') {
            calendarTitle.value = info.view.title
        }

        // 今日がビュー内にあるか判定
        const todayMidnight = new Date()
        todayMidnight.setHours(0, 0, 0, 0)
        isTodayInViewForFullCalendar.value = info.view.currentStart <= todayMidnight && todayMidnight < info.view.currentEnd

        // 年表示：月セル全体をクリック可能にする（軽量化）
        if (viewMode.value === 'multiMonthYear') {
            setTimeout(() => {
                const api = fullCalendarRef.value?.getApi()
                if (!api) return

                document.querySelectorAll('.fc-multimonth-month').forEach(monthEl => {
                    const el = monthEl as HTMLElement
                    // 既にリスナーがついているか確認する術がないので、再描画ごとに設定
                    // removeEventListenerは困難だが、DOM再構築されるので許容
                    if (el.dataset.clickAttached) return

                    el.style.cursor = 'pointer'
                    el.dataset.clickAttached = 'true'

                    el.addEventListener('click', (e) => {
                        const target = e.target as HTMLElement
                        // 日付セルやイベント自体のクリックは無視（バブリングでここまで来た場合）
                        if (target.closest('.fc-daygrid-day-top') || target.closest('.fc-event') || target.closest('.fc-daygrid-more-link')) {
                            return
                        }

                        // 月の最初の日付を取得してその月へ遷移
                        const firstDayCell = el.querySelector('.fc-daygrid-day[data-date]')
                        if (firstDayCell) {
                            const dateStr = firstDayCell.getAttribute('data-date')
                            if (dateStr) {
                                const targetDate = new Date(dateStr)
                                viewMode.value = 'dayGridMonth'
                                api.changeView('dayGridMonth')
                                api.gotoDate(targetDate)
                            }
                        }
                    })
                })
            }, 0)
        }
    }

    return {
        hoveredEvent,
        hoveredEvents,
        hoverPosition,
        handleDatesSet,
        handleEventMouseEnter,
        handleEventMouseLeave,
        getMoreLinkClassNames,
        handleMoreLinkDidMount,
        handleDayCellDidMount
    }
}
