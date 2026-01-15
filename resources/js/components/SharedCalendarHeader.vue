<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { 
    Calendar as CalendarIcon, 
    ChevronLeft, 
    ChevronRight, 
    Plus, 
    ArrowLeft, 
    Search, 
    ChevronUp, 
    Filter 
} from 'lucide-vue-next'
import { CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CATEGORY_COLORS, CATEGORY_LABELS, GENRE_FILTERS, CATEGORY_ITEMS } from '@/constants/calendar'
import { useCalendarLayout } from '@/composables/calendar/useCalendarLayout'

const props = defineProps<{
    showBackButton?: boolean
    searchQuery: string
    genreFilter: string
    viewMode: string
    calendarTitle: string
    todayButtonText: string
    canGoBack: boolean
    currentDayViewDate: Date
    currentWeekStart: Date
}>()

const emit = defineEmits<{
    'update:searchQuery': [value: string]
    'update:genreFilter': [value: string]
    'update:viewMode': [value: string]
    'create': []
    'previous': []
    'next': []
    'today': []
    'goBackOneLevel': []
}>()

const headerRef = ref<HTMLElement | null>(null)

// useCalendarLayout with refs from props (converted to refs)
const { layoutMode, compactCalendarTitle } = useCalendarLayout(
    headerRef,
    computed(() => props.calendarTitle),
    computed(() => props.viewMode),
    computed(() => props.currentDayViewDate),
    computed(() => props.currentWeekStart)
)

// 検索バー閉じる処理
const isSearchOpen = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)

const toggleSearch = () => {
    isSearchOpen.value = !isSearchOpen.value
    if (!isSearchOpen.value) {
        emit('update:searchQuery', '')
    }
}

const handleUpdateSearchQuery = (e: Event) => {
    emit('update:searchQuery', (e.target as HTMLInputElement).value)
}

const handleUpdateGenreFilter = (value: any) => {
    emit('update:genreFilter', String(value))
}

const handleUpdateViewMode = (value: any) => {
    emit('update:viewMode', String(value))
}

</script>

<template>
    <div ref="headerRef" class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2 min-w-0 flex-shrink-0" 
                :class="!showBackButton ? 'cursor-pointer hover:opacity-70 transition-opacity' : ''" 
                @click="!showBackButton && router.visit('/calendar')">
                <Button v-if="showBackButton" variant="ghost" size="icon" @click="router.get('/')" class="mr-1">
                    <ArrowLeft class="h-5 w-5" />
                </Button>

                <CalendarIcon class="h-6 w-6 text-blue-700 flex-shrink-0" />

                <Transition
                    enter-active-class="transition-all duration-300 ease-in-out"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <CardTitle 
                        v-if="layoutMode === 'default' || layoutMode === 'filter-small' || layoutMode === 'search-icon'"
                        class="transition-all duration-300 ease-in-out whitespace-nowrap"
                    >
                        共有カレンダー
                    </CardTitle>
                </Transition>
            </div>
            <!-- 右上操作エリア -->
            <div class="flex items-center gap-2 min-w-0 flex-shrink">
                <!-- ジャンル Select -->
                <div class="transition-all duration-300 ease-in-out flex-shrink">
                    <Select :model-value="genreFilter" @update:model-value="handleUpdateGenreFilter" :key="`genre-${layoutMode}`">
                        <SelectTrigger 
                            class="transition-all duration-300 ease-in-out w-10 justify-center px-0 [&>svg:last-child]:hidden"
                        >
                            <Filter class="h-4 w-4" />
                        </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="GENRE_FILTERS.ALL">すべて</SelectItem>
                        <SelectItem :value="GENRE_FILTERS.BLUE">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['会議'] }"></div>
                                {{ CATEGORY_LABELS['会議'] }}
                            </div>
                        </SelectItem>
                        <SelectItem :value="GENRE_FILTERS.GREEN">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['業務'] }"></div>
                                {{ CATEGORY_LABELS['業務'] }}
                            </div>
                        </SelectItem>
                        <SelectItem :value="GENRE_FILTERS.YELLOW">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['来客'] }"></div>
                                {{ CATEGORY_LABELS['来客'] }}
                            </div>
                        </SelectItem>
                        <SelectItem :value="GENRE_FILTERS.PURPLE">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['出張'] }"></div>
                                {{ CATEGORY_LABELS['出張'] }}
                            </div>
                        </SelectItem>
                        <SelectItem :value="GENRE_FILTERS.PINK">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['休暇'] }"></div>
                                {{ CATEGORY_LABELS['休暇'] }}
                            </div>
                        </SelectItem>
                        <SelectItem :value="GENRE_FILTERS.OTHER">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: CATEGORY_COLORS['その他'] }"></div>
                                {{ CATEGORY_LABELS['その他'] }}
                            </div>
                        </SelectItem>
                    </SelectContent>
                    </Select>
                </div>

                <!-- 検索エリア -->
                <div class="relative transition-all duration-300 ease-in-out flex-shrink">
                    <div v-if="(layoutMode === 'default' || layoutMode === 'filter-small')" class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                        <Input
                            :model-value="searchQuery"
                            @input="handleUpdateSearchQuery"
                            type="text"
                            placeholder="タイトルなどで検索"
                            class="pl-10 min-w-0 transition-all duration-300 ease-in-out"
                            :class="layoutMode === 'filter-small' ? 'max-w-[200px]' : 'max-w-[280px]'"
                        />
                    </div>
                    <div v-else-if="layoutMode === 'search-icon' || layoutMode === 'title-hide' || layoutMode === 'compact' || layoutMode === 'minimal' || layoutMode === 'ultra-minimal'" class="flex items-center transition-all duration-300 ease-in-out">
                        <div class="relative flex items-center">
                            <Button
                                variant="outline"
                                size="icon"
                                @click="toggleSearch"
                                class="transition-all duration-300 ease-in-out"
                                :class="isSearchOpen ? 'rounded-r-none border-r-0' : ''"
                                tabindex="-1"
                            >
                                <Search class="h-4 w-4"/>
                            </Button>
                            <Transition
                                enter-active-class="transition-all duration-300 ease-in-out"
                                leave-active-class="transition-all duration-300 ease-in-out"
                                enter-from-class="w-0 opacity-0"
                                enter-to-class="w-[140px] opacity-100"
                                leave-from-class="w-[140px] opacity-100"
                                leave-to-class="w-0 opacity-0"
                            >
                                <Input
                                    v-if="isSearchOpen"
                                    :model-value="searchQuery"
                                    @input="handleUpdateSearchQuery"
                                    type="text"
                                    placeholder="タイトルなどで検索"
                                    class="rounded-l-none border-l-0 transition-all duration-300 ease-in-out"
                                    @blur="!searchQuery && toggleSearch()"
                                    @keydown.escape="toggleSearch()"
                                    ref="searchInput"
                                />
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- 新規作成 -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-in-out"
                    leave-active-class="transition-all duration-300 ease-in-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div class="transition-all duration-300 ease-in-out">
                        <Button
                            :key="`create-${layoutMode}`"
                            variant="outline"
                            class="transition-all duration-300 ease-in-out flex-shrink-0"
                            :class="layoutMode === 'default' || layoutMode === 'filter-small' ? 'gap-2' : ''"
                            @click="emit('create')"
                            :title="layoutMode === 'search-icon' || layoutMode === 'title-hide' || layoutMode === 'compact' || layoutMode === 'minimal' || layoutMode === 'ultra-minimal' ? '新規作成' : undefined"
                        >
                            <Plus class="h-4 w-4" />
                            <Transition
                                enter-active-class="transition-all duration-300 ease-in-out"
                                leave-active-class="transition-all duration-300 ease-in-out"
                                enter-from-class="w-0 opacity-0"
                                enter-to-class="w-auto opacity-100"
                                leave-from-class="w-auto opacity-100"
                                leave-to-class="w-0 opacity-0"
                            >
                                <span v-if="layoutMode === 'default' || layoutMode === 'filter-small'" class="whitespace-nowrap">
                                    新規作成
                                </span>
                            </Transition>
                        </Button>
                    </div>
                </Transition>
            </div>
        </div>

        <div class="flex items-center gap-4 transition-all duration-300 ease-in-out" :class="layoutMode === 'default' ? 'justify-between' : 'justify-end'">
            <Transition
                enter-active-class="transition-all duration-300 ease-in-out"
                leave-active-class="transition-all duration-300 ease-in-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="layoutMode === 'default'" class="flex-1">
                    <Tabs :model-value="viewMode" @update:model-value="handleUpdateViewMode" class="flex-1">
                        <TabsList class="grid w-full max-w-[400px] grid-cols-4 bg-gray-100">
                            <TabsTrigger value="multiMonthYear">年</TabsTrigger>
                            <TabsTrigger value="dayGridMonth">月</TabsTrigger>
                            <TabsTrigger value="timeGridWeek">週</TabsTrigger>
                            <TabsTrigger value="timeGridDay">日</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
            </Transition>

            <div class="flex items-center gap-3 transition-all duration-300 ease-in-out">
                <Button 
                    v-if="canGoBack" 
                    variant="outline" 
                    size="sm" 
                    @click="emit('goBackOneLevel')"
                    class="gap-1 transition-all duration-300 ease-in-out flex-shrink-0"
                >
                    <ChevronUp class="h-4 w-4" />
                    <Transition
                        enter-active-class="transition-all duration-300 ease-in-out"
                        leave-active-class="transition-all duration-300 ease-in-out"
                        enter-from-class="w-0 opacity-0"
                        enter-to-class="w-auto opacity-100"
                        leave-from-class="w-auto opacity-100"
                        leave-to-class="w-0 opacity-0"
                    >
                        <span v-if="layoutMode === 'default' || layoutMode === 'filter-small'" class="whitespace-nowrap">戻る</span>
                    </Transition>
                </Button>
                <Button variant="outline" size="sm" @click="emit('previous')" class="flex-shrink-0">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div 
                    class="text-center font-semibold truncate transition-all duration-300 ease-in-out flex-shrink-0"
                >
                    {{ compactCalendarTitle }}
                </div>
                <Button variant="outline" size="sm" @click="emit('next')" class="flex-shrink-0">
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button variant="outline" size="sm" @click="emit('today')" class="flex-shrink-0">{{ todayButtonText }}</Button>
            </div>
        </div>
    </div>
</template>
