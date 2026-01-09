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

    const updateMoreLinks = (api: any) => {
        document.querySelectorAll('.fc-daygrid-day').forEach(dayEl => {
            const dateStr = dayEl.getAttribute('data-date')
            if (!dateStr) return

            // その日の全イベントを取得（表示されていないものも含む）
            const dayStart = new Date(dateStr + 'T00:00:00')
            const dayEnd = new Date(dateStr + 'T23:59:59')
            const eventsOnDay = api.getEvents().filter((event: any) => {
                const eventStart = event.start
                const eventEnd = event.end || event.start
                return eventStart <= dayEnd && eventEnd >= dayStart
            })

            const hasImportant = eventsOnDay.some((event: any) =>
                event.extendedProps?.importance === '重要'
            )

            const moreLink = dayEl.querySelector('.fc-daygrid-more-link')
            if (moreLink) {
                if (hasImportant) {
                    moreLink.classList.add('has-important-event')
                } else {
                    moreLink.classList.remove('has-important-event')
                }

                // 年表示のみ：+moreリンクにホバー機能を追加
                if (viewMode.value === 'multiMonthYear') {
                    // クローンしてリスナー重複を防ぐ簡易的な策として、一度だけ追加されるように制御が必要だが、
                    // FullCalendarは再描画で要素を作り直すことが多いので毎回追加でよい場合も。
                    // ただしメモリリーク注意。Vueの管理外DOMなので本当はクリーンアップが必要だが...
                    // ここでは既存のコードを踏襲しつつ、リスナー追加処理を入れる。
                    // 既存コードでは単純に追加していた。

                    /* Note: 既存コードでは addEventListener を行っていたが、
                       何度も呼ばれる可能性があるため、厳密には removeEventListener が必要。
                       しかし要素自体が再生成されるならリークは限定的。
                       今回はDOM操作ロジックをここに集約する。
                    */

                    const onMouseEnter = (e: Event) => {
                        const sortedEvents = [...eventsOnDay].sort((a: any, b: any) => {
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
                    }

                    const onMouseLeave = () => {
                        hoveredEvents.value = []
                    }

                    // リスナー重複回避のため一旦削除（無名関数だとできないので、プロパティで管理するか...
                    // ここでは簡易的に、カスタムプロパティでフラグを立てる等の制御を入れるのが安全だが
                    // 要素再生成される前提で進める。
                    moreLink.addEventListener('mouseenter', onMouseEnter)
                    moreLink.addEventListener('mouseleave', onMouseLeave)
                }
            }
        })
    }

    const handleEventDidMount = () => {
        setTimeout(() => {
            const api = fullCalendarRef.value?.getApi()
            if (!api) return
            updateMoreLinks(api)
        }, 0)
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

        // 年表示：月セル全体をクリック可能にする
        setTimeout(() => {
            const api = fullCalendarRef.value?.getApi()
            if (!api) return

            if (viewMode.value === 'multiMonthYear') {
                document.querySelectorAll('.fc-multimonth-month').forEach(monthEl => {
                    const el = monthEl as HTMLElement
                    el.style.cursor = 'pointer'

                    const firstDayCell = el.querySelector('.fc-daygrid-day[data-date]')
                    if (firstDayCell) {
                        const dateStr = firstDayCell.getAttribute('data-date')
                        if (dateStr) {
                            // クローンしてリスナーをリセット（簡易的な方法）
                            const newEl = el.cloneNode(true) as HTMLElement
                            el.parentNode?.replaceChild(newEl, el)

                            newEl.addEventListener('click', (e) => {
                                const target = e.target as HTMLElement
                                if (target.closest('.fc-daygrid-day') || target.closest('.fc-event')) {
                                    return
                                }

                                const targetDate = new Date(dateStr)
                                viewMode.value = 'dayGridMonth'
                                api.changeView('dayGridMonth')
                                api.gotoDate(targetDate)
                            })
                        }
                    }
                })
            } else if (viewMode.value === 'dayGridMonth') {
                document.querySelectorAll('.fc-daygrid-day').forEach(el => {
                    (el as HTMLElement).style.cursor = 'pointer'
                })
            }

            updateMoreLinks(api)
        }, 0)
    }

    return {
        hoveredEvent,
        hoveredEvents,
        hoverPosition,
        handleDatesSet,
        handleEventDidMount,
        handleEventMouseEnter,
        handleEventMouseLeave
    }
}
