import { computed, Ref } from 'vue'
import { CalendarOptions } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
// RRULE削除済み
import { getEventColor } from '@/constants/calendar'

export function useFullCalendarConfig(
    getUnifiedEvents: () => any[],
    memberId: Ref<number | null | undefined>,
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
    }
) {
    const calendarOptions = computed((): CalendarOptions => ({
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, multiMonthPlugin], // RRULEプラグイン削除済み
        initialView: viewMode.value,
        headerToolbar: false,
        contentHeight: 'auto',
        multiMonthMaxColumns: 2,
        multiMonthMinWidth: 220,
        dayMaxEvents: true,
        eventOrder: (a: any, b: any) => {
            // 重要度のみ
            const aImportance = a.extendedProps?.importance || '低'
            const bImportance = b.extendedProps?.importance || '低'
            if (aImportance === '重要' && bImportance !== '重要') return -1
            if (aImportance !== '重要' && bImportance === '重要') return 1
            return 0
        },
        events: (info, successCallback, failureCallback) => {
            try {
                const allEvents = getUnifiedEvents()
                
                // 期間フィルタリング
                const startDate = new Date(info.startStr)
                const endDate = new Date(info.endStr)
                
                const filteredEvents = allEvents.filter(event => {
                    const eventStart = new Date(event.start_date || event.start)
                    const eventEnd = new Date(event.end_date || event.end)
                    return eventStart < endDate && eventEnd >= startDate
                })

                const mappedEvents = filteredEvents.map((event: any) => {
                    const commonProps = {
                        id: String(event.id),
                        title: event.title,
                        backgroundColor: categoryColorGetter(event.category),
                        borderColor: event.importance === '重要' ? '#dc2626' : categoryColorGetter(event.category),
                        extendedProps: event,
                        allDay: event.isAllDay || event.is_all_day,
                        classNames: (event.importance === '重要' || event.isImportant) ? ['important-event'] : [],
                    };

                    if (event.isAllDay || event.is_all_day) {
                        const endDateStr = event.end || event.end_date
                        const [year, month, day] = endDateStr.split('T')[0].split('-').map(Number);
                        const endDate = new Date(Date.UTC(year, month - 1, day));
                        endDate.setUTCDate(endDate.getUTCDate() + 1);
                        const endStr = endDate.toISOString().split('T')[0];

                        return {
                            ...commonProps,
                            start: (event.start || event.start_date).split('T')[0],
                            end: endStr,
                        };
                    }

                    const startDateStr = (event.start || event.start_date).split('T')[0];
                    const endDateStr = (event.end || event.end_date).split('T')[0];
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

                successCallback(mappedEvents)
            } catch (error) {
                console.error('Error processing unified events:', error)
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
