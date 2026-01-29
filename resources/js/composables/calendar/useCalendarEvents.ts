import axios from 'axios'
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { GENRE_FILTERS, getEventColor } from '@/constants/calendar'

export function useCalendarEvents() {
    const searchQuery = ref('')
    const genreFilter = ref<string>(GENRE_FILTERS.ALL)

    const canEditEvent = (event: App.Models.ExpandedEvent | App.Models.Event) => {
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

            return response.data as App.Models.ExpandedEvent[]
        } catch (error) {
            console.error('Error fetching events:', error)
            // ユーザーにエラーを通知
            if (error instanceof Error) {
                throw new Error(`イベントの取得に失敗しました: ${error.message}`)
            }
            throw new Error('イベントの取得に失敗しました')
        }
    }

    return {
        searchQuery,
        genreFilter,
        canEditEvent,
        fetchEvents
    }
}
