<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3' // Inertiaのインポート
import { StickyNote, Plus, User, AlertCircle, Calendar, MapPin, CheckCircle, Undo2 } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'

// 子コンポーネントのインポートパスを修正（画面構成を崩さない最小限の修正）
import CreateNoteDialog from './Notes/CreateNoteDialog.vue' 
import NoteDetailDialog from './Notes/NoteDetailDialog.vue' 

type Priority = 'high' | 'medium' | 'low'
type SortOrder = 'priority' | 'deadline'

interface NotesProps {
    notes: App.Models.SharedNote[];
}

const props = withDefaults(defineProps<NotesProps>(), {
    notes: () => [],
});

const page = usePage()
const sortOrder = ref<SortOrder>('priority')
const isCreateDialogOpen = ref(false)
const selectedNote = ref<App.Models.SharedNote | null>(null)
const isSaving = ref(false)

// メッセージとUndoロジック
const saveMessage = ref('')
const messageType = ref<'success' | 'delete'>('success')
const messageTimer = ref<number | null>(null)
const lastDeletedNote = ref<App.Models.SharedNote | null>(null) // 削除したメモを一時保存

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
    // 既存のタイマーをクリア
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    
    saveMessage.value = message
    messageType.value = type
    
    // 4秒後にメッセージを非表示にする
    messageTimer.value = setTimeout(() => {
        saveMessage.value = ''
        lastDeletedNote.value = null
    }, 4000)
}

// NoteDetailDialogから保存ボタンが押されたときに呼び出される
const handleSaveNote = async (editedData: App.Models.SharedNote) => {
    isSaving.value = true
    
    // Inertia PUT リクエスト
    router.put(route('notes.update', editedData.note_id), editedData, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false
            showMessage('メモが保存されました。', 'success') // メッセージ表示ロジック追加
        }
    })
}

// 削除処理 (DELETE)
const handleDeleteNote = (note: App.Models.SharedNote) => {
    // 削除前のメモを一時保存
    lastDeletedNote.value = note;
    
    router.delete(route('notes.destroy', note.note_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            // 削除成功メッセージ表示 (Undoボタン付き)
            showMessage('メモを削除しました。', 'delete')
            selectedNote.value = null // 詳細ダイアログを閉じる
        },
        onError: () => {
            // エラー時は一時保存を解除し、通常メッセージで通知
            lastDeletedNote.value = null
            showMessage('メモの削除に失敗しました。', 'success')
        }
    })
}

// Undo (元に戻す) 処理 (RESTORE)
const handleUndoDelete = () => {
    if (!lastDeletedNote.value) return;

    // メッセージタイマーをクリアし、メッセージを一旦非表示
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    saveMessage.value = '元に戻しています...'
    
    const noteToRestore = lastDeletedNote.value
    lastDeletedNote.value = null // Undo処理中はボタンを押せないように解除

    // サーバーサイドに復元リクエストを送る (notes.restore ルートがある前提)
    router.post(route('notes.restore', noteToRestore.note_id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
             showMessage('メモが元に戻されました。', 'success')
        },
        onError: () => {
            showMessage('元に戻す処理に失敗しました。', 'success')
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
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="transform opacity-0 -translate-y-full"
            enter-to-class="transform opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="transform opacity-100 translate-y-0"
            leave-to-class="transform opacity-0 -translate-y-full"
        >
            <div 
                v-if="saveMessage" 
                :class="['absolute top-0 left-0 right-0 z-10 p-3 shadow-md transition-all',
                    messageType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white']"
            >
                <div class="flex items-center justify-between max-w-lg mx-auto">
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5" />
                        <span class="font-medium">{{ saveMessage }}</span>
                    </div>
                    <Button 
                        v-if="messageType === 'delete' && lastDeletedNote"
                        variant="link"
                        class="text-white hover:bg-red-400 p-1 h-auto"
                        @click.stop="handleUndoDelete"
                    >
                        <Undo2 class="h-4 w-4 mr-1" />
                        <span class="underline">元に戻す</span>
                    </Button>
                </div>
            </div>
        </Transition>
        
        <CardHeader>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <StickyNote class="h-5 w-5 text-orange-600" />
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
                    :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'priority' ? 'bg-white shadow-sm border border-gray-300' : 'hover:bg-gray-200']"
                >
                    <AlertCircle :class="['h-3.5 w-3.5', sortOrder === 'priority' ? 'text-red-600' : 'text-gray-400']" />
                    <span :class="['text-xs', sortOrder === 'priority' ? 'text-gray-900' : 'text-gray-500']">
                        重要度順
                    </span>
                </button>
                <button
                    @click="toggleSortOrder"
                    :class="['flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded transition-all', sortOrder === 'deadline' ? 'bg-white shadow-sm border border-gray-300' : 'hover:bg-gray-200']"
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
                            <span class="text-gray-500">{{ note.updated_at ? new Date(note.updated_at).toLocaleDateString() : '' }}</span>
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
            @delete="handleDeleteNote"
        />
    </Card>
</template>