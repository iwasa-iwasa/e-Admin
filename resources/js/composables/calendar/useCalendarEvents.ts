import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { GENRE_FILTERS } from '@/constants/calendar'

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

    return {
        searchQuery,
        genreFilter,
        canEditEvent
    }
}
