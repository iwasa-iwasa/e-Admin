<script setup lang="ts">
import { ref, computed, watch, withDefaults } from 'vue'
import { router, usePage } from '@inertiajs/vue3' // Inertiaのインポート
// --- 🚨 修正箇所: CornerPin を MapPin に変更 🚨 ---
import { StickyNote, Plus, User, AlertCircle, Calendar, MapPin } from 'lucide-vue-next'
// -----------------------------------------------------
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'

// 子コンポーネントのインポートパスを修正（画面構成を崩さない最小限の修正）
import CreateNoteDialog from './Notes/CreateNoteDialog.vue' 
import NoteDetailDialog from './Notes/NoteDetailDialog.vue' 

type Priority = 'high' | 'medium' | 'low'
type SortOrder = 'priority' | 'deadline'

// --- 🐞 修正箇所: definePropsの構文をwithDefaultsでラップしてエラーを回避 🐞 ---
interface NotesProps {
    notes: App.Models.SharedNote[];
}

const props = withDefaults(defineProps<NotesProps>(), {
    notes: () => [],
});
// -------------------------------------------------------------------------

const page = usePage()
const sortOrder = ref<SortOrder>('priority')
const isCreateDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const isSaving = ref(false)

// --- メッセージ関連のロジックは一旦削除 ---

// NoteDetailDialogから保存ボタンが押されたときに呼び出される
const handleSaveNote = async (editedData: App.Models.SharedNote) => {
    isSaving.value = true
    
    // Inertia PUT リクエスト
    router.put(route('notes.update', editedData.note_id), editedData, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false
            // ここに保存成功メッセージ表示ロジックが入る
        }
    })
}

// ピン留め/ピン解除の処理
const togglePin = (note: App.Models.SharedNote) => {
    const routeName = note.is_pinned ? 'notes.unpin' : 'notes.pin';
    
    router.post(route(routeName, note.note_id), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
             // サーバーから返された新しい props.notes でUIが自動更新される
        }
    });
}

const getPriorityInfo = (priority: Priority) => {
    switch (priority) {
        case 'high':
            return { className: 'bg-red-600 text-white border-red-600', label: '重要' }
        case 'medium':
            return { className: 'bg-yellow-500 text-white border-yellow-500', label: '中' }
        case 'low':
            return { className: 'bg-gray-400 text-white border-gray-400', label: '低' }
    }
}

const getPriorityValue = (priority: Priority) => {
    switch (priority) {
        case 'high': return 3
        case 'medium': return 2
        case 'low': return 1
    }
}

const getColorClass = (color: string) => {
    const colorMap: { [key: string]: string } = {
        yellow: 'bg-yellow-100 border-yellow-300',
        blue: 'bg-blue-100 border-blue-300',
        green: 'bg-green-100 border-green-300',
        pink: 'bg-pink-100 border-pink-300',
        purple: 'bg-purple-100 border-purple-300',
    };
    return colorMap[color] || 'bg-gray-100 border-gray-300';
}

const toggleSortOrder = () => {
    sortOrder.value = sortOrder.value === 'priority' ? 'deadline' : 'priority'
}

const sortedNotes = computed(() => {
    if (!props.notes) return []

    return [...props.notes].sort((a, b) => {
        // 1. ピン留めを優先
        if (a.is_pinned !== b.is_pinned) {
            return a.is_pinned ? -1 : 1;
        }

        // 2. 選択された順序でソート
        if (sortOrder.value === 'priority') {
            const priorityDiff = getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
            if (priorityDiff !== 0) return priorityDiff
            return (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
        } else {
            const deadlineDiff = (a.deadline || '9999-12-31').localeCompare(b.deadline || '9999-12-31')
            if (deadlineDiff !== 0) return deadlineDiff
            return getPriorityValue(b.priority as Priority) - getPriorityValue(a.priority as Priority)
        }
    })
})
</script>

<template>
    <Card class="h-full flex flex-col relative overflow-hidden">
        <!-- メッセージ表示UIは一旦削除 -->
        
        <CardHeader>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <StickyNote class="h-5 w-5 text-yellow-600" />
                    <CardTitle class="text-lg">共有メモ</CardTitle>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    class="h-8 gap-1"
                    @click="isCreateDialogOpen = true"
                >
                    <Plus class="h-3 w-3" />
                    新規作成
                </Button>
            </div>
            <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-lg">
                <button
                    @click="toggleSortOrder"
                    :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'priority' ? 'bg-white shadow-sm border border-gray-200' : 'hover:bg-gray-200']"
                >
                    <AlertCircle :class="['h-3.5 w-3.5', sortOrder === 'priority' ? 'text-red-600' : 'text-gray-400']" />
                    <span :class="['text-xs', sortOrder === 'priority' ? 'text-gray-900' : 'text-gray-500']">
                        優先度順
                    </span>
                </button>
                <button
                    @click="toggleSortOrder"
                    :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'deadline' ? 'bg-white shadow-sm border border-gray-200' : 'hover:bg-gray-200']"
                >
                    <Calendar :class="['h-3.5 w-3.5', sortOrder === 'deadline' ? 'text-blue-600' : 'text-gray-400']" />
                    <span :class="['text-xs', sortOrder === 'deadline' ? 'text-gray-900' : 'text-gray-500']">
                        期限順
                    </span>
                </button>
            </div>
        </CardHeader>
        <CardContent class="flex-1 overflow-hidden p-0 px-6 pb-6">
            <ScrollArea class="h-full">
                <div class="space-y-3">
                    <div
                        v-for="note in sortedNotes"
                        :key="note.note_id"
                        :class="[getColorClass(note.color), 'border-2 rounded-lg p-3 cursor-pointer hover:shadow-lg transition-shadow relative']"
                        @click="selectedNote = note"
                    >
                        <div v-if="note.is_pinned" class="absolute top-0 right-0 p-1 text-yellow-600">
                             <MapPin class="h-4 w-4 fill-yellow-600" />
                        </div>
                        <div class="flex items-start justify-between mb-2">
                            <h4 class="flex-1 font-semibold text-gray-800 pr-4">
                                {{ note.title }}
                            </h4>
                            <Badge :class="[getPriorityInfo(note.priority as Priority).className, 'text-xs px-2 py-0.5']">
                                {{ getPriorityInfo(note.priority as Priority).label }}
                            </Badge>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line mb-2 line-clamp-3">
                            {{ note.content }}
                        </p>
                        <div class="flex items-center justify-between text-xs text-gray-600 mt-3 border-t border-gray-300 pt-2">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1">
                                    <User class="h-3 w-3" />
                                    {{ note.author?.name || 'N/A' }}
                                </div>
                                <Badge v-if="note.deadline" variant="outline" class="text-xs h-5 bg-white border-gray-400">
                                    期限: {{ note.deadline }}
                                </Badge>
                            </div>
                            <span class="text-gray-500">{{ new Date(note.updated_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </CardContent>
        <CreateNoteDialog
            :open="isCreateDialogOpen"
            @update:open="isCreateDialogOpen = $event"
        />
        <NoteDetailDialog
            :note="selectedNote"
            :open="selectedNote !== null"
            @update:open="(isOpen) => !isOpen && (selectedNote = null)"
            @save="handleSaveNote"
            @toggle-pin="togglePin"
        />
    </Card>
</template>