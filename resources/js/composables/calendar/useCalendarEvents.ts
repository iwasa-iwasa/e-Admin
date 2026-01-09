import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CATEGORY_COLORS, CATEGORY_LABELS, GENRE_FILTERS } from '@/constants/calendar'

export function useCalendarEvents(props: { events: App.Models.Event[] }) {
    const searchQuery = ref('')
    const genreFilter = ref('all')

    const filteredEvents = computed(() => {
        let filtered = props.events || []

        if (genreFilter.value !== GENRE_FILTERS.ALL) {
            if (genreFilter.value === GENRE_FILTERS.OTHER) {
                const knownCategories = Object.keys(CATEGORY_COLORS)
                filtered = filtered.filter(event => !knownCategories.includes(event.category))
            } else {
                // COLOR to CATEGORY mapping for filter
                const colorToCategory: Record<string, string> = {
                    [GENRE_FILTERS.BLUE]: CATEGORY_LABELS['会議'],
                    [GENRE_FILTERS.GREEN]: CATEGORY_LABELS['業務'],
                    [GENRE_FILTERS.YELLOW]: CATEGORY_LABELS['来客'],
                    [GENRE_FILTERS.PURPLE]: CATEGORY_LABELS['出張'],
                    [GENRE_FILTERS.PINK]: CATEGORY_LABELS['休暇'],
                }
                filtered = filtered.filter(event => event.category === colorToCategory[genreFilter.value])
            }
        }

        if (!searchQuery.value.trim()) return filtered

        const query = searchQuery.value.toLowerCase()
        return filtered.filter(event => {
            const title = event.title?.toLowerCase() || ''
            const description = event.description?.toLowerCase() || ''
            const creatorName = event.creator?.name?.toLowerCase() || ''
            const participantNames = event.participants?.map((p: any) => p.name?.toLowerCase()).join(' ') || ''

            return title.includes(query) ||
                description.includes(query) ||
                creatorName.includes(query) ||
                participantNames.includes(query)
        })
    })

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
        filteredEvents,
        canEditEvent
    }
}
