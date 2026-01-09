import axios from 'axios'
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { GENRE_FILTERS, getEventColor } from '@/constants/calendar'

export function useCalendarEvents() {
    const searchQuery = ref('')
    const genreFilter = ref(GENRE_FILTERS.ALL)

    const canEditEvent = (event: App.Models.Event) => {
        const page = usePage()
        const currentUserId = (page.props as any).auth?.user?.id ?? null
        const teamMembers = (page.props as any).teamMembers || []

        const isCreator = event.created_by === currentUserId
        if (isCreator) return true

        if (Array.isArray(teamMembers) && teamMembers.length > 0 && event.participants && event.participants.length === teamMembers.length) {
            return true
        }

        const isParticipant = event.participants?.some(p => p.id === currentUserId)
        return isParticipant || false
    }

    const fetchEvents = async (start: string, end: string, memberId?: number | null) => {
        try {
            const response = await axios.get('/api/events', {
                params: {
                    start,
                    end,
                    search_query: searchQuery.value,
                    genre_filter: genreFilter.value,
                    member_id: memberId
                }
            })

            const events = response.data
            // Apply mapping similar to useFullCalendarConfig but returning raw objects mostly
            // actually useFullCalendarConfig maps them for FullCalendar. 
            // Here we return raw events but we might need to map them if we want consistency?
            // SharedCalendar uses them for DayViewGantt/WeekSummaryView which expect App.Models.Event
            // The API returns JSON structure of App.Models.Event so it should be fine.
            // However, useFullCalendarConfig does some extra mapping (e.g. extending duration for allDay).
            // Let's return the raw response data as it matches App.Models.Event structure.

            // Note: The components might expect some fields like 'start'/'end' as strings or Dates?
            // DayViewGantt uses event.start_date, end_date (string YYYY-MM-DD) or start_time etc.
            // The API returns these fields.

            return events
        } catch (error) {
            console.error('Error fetching events:', error)
            return []
        }
    }

    return {
        searchQuery,
        genreFilter,
        canEditEvent,
        fetchEvents
    }
}
