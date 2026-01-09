import axios from 'axios'
import { computed, Ref } from 'vue'
import { CalendarOptions } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'
import { getEventColor } from '@/constants/calendar'

export function useFullCalendarConfig(
    filters: {
        searchQuery: Ref<string>,
        genreFilter: Ref<string>,
        memberId: Ref<number | null | undefined>
    },
    viewMode: Ref<string>,
    fullCalendarRef: Ref<any>,
    categoryColorGetter: typeof getEventColor,
    handlers: {
        eventClick: (info: any) => void
        dateClick: (info: any) => void
        eventMouseEnter: (info: any) => void
        eventMouseLeave: (info: any) => void
        datesSet: (info: any) => void
        moreLinkClassNames: (arg: any) => string[]
        moreLinkDidMount: (arg: any) => void
        dayCellDidMount: (arg: any) => void
    },
    onEventsFetched?: (events: any[]) => void
) {
    const calendarOptions = computed((): CalendarOptions => ({
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin, rrulePlugin],
        initialView: viewMode.value,
        headerToolbar: false,
        contentHeight: 'auto',
        multiMonthMaxColumns: 2,
        multiMonthMinWidth: 220,
        dayMaxEvents: viewMode.value === 'multiMonthYear' ? 2 : true,
        eventOrder: (a: any, b: any) => {
            // 年表示での優先度制御
            if (viewMode.value === 'multiMonthYear') {
                const aStart = new Date(a.start).toDateString()
                const aEnd = new Date(a.end || a.start).toDateString()
                const bStart = new Date(b.start).toDateString()
                const bEnd = new Date(b.end || b.start).toDateString()

                const aIsMultiDay = aStart !== aEnd
                const bIsMultiDay = bStart !== bEnd
                const aIsImportant = a.extendedProps?.importance === '重要'
                const bIsImportant = b.extendedProps?.importance === '重要'

                // 優先度①：複数日かつ重要
                const aScore1 = aIsMultiDay && aIsImportant ? 1 : 0
                const bScore1 = bIsMultiDay && bIsImportant ? 1 : 0
                if (aScore1 !== bScore1) return aScore1 - bScore1

                // 優先度②：複数日
                if (aIsMultiDay !== bIsMultiDay) return aIsMultiDay ? -1 : 1

                // 優先度③：単日だが重要
                if (aIsImportant !== bIsImportant) return aIsImportant ? -1 : 1

                // 優先度④：その他（開始時刻順）
                return 0
            }

            // 他の表示モード：重要度のみ
            const aImportance = a.extendedProps?.importance || '低'
            const bImportance = b.extendedProps?.importance || '低'
            if (aImportance === '重要' && bImportance !== '重要') return -1
            if (aImportance !== '重要' && bImportance === '重要') return 1
            return 0
        },
        events: async (info, successCallback, failureCallback) => {
            try {
                const response = await axios.get('/api/events', {
                    params: {
                        start: info.startStr,
                        end: info.endStr,
                        search_query: filters.searchQuery.value,
                        genre_filter: filters.genreFilter.value,
                        member_id: filters.memberId.value
                    }
                })

                const events = response.data
                const mappedEvents = events.map((event: any) => {
                    const commonProps = {
                        id: String(event.event_id),
                        title: event.title,
                        backgroundColor: categoryColorGetter(event.category),
                        borderColor: event.importance === '重要' ? '#dc2626' : categoryColorGetter(event.category),
                        extendedProps: event,
                        allDay: event.is_all_day,
                        classNames: event.importance === '重要' ? ['important-event'] : [],
                    };

                    if (event.rrule) {
                        return {
                            ...commonProps,
                            rrule: event.rrule,
                            duration: event.duration,
                        };
                    }

                    if (event.is_all_day) {
                        // event.end_dateの翌日を計算
                        const [year, month, day] = event.end_date.split('T')[0].split('-').map(Number);
                        const endDate = new Date(Date.UTC(year, month - 1, day));
                        endDate.setUTCDate(endDate.getUTCDate() + 1);
                        const endStr = endDate.toISOString().split('T')[0];

                        return {
                            ...commonProps,
                            start: event.start_date.split('T')[0],
                            end: endStr,
                        };
                    }

                    const startDateStr = event.start_date.split('T')[0];
                    const endDateStr = event.end_date.split('T')[0];
                    const startTime = event.start_time || '00:00:00';
                    const endTime = event.end_time || '00:00:00';

                    const formatTime = (time: string) => {
                        const parts = time.split(':');
                        if (parts.length === 2) return time + ':00';
                        return time.substring(0, 8);
                    };

                    return {
                        ...commonProps,
                        start: `${startDateStr}T${formatTime(startTime)}`,
                        end: `${endDateStr}T${formatTime(endTime)}`,
                    };
                })

                if (onEventsFetched) {
                    // Extract the original events from extendedProps to update various view states
                    const originalEvents = mappedEvents.map((e: any) => e.extendedProps)
                    onEventsFetched(originalEvents)
                }

                successCallback(mappedEvents)
            } catch (error) {
                console.error('Error fetching events:', error)
                failureCallback(error as Error)
            }
        },
        locale: 'ja',
        buttonText: {
            today: '今日',
        },
        eventClick: handlers.eventClick,
        dateClick: handlers.dateClick,
        eventMouseEnter: handlers.eventMouseEnter,
        eventMouseLeave: handlers.eventMouseLeave,
        datesSet: handlers.datesSet,
        moreLinkClassNames: handlers.moreLinkClassNames,
        moreLinkDidMount: handlers.moreLinkDidMount,
        dayCellDidMount: handlers.dayCellDidMount,
    }))

    return {
        calendarOptions
    }
}
