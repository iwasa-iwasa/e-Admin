import { ref, computed, watch, unref, Ref, ComputedRef } from 'vue'
import { useCalendarEvents } from './useCalendarEvents'

export interface UnifiedEvent {
  id: number
  event_id: number
  start: string
  end: string
  start_date: string
  end_date: string
  start_time?: string
  end_time?: string
  title: string
  isAllDay: boolean
  is_all_day: boolean
  isImportant: boolean
  parentEventId?: number
  isException?: boolean
  category: string
  importance: string
  description?: string
  location?: string
  progress?: number
  creator?: any
  participants?: any[]
  attachments?: any[]
}

interface EventFilters {
  searchQuery?: string
  genreFilter?: string
  memberId?: number | null
}

// EventService レベルキャッシュ
const eventCache = new Map<string, { data: UnifiedEvent[], timestamp: number }>()
const CACHE_TTL = 5 * 60 * 1000 // 5分 TODO: 設定ファイルから取得

function getCacheKey(rangeStart: string, rangeEnd: string, filters: EventFilters): string {
  return `${rangeStart}-${rangeEnd}-${JSON.stringify(filters)}`
}

export function useUnifiedEvents(
  rangeStart: string | Ref<string> | ComputedRef<string>,
  rangeEnd: string | Ref<string> | ComputedRef<string>,
  filters: EventFilters | Ref<EventFilters> | ComputedRef<EventFilters> = {}
) {
  const events = ref<UnifiedEvent[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const initialized = ref(false)
  
  const { searchQuery, genreFilter, fetchEvents } = useCalendarEvents()

  const loadEvents = async (forceRefresh = false) => {
    loading.value = true
    error.value = null
    
    try {
      const start = unref(rangeStart)
      const end = unref(rangeEnd)
      const filterValues = unref(filters)
      
      // フィルター値をuseCalendarEventsに設定
      if (filterValues.searchQuery !== undefined) {
        searchQuery.value = filterValues.searchQuery
      }
      if (filterValues.genreFilter !== undefined) {
        genreFilter.value = filterValues.genreFilter
      }
      
      const cacheKey = getCacheKey(start, end, filterValues)
      const cached = eventCache.get(cacheKey)
      
      // 強制リフレッシュまたは初期化時はキャッシュを無視
      if (!forceRefresh && !initialized.value && cached && Date.now() - cached.timestamp < CACHE_TTL) {
        events.value = cached.data
        initialized.value = true
        return
      }
      
      const fetchedEvents = await fetchEvents(start, end, filterValues.memberId)
      const unifiedEvents: UnifiedEvent[] = fetchedEvents.map((event: any) => ({
        id: event.id || event.event_id,
        event_id: event.event_id,
        start: event.start_date,
        end: event.end_date,
        start_date: event.start_date,
        end_date: event.end_date,
        start_time: event.start_time,
        end_time: event.end_time,
        title: event.title,
        isAllDay: event.is_all_day,
        is_all_day: event.is_all_day,
        isImportant: event.importance === '重要',
        parentEventId: event.originalEventId,
        isException: event.isException,
        category: event.category,
        importance: event.importance,
        description: event.description,
        location: event.location,
        progress: event.progress,
        creator: event.creator,
        participants: event.participants,
        attachments: event.attachments
      }))
      
      eventCache.set(cacheKey, { data: unifiedEvents, timestamp: Date.now() })
      events.value = unifiedEvents
      initialized.value = true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      events.value = []
      initialized.value = true
    } finally {
      loading.value = false
    }
  }

  // フィルター変更時の再読み込み
  watch([rangeStart, rangeEnd, filters], 
    () => loadEvents(), 
    { deep: true, immediate: false }
  )

  return {
    events: computed(() => events.value),
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    initialized: computed(() => initialized.value),
    refresh: (forceRefresh = true) => loadEvents(forceRefresh),
    clearCache: () => eventCache.clear()
  }
}