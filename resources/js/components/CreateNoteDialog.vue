<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { Save, X, CheckCircle, Pin, Info } from "lucide-vue-next";
import { CATEGORY_LABELS, CATEGORY_COLORS, loadCategoryLabels } from '@/constants/calendar'
import { onMounted } from 'vue'
import '@vuepic/vue-datepicker/dist/main.css';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/components/ui/toast/use-toast";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

type Priority = "high" | "medium" | "low";

const props = defineProps<{
    open: boolean;
    teamMembers?: App.Models.User[];
}>();
const emit = defineEmits(["update:open"]);

const form = useForm<{
    title: string;
    content: string;
    priority: Priority;
    deadline: string;
    tags: string[];
    color: string;
    participants: number[];
    pinned: boolean;
}>({
    title: "",
    content: "",
    priority: "medium",
    deadline: "",
    tags: [] as string[],
    color: "yellow",
    participants: [],
    pinned: false,
});

const tagInput = ref("");
const selectedParticipants = ref<App.Models.User[]>([]);
const activeTab = ref("basic");
const deadlineDateTime = ref<Date | null>(null);

const { toast } = useToast();
const currentUserId = computed(() => (usePage().props as any).auth?.user?.id ?? null)
const saveMessage = ref('')
const messageTimer = ref<number | null>(null)
const showDraftBanner = ref(false)
const showDraftDialog = ref(false)
const pendingDraft = ref<any>(null)

const DRAFT_KEY = 'note_draft'

const showMessage = (message: string) => {
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    
    saveMessage.value = message
    
    messageTimer.value = setTimeout(() => {
        saveMessage.value = ''
    }, 4000)
}

const saveDraft = () => {
    const draft = {
        title: form.title,
        content: form.content,
        priority: form.priority,
        deadline: form.deadline,
        tags: form.tags,
        color: form.color,
        participants: form.participants,
        selectedParticipants: selectedParticipants.value,
        pinned: form.pinned,
    }
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft))
}

const loadDraft = () => {
    const draft = sessionStorage.getItem(DRAFT_KEY)
    return draft ? JSON.parse(draft) : null
}

const clearDraft = () => {
    sessionStorage.removeItem(DRAFT_KEY)
    showDraftBanner.value = false
}

const restoreDraft = () => {
    if (pendingDraft.value) {
        form.title = pendingDraft.value.title
        form.content = pendingDraft.value.content
        form.priority = pendingDraft.value.priority
        form.deadline = pendingDraft.value.deadline
        form.tags = pendingDraft.value.tags
        form.color = pendingDraft.value.color
        form.participants = pendingDraft.value.participants || []
        selectedParticipants.value = pendingDraft.value.selectedParticipants || []
        form.pinned = pendingDraft.value.pinned
        showDraftBanner.value = true
    }
    showDraftDialog.value = false
    pendingDraft.value = null
}

const discardDraft = () => {
    clearDraft()
    showDraftDialog.value = false
    pendingDraft.value = null
    form.reset()
    form.tags = []
    form.participants = []
    form.pinned = false
    selectedParticipants.value = []
    tagInput.value = ""
}

const handleSave = () => {
    if (!form.title.trim()) {
        toast({
            title: "Error",
            description: "タイトルを入力してください",
            variant: "destructive",
        });
        return;
    }
    
    saveDraft()
    
    form.post(route("shared-notes.store"), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            clearDraft()
            showMessage('共有メモを正常に作成しました。')
            handleClose();
        },
        onError: (errors) => {
             toast({
                title: "Error",
                description: "メモの作成中にエラーが発生しました。",
                variant: "destructive",
            });
            console.error(errors);
        },
    });
};

const handleClose = () => {
    form.reset();
    form.tags = [];
    form.participants = [];
    form.pinned = false;
    selectedParticipants.value = [];
    tagInput.value = "";
    activeTab.value = "basic";
    emit("update:open", false);
};

const handleDialogClose = (isOpen: boolean) => {
    if (!isOpen) {
        handleClose();
    }
};

const handleAddTag = () => {
    if (tagInput.value.trim() && !form.tags.includes(tagInput.value.trim())) {
        form.tags.push(tagInput.value.trim());
        tagInput.value = "";
    }
};

const handleRemoveTag = (tagToRemove: string) => {
    form.tags = form.tags.filter((tag) => tag !== tagToRemove);
};

const handleAddParticipant = (memberId: unknown) => {
    if (memberId === null || memberId === undefined) return
    const id = Number(memberId as any)
    if (Number.isNaN(id)) return
    const member = props.teamMembers?.find((m) => m.id === id)
    if (member && !selectedParticipants.value.find((p) => p.id === member.id)) {
        selectedParticipants.value.push(member)
        form.participants.push(member.id)
    }
}

const handleRemoveParticipant = (participantId: number) => {
    selectedParticipants.value = selectedParticipants.value.filter((p) => p.id !== participantId)
    form.participants = form.participants.filter((id) => id !== participantId)
}

const handleSelectAll = () => {
    if (!props.teamMembers) return
    selectedParticipants.value = [...props.teamMembers.filter(m => m.id !== currentUserId.value)]
    form.participants = selectedParticipants.value.map(p => p.id)
}

const handleDeselectAll = () => {
    selectedParticipants.value = []
    form.participants = []
}

const getPriorityInfo = (p: Priority) => {
    switch (p) {
        case "high":
            return { className: "bg-red-600 text-white", label: "重要" };
        case "medium":
            return { className: "bg-yellow-500 text-white", label: "中" };
        case "low":
            return { className: "bg-gray-400 text-white", label: "低" };
    }
};

const getColorInfo = (c: string) => {
    const colorMap: Record<string, { bg: string; label: string; color: string }> = {
        blue: { bg: "bg-blue-100 dark:bg-blue-500", label: CATEGORY_LABELS.value['会議'] || '会議', color: CATEGORY_COLORS['会議'] },
        green: { bg: "bg-green-100 dark:bg-green-500", label: CATEGORY_LABELS.value['業務'] || '業務', color: CATEGORY_COLORS['業務'] },
        yellow: { bg: "bg-yellow-100 dark:bg-yellow-500", label: CATEGORY_LABELS.value['来客'] || '来客', color: CATEGORY_COLORS['来客'] },
        purple: { bg: "bg-purple-100 dark:bg-purple-500", label: CATEGORY_LABELS.value['出張・外出'] || '出張・外出', color: CATEGORY_COLORS['出張・外出'] },
        pink: { bg: "bg-pink-100 dark:bg-pink-500", label: CATEGORY_LABELS.value['休暇'] || '休暇', color: CATEGORY_COLORS['休暇'] },
        gray: { bg: "bg-gray-100 dark:bg-gray-500", label: CATEGORY_LABELS.value['その他'] || 'その他', color: CATEGORY_COLORS['その他'] },
    };
    return colorMap[c] || colorMap.yellow;
};

onMounted(() => {
    loadCategoryLabels()
    const handleCategoryUpdate = () => loadCategoryLabels()
    window.addEventListener('category-labels-updated', handleCategoryUpdate)
})

watch(() => props.open, (isOpen) => {
    console.log('[CreateNoteDialog] watch props.open:', isOpen)
    if (isOpen) {
        // 複製データをチェック
        const copyData = sessionStorage.getItem('note_copy_data')
        console.log('[CreateNoteDialog] note_copy_data from sessionStorage:', copyData)
        if (copyData) {
            const data = JSON.parse(copyData)
            console.log('[CreateNoteDialog] Parsed copy data:', data)
            
            form.title = data.title
            form.content = data.content
            form.color = data.color
            form.priority = data.priority
            form.tags = data.tags || []
            form.pinned = false
            
            // 全員を選択
            if (props.teamMembers) {
                selectedParticipants.value = [...props.teamMembers.filter(m => m.id !== currentUserId.value)]
                form.participants = selectedParticipants.value.map(p => p.id)
                console.log('[CreateNoteDialog] Selected all participants:', selectedParticipants.value.length)
            }
            
            if (data.deadline_date) {
                const time = data.deadline_time ? data.deadline_time.substring(0, 5) : '23:59'
                form.deadline = `${data.deadline_date}T${time}`
                deadlineDateTime.value = new Date(`${data.deadline_date}T${time}`)
            } else {
                form.deadline = ''
                deadlineDateTime.value = null
            }
            
            sessionStorage.removeItem('note_copy_data')
            console.log('[CreateNoteDialog] Copy data loaded and removed from sessionStorage')
        } else {
            const draft = loadDraft()
            if (draft) {
                pendingDraft.value = draft
                showDraftDialog.value = true
            } else {
                // 新規作成時は全員を選択
                if (props.teamMembers) {
                    selectedParticipants.value = [...props.teamMembers.filter(m => m.id !== currentUserId.value)]
                    form.participants = selectedParticipants.value.map(p => p.id)
                    console.log('[CreateNoteDialog] New note - Selected all participants:', selectedParticipants.value.length)
                }
                
                // Initialize deadlineDateTime from form.deadline
                if (form.deadline) {
                    deadlineDateTime.value = new Date(form.deadline)
                } else {
                    deadlineDateTime.value = null
                }
            }
        }
    }
})

watch(deadlineDateTime, (newDate) => {
    if (newDate) {
        form.deadline = newDate.toISOString().slice(0, 16)
    } else {
        form.deadline = ''
    }
})
</script>

<template>
    <Dialog :open="open" @update:open="handleDialogClose">
        <DialogContent class="max-w-4xl md:max-w-5xl lg:max-w-6xl w-[95vw] md:w-[66vw] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>新しい共有メモを作成</DialogTitle>
                <DialogDescription>
                    部署全員で共有できるメモを作成します
                </DialogDescription>
            </DialogHeader>
            
            <div v-if="showDraftBanner" class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2 dark:bg-blue-900/20 dark:border-blue-800">
              <Info class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5 dark:text-blue-400" />
              <div class="flex-1">
                <p class="text-sm text-blue-800 dark:text-blue-300">下書きから復元されました</p>
              </div>
              <button @click="showDraftBanner = false" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                <X class="h-4 w-4" />
              </button>
            </div>

            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="basic">基本情報</TabsTrigger>
                    <TabsTrigger value="settings">その他設定</TabsTrigger>
                </TabsList>
                
                <TabsContent value="basic" class="space-y-4 mt-4">
                    <div class="space-y-2">
                        <Label for="title">タイトル *</Label>
                        <Input
                            id="title"
                            placeholder="メモのタイトル"
                            v-model="form.title"
                            autofocus
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="content">内容</Label>
                        <Textarea
                            id="content"
                            placeholder="メモの内容を入力..."
                            v-model="form.content"
                            rows="8"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="priority">重要度</Label>
                        <Select v-model="form.priority">
                            <SelectTrigger id="priority">
                                <div class="flex items-center gap-2">
                                    <Badge
                                        :class="getPriorityInfo(form.priority).className"
                                    >
                                        {{ getPriorityInfo(form.priority).label }}
                                    </Badge>
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="high">
                                    <Badge class="bg-red-600 text-white"
                                        >重要</Badge
                                    >
                                </SelectItem>
                                <SelectItem value="medium">
                                    <Badge class="bg-yellow-500 text-white"
                                        >中</Badge
                                    >
                                </SelectItem>
                                <SelectItem value="low">
                                    <Badge class="bg-gray-400 text-white">低</Badge>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </TabsContent>
                
                <TabsContent value="settings" class="space-y-4 mt-4">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="deadline">期限（任意）</Label>
                            <VueDatePicker
                                v-model="deadlineDateTime"
                                :locale="ja"
                                format="yyyy-MM-dd HH:mm"
                                :week-start="0"
                                auto-apply
                                teleport-center
                                enable-time-picker
                                placeholder="期限を選択"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="color">ジャンル</Label>
                            <Select v-model="form.color">
                                <SelectTrigger id="color">
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo(form.color).color }"></div>
                                        <span>{{ getColorInfo(form.color).label }}</span>
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="blue">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('blue').color }"></div>
                                            <span>{{ getColorInfo('blue').label }}</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="green">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('green').color }"></div>
                                            <span>{{ getColorInfo('green').label }}</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="yellow">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('yellow').color }"></div>
                                            <span>{{ getColorInfo('yellow').label }}</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="purple">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('purple').color }"></div>
                                            <span>{{ getColorInfo('purple').label }}</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="pink">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('pink').color }"></div>
                                            <span>{{ getColorInfo('pink').label }}</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="gray">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: getColorInfo('gray').color }"></div>
                                            <span>{{ getColorInfo('gray').label }}</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>ピン留め</Label>
                            <label class="flex items-center gap-2 h-10 px-3 border dark:border-gray-700 rounded-md cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                <input 
                                    type="checkbox" 
                                    v-model="form.pinned"
                                    class="h-4 w-4 text-yellow-600 rounded border-gray-300"
                                />
                                <Pin class="h-4 w-4" :class="form.pinned ? 'text-yellow-500 fill-current' : 'text-gray-400'" />
                                <span class="text-sm">ピン留め</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label for="participants">共有メンバー</Label>
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="outline" size="sm" @click="handleSelectAll" class="h-7 text-xs">
                                    全員選択
                                </Button>
                                <Button type="button" variant="outline" size="sm" @click="handleDeselectAll" class="h-7 text-xs">
                                    選択解除
                                </Button>
                            </div>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 p-2 bg-gray-50 dark:bg-gray-800 rounded border dark:border-gray-700">
                            💡 メンバーを選択すると、選択したメンバーと自分のみに表示されます。選択しない場合は全員に表示されます。
                        </div>
                        <div class="max-h-[200px] overflow-y-auto border dark:border-gray-700 rounded p-2 space-y-1">
                            <label v-for="member in props.teamMembers?.filter(m => m.id !== currentUserId)" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedParticipants.find(p => p.id === member.id) !== undefined"
                                    @change="(e) => (e.target as HTMLInputElement).checked ? handleAddParticipant(member.id) : handleRemoveParticipant(member.id)"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <span class="text-xs dark:text-gray-300">{{ member.name }}</span>
                            </label>
                        </div>
                        <div v-if="selectedParticipants.length > 0" class="min-h-[60px] p-3 border border-purple-300 dark:border-purple-700 rounded-md bg-purple-50 dark:bg-purple-900/20">
                            <div class="text-xs font-medium text-purple-800 dark:text-purple-300 mb-2">🔒 限定公開: 選択されたメンバーと自分のみ表示</div>
                            <div class="flex flex-wrap gap-2">
                                <Badge v-for="participant in selectedParticipants" :key="participant.id" variant="secondary" class="gap-2 px-3 py-1">
                                    {{ participant.name }}
                                    <button @click="handleRemoveParticipant(participant.id)" class="hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full p-0.5">
                                        <X class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                        </div>
                        <div v-else class="min-h-[40px] p-3 border border-input rounded-md bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-sm">
                            🌐 全体公開: 全員に表示されます
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="tags">タグ</Label>
                        <div class="flex gap-2">
                            <Input
                                id="tags"
                                placeholder="タグを追加"
                                v-model="tagInput"
                                @keypress.enter.prevent="handleAddTag"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                @click="handleAddTag"
                            >
                                追加
                            </Button>
                        </div>
                        <div
                            v-if="form.tags.length > 0"
                            class="flex flex-wrap gap-2 mt-2"
                        >
                            <Badge
                                v-for="tag in form.tags"
                                :key="tag"
                                variant="secondary"
                                class="gap-2"
                            >
                                {{ tag }}
                                <button @click="handleRemoveTag(tag)">
                                    <X class="h-3 w-3" />
                                </button>
                            </Badge>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">
                    キャンセル
                </Button>
                <Button 
                    variant="outline"
                    @click="handleSave" 
                    :disabled="form.processing" 
                    class="gap-2"
                >
                    <Save class="h-4 w-4" />
                    作成
                </Button>
            </DialogFooter>
        </DialogContent>
        
        <!-- 下部メッセージ -->
        <Transition
          enter-active-class="transition ease-out duration-300"
          enter-from-class="transform opacity-0 translate-y-full"
          enter-to-class="transform opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-200"
          leave-from-class="transform opacity-100 translate-y-0"
          leave-to-class="transform opacity-0 translate-y-full"
        >
          <div 
            v-if="saveMessage"
            class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg bg-green-500"
          >
            <div class="flex items-center gap-2">
              <CheckCircle class="h-5 w-5" />
              <span class="font-medium">{{ saveMessage }}</span>
            </div>
          </div>
        </Transition>
        
        <!-- ドラフト復元確認ダイアログ -->
        <Dialog :open="showDraftDialog" @update:open="(val) => !val && discardDraft()">
          <DialogContent class="max-w-md">
            <DialogHeader>
              <DialogTitle>下書きが見つかりました</DialogTitle>
              <DialogDescription>前回保存に失敗した内容が残っています。復元しますか？</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" @click="discardDraft">破棄</Button>
              <Button @click="restoreDraft">復元</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
    </Dialog>
</template>

