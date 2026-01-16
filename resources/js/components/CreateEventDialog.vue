<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import {
    Calendar as CalendarIcon,
    Users,
    MapPin,
    FileText,
    AlertCircle,
    X,
    Save,
    Clock,
    Link as LinkIcon,
    Repeat,
    Paperclip,
    CheckCircle,
    Info,
} from "lucide-vue-next";
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
import { Switch } from '@/components/ui/switch'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

import { VueDatePicker } from '@vuepic/vue-datepicker';
import { TimePicker } from "vue-material-time-picker";
import { ja } from "date-fns/locale"
import '@vuepic/vue-datepicker/dist/main.css';
import "vue-material-time-picker/dist/style.css";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

const props = defineProps<{
    open: boolean;
    event?: App.Models.Event | null;
    readonly?: boolean;
}>();
const emit = defineEmits(["update:open"]);

const isEditMode = computed(() => !!props.event);
const fileInput = ref<HTMLInputElement | null>(null);

const page = usePage()
const saveMessage = ref('')
const messageType = ref<'success' | 'error'>('success')
const messageTimer = ref<number | null>(null)
const showDraftBanner = ref(false)
const showDraftDialog = ref(false)
const pendingDraft = ref<any>(null)

const DRAFT_KEY = 'event_draft'

const teamMembers = computed(() => page.props.teamMembers as App.Models.User[])
const currentUserId = computed(() => (page.props as any).auth?.user?.id ?? null)

// 参加者リストに含まれていないメンバー（選択肢用）
const availableMembers = computed(() => {
  return teamMembers.value.filter(member => 
    member.id !== currentUserId.value && 
    !form.participants.find(p => p.id === member.id)
  )
})

// 全員共有かどうか（自分以外の全メンバーが参加者に含まれている）
const isAllUsers = computed(() => {
  const otherMembersCount = teamMembers.value.length - 1
  return form.participants.length > 0 && form.participants.length === otherMembersCount
})

// 編集権限チェック
const canEdit = computed(() => {
  if (props.readonly) return false
  if (!isEditMode.value || !props.event) return true
  const event = props.event
  const isCreator = event.created_by === currentUserId.value
  
  if (isCreator) return true
  
  if (event.participants && event.participants.length === teamMembers.value.length) {
    return true
  }
  
  const isParticipant = event.participants?.some(p => p.id === currentUserId.value)
  return isParticipant || false
})

// 参加者編集権限: 作成者または参加者
const canEditParticipants = computed(() => {
  if (!isEditMode.value || !props.event) return true
  const isCreator = props.event.created_by === currentUserId.value
  // 作成者は常に編集可能
  if (isCreator) return true
  // 全員共有の場合、参加者も編集可能
  if (isAllUsers.value) return true
  // 個人指定の場合、参加者のみ編集可能
  const isParticipant = props.event.participants?.some(p => p.id === currentUserId.value)
  return isParticipant || false
})

const form = useForm({
  title: '',
  is_all_day: false,
  date_range: [new Date(), new Date()] as [Date, Date],
  start_time: '09:00',
  end_time: '10:00',
  participants: [] as App.Models.User[],
  location: '',
  description: '',
  url: '',
  category: '会議',
  importance: '中',
  event_progress: 0,

  recurrence: {
    is_recurring: false,
    recurrence_type: 'daily',
    recurrence_interval: 1,
    by_day: [] as string[],
    by_set_pos: null as number | null,
    end_date: null as Date | null,
  },
  attachments: {
    existing: [] as App.Models.EventAttachment[],
    new_files: [] as File[],
    removed_ids: [] as number[],
  },
})
const is_all_day = ref(form.is_all_day);
const date = ref<[Date, Date]>([new Date(), new Date()]);
const weekdays = [
    { label: '月', value: 'MO' },
    { label: '火', value: 'TU' },
    { label: '水', value: 'WE' },
    { label: '木', value: 'TH' },
    { label: '金', value: 'FR' },
    { label: '土', value: 'SA' },
    { label: '日', value: 'SU' },
];

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    if (isEditMode.value && props.event) {
      const eventData = props.event;
      form.title = eventData.title;
      form.is_all_day = eventData.is_all_day;
      form.date_range = [new Date(eventData.start_date), new Date(eventData.end_date)];
      form.start_time = eventData.start_time?.slice(0, 5) || '09:00';
      form.end_time = eventData.end_time?.slice(0, 5) || '10:00';
      form.participants = eventData.participants || [];
      form.location = eventData.location || '';
      form.description = eventData.description || '';
      form.url = eventData.url || '';
      form.category = eventData.category || '会議';
      form.importance = eventData.importance || '中';
      form.event_progress = (eventData.progress ?? 0) as number;

      
      if (eventData.recurrence) {
        form.recurrence.is_recurring = true;
        form.recurrence.recurrence_type = eventData.recurrence.recurrence_type;
        form.recurrence.recurrence_interval = eventData.recurrence.recurrence_interval;
        form.recurrence.by_day = eventData.recurrence.by_day || [];
        form.recurrence.by_set_pos = eventData.recurrence.by_set_pos;
        form.recurrence.end_date = eventData.recurrence.end_date ? new Date(eventData.recurrence.end_date) : null;
      } else {
        form.recurrence.is_recurring = false;
        form.recurrence.recurrence_type = 'daily';
        form.recurrence.recurrence_interval = 1;
        form.recurrence.by_day = [];
        form.recurrence.by_set_pos = null;
        form.recurrence.end_date = null;
      }

      form.attachments.existing = eventData.attachments || [];
      form.attachments.new_files = [];
      form.attachments.removed_ids = [];

      date.value = form.date_range;
      is_all_day.value = form.is_all_day;
    } else {
      const draft = loadDraft()
      if (draft) {
        pendingDraft.value = draft
        showDraftDialog.value = true
      } else {
        form.reset();
        const now = new Date();
        date.value = [now, now];
        form.date_range = [now, now];
        is_all_day.value = false;
      }
    }
  } else {
    // Dialog is closing - cleanup
    nextTick(() => {
      showDraftDialog.value = false
      pendingDraft.value = null
      showDraftBanner.value = false
    })
  }
}, { immediate: true });


watch(date, (newDates) => {
    if (newDates && Array.isArray(newDates) && newDates[0] instanceof Date) {
        const startDate = newDates[0];
        const endDate = (newDates[1] instanceof Date) ? newDates[1] : startDate;
        form.date_range = [startDate, endDate];
    } else {
        const now = new Date();
        form.date_range = [now, now];
        date.value = [now, now];
    }
}, { deep: true });

const format = (dates: Date[]) => {
    if (dates && dates.length === 2) {
        const start = dates[0].toLocaleDateString('ja-JP');
        const end = dates[1].toLocaleDateString('ja-JP');
        return `${start} - ${end}`;
    }
    return '';
}

const handleAllDayToggle = (value: boolean) => {
  form.is_all_day = value;
};

const handleAddParticipant = (value: string) => {
  if (value === 'none') {
    form.participants = []
  } else if (value === 'all') {
    form.participants = teamMembers.value.filter(m => m.id !== currentUserId.value)
  } else {
    const memberId = Number(value)
    const member = teamMembers.value.find(m => m.id === memberId)
    if (member) {
      form.participants.push(member)
    }
  }
}

const handleRemoveParticipant = (participantId: number) => {
  form.participants = form.participants.filter((p) => p.id !== participantId)
}

const MAX_FILE_SIZE = 41943040; // Approximately 40MB, matching common PHP post_max_size

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        Array.from(target.files).forEach(file => {
            if (file.size > MAX_FILE_SIZE) {
                showMessage(`${file.name} はサイズ上限 (${(MAX_FILE_SIZE / (1024 * 1024)).toFixed(0)}MB) を超えています。`, 'error');
            } else {
                form.attachments.new_files.push(file);
            }
        });
        // Clear the input to allow selecting the same file again if needed
        target.value = '';
    }
};

const handleRemoveNewFile = (index: number) => {
    form.attachments.new_files.splice(index, 1);
};

const handleRemoveExistingFile = (attachmentId: number) => {
    form.attachments.removed_ids.push(attachmentId);
    form.attachments.existing = form.attachments.existing.filter(
        (file) => file.attachment_id !== attachmentId
    );
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const toggleByDay = (day: string) => {
    const index = form.recurrence.by_day.indexOf(day);
    if (index > -1) {
        form.recurrence.by_day.splice(index, 1);
    } else {
        form.recurrence.by_day.push(day);
    }
};

const saveDraft = () => {
  const draft = {
    title: form.title,
    is_all_day: form.is_all_day,
    date_range: form.date_range,
    start_time: form.start_time,
    end_time: form.end_time,
    participants: form.participants,
    location: form.location,
    description: form.description,
    url: form.url,
    category: form.category,
    importance: form.importance,
    progress: form.event_progress,
    recurrence: form.recurrence,
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
    form.is_all_day = pendingDraft.value.is_all_day
    form.date_range = pendingDraft.value.date_range.map((d: string) => new Date(d))
    form.start_time = pendingDraft.value.start_time
    form.end_time = pendingDraft.value.end_time
    form.participants = pendingDraft.value.participants
    form.location = pendingDraft.value.location
    form.description = pendingDraft.value.description
    form.url = pendingDraft.value.url
    form.category = pendingDraft.value.category
    form.importance = pendingDraft.value.importance
    form.event_progress = pendingDraft.value.progress
    form.recurrence = pendingDraft.value.recurrence
    date.value = form.date_range as [Date, Date]
    is_all_day.value = form.is_all_day
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
  const now = new Date()
  date.value = [now, now]
  form.date_range = [now, now]
  is_all_day.value = false
}

const handleSave = () => {
  // 必須項目の検証
  if (!form.title?.trim()) {
    showMessage('タイトルを入力してください', 'error')
    return
  }
  
  // date_rangeの検証を強化
  if (!form.date_range || !Array.isArray(form.date_range) || 
      !form.date_range[0] || !form.date_range[1] ||
      !(form.date_range[0] instanceof Date) || !(form.date_range[1] instanceof Date)) {
    showMessage('日付が正しく設定されていません', 'error')
    return
  }
  
  saveDraft()
  
  const transformData = (data: any) => {
    // date_rangeが正しく設定されていることを確認
    if (!data.date_range || !Array.isArray(data.date_range) || data.date_range.length !== 2) {
      throw new Error('Invalid date_range')
    }
    
    const transformed: Record<string, any> = {
        ...data,
        // date_rangeを確実にISO文字列として送信
        date_range: [
          data.date_range[0].toISOString().split('T')[0],
          data.date_range[1].toISOString().split('T')[0]
        ],
        participants: data.participants.map((p: App.Models.User) => p.id)
    };
    if (isEditMode.value) {
        transformed._method = 'put';
    }
    return transformed;
  };

  const options = {
    onSuccess: () => {
      clearDraft()
      handleClose()
      setTimeout(() => {
        showMessage(`予定を${isEditMode.value ? '更新' : '作成'}しました`, 'success')
      }, 100)
    },
    onError: (errors: any) => {
      console.log(errors)
      const firstError = Object.values(errors)[0] as string
      showMessage(firstError || '保存に失敗しました', 'error')
    }
  };

  if (isEditMode.value && props.event) {
    form.transform(transformData).post(route('events.update', { event: props.event.event_id }), options);
  } else {
    form.transform(transformData).post(route('events.store'), options);
  }
}

// 確認完了ハンドラー（閲覧のみのユーザー用）
const handleConfirm = () => {
  handleClose()
  setTimeout(() => {
    showMessage('確認しました', 'success')
  }, 100)
}

const handleClose = () => {
    form.reset()
    date.value = [new Date(), new Date()];
    showDraftDialog.value = false
    pendingDraft.value = null
    showDraftBanner.value = false
    emit("update:open", false);
    
    // Ensure body overflow is restored
    nextTick(() => {
        document.body.style.removeProperty('overflow')
        document.body.style.removeProperty('pointer-events')
    })
};

const getImportanceColor = (imp: string) => {
    switch (imp) {
        case "重要": return "text-red-600";
        case "中": return "text-yellow-600";
        case "低": return "text-gray-600";
        default: return "text-gray-600";
    }
};

const getCategoryColor = (cat: string) => {
    switch (cat) {
        case "会議": return "bg-[#3b82f6]";
        case "業務": return "bg-[#66bb6a]";
        case "来客": return "bg-[#ffa726]";
        case "出張": return "bg-[#9575cd]";
        case "休暇": return "bg-[#f06292]";
        default: return "bg-gray-500";
    }
};

const showMessage = (message: string, type: 'success' | 'error' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 4000)
}
</script>

<template>
    <!-- ドラフト復元確認ダイアログ（メインダイアログの外に配置） -->
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

    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-3xl md:max-w-4xl lg:max-w-5xl w-[95vw] md:w-[66vw] max-h-[90vh]">
            <DialogHeader>
                            <DialogTitle>{{ isEditMode ? (canEdit ? '予定編集' : '予定確認') : '新規予定作成' }}</DialogTitle>
                            <DialogDescription>{{ isEditMode ? (canEdit ? '部署内共有カレンダーの予定を編集します' : '部署内共有カレンダーの予定を確認します') : '部署内共有カレンダーに予定を追加します' }}</DialogDescription>
                        </DialogHeader>
            
            <div v-if="showDraftBanner" class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
              <Info class="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
              <div class="flex-1">
                <p class="text-sm text-blue-800">前回の下書きを復元しました</p>
              </div>
              <button @click="showDraftBanner = false" class="text-blue-600 hover:text-blue-800">
                <X class="h-4 w-4" />
              </button>
            </div>
            
                        <Tabs default-value="basic" class="w-full">
                            <TabsList class="grid w-full grid-cols-3">
                                <TabsTrigger value="basic">基本情報</TabsTrigger>
                                <TabsTrigger value="datetime">日時・参加者</TabsTrigger>
                                <TabsTrigger value="details">詳細</TabsTrigger>
                            </TabsList>
            
                            <ScrollArea class="max-h-[60vh] mt-4">
                                <div class="px-4">
                                    <TabsContent value="basic" class="space-y-4 min-h-[400px]">
                                        <div class="space-y-2">
                                            <Label for="title">タイトル / 件名 *</Label>
                                            <Input id="title" placeholder="例：部署ミーティング" v-model="form.title" :disabled="!canEdit" autofocus />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="category">ジャンル</Label>
                                            <Select v-model="form.category" :disabled="!canEdit">
                                                <SelectTrigger id="category">
                                                    <div class="flex items-center gap-2">
                                                        <div :class="['w-3 h-3 rounded-full', getCategoryColor(form.category)]"></div>
                                                        <SelectValue />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="会議">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-[#3b82f6]"></div>
                                                            <span>会議</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="業務">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-[#66bb6a]"></div>
                                                            <span>業務</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="来客">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-[#ffa726]"></div>
                                                            <span>来客</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="出張">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-[#9575cd]"></div>
                                                            <span>出張</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="休暇">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-[#f06292]"></div>
                                                            <span>休暇</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="その他">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-3 h-3 rounded-full bg-gray-500"></div>
                                                            <span>その他</span>
                                                        </div>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div> 
                                        <div class="space-y-2">
                                            <Label for="importance">重要度</Label>
                                            <Select v-model="form.importance" :disabled="!canEdit">
                                                <SelectTrigger id="importance">
                                                    <div class="flex items-center gap-2">
                                                        <AlertCircle :class="['h-4 w-4', getImportanceColor(form.importance)]" />
                                                        <SelectValue />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="重要">
                                                        <Badge class="bg-red-600 text-white">重要</Badge>
                                                    </SelectItem>
                                                    <SelectItem value="中">
                                                        <Badge class="bg-yellow-500 text-white">中</Badge>
                                                    </SelectItem>
                                                    <SelectItem value="低">
                                                        <Badge class="bg-gray-400 text-white">低</Badge>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="location">場所・会議室</Label>
                                            <Input id="location" placeholder="例：会議室A、オンライン（Zoom）" v-model="form.location" :disabled="!canEdit" />
                                        </div>
                                        <div v-if="isEditMode" class="space-y-2">
                                            <Label for="progress">進捗 ({{ form.event_progress }}%)</Label>
                                            <div class="relative">
                                                <div 
                                                    class="w-full h-2 rounded-lg overflow-hidden mb-2"
                                                    :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${form.event_progress}%, #e5e7eb ${form.event_progress}%, #e5e7eb 100%)` }"
                                                >
                                                </div>
                                                <input 
                                                    id="progress" 
                                                    type="range" 
                                                    min="0" 
                                                    max="100" 
                                                    v-model.number="form.event_progress" 
                                                    :disabled="!canEdit"
                                                    class="w-full h-2 bg-transparent rounded-lg appearance-none cursor-pointer slider absolute top-0"
                                                />
                                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                                    <span>0%</span>
                                                    <span>50%</span>
                                                    <span>100%</span>
                                                </div>
                                            </div>
                                        </div>

                                    </TabsContent>
            
                                    <TabsContent value="datetime" class="space-y-4 min-h-[400px]">
                                        <div class="flex items-center gap-6">
                                            <div class="flex items-center space-x-2">
                                                <Switch
                                                  id="allDay"
                                                  :checked="is_all_day"
                                                  v-model="form.is_all_day"
                                                  @update:checked="handleAllDayToggle"
                                                  :disabled="!canEdit"
                                                />
                                                <Label for="allDay" class="text-sm cursor-pointer">終日</Label>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <Switch id="recurring" v-model="form.recurrence.is_recurring" :disabled="!canEdit" />
                                                <Label for="recurring" class="text-sm cursor-pointer flex items-center gap-2">
                                                    <Repeat class="h-4 w-4" />
                                                    繰り返し
                                                </Label>
                                            </div>
                                        </div>
                                        <div class="flex flex-col lg:flex-row gap-4 lg:items-end">
                                            <div class="flex-1">
                                                <Label class="flex items-center gap-2 mb-2">
                                                    <CalendarIcon class="h-4 w-4" />
                                                    期間 *
                                                </Label>
                                                <VueDatePicker
                                                    v-model="date"
                                                    range
                                                    :time-config="{ enableTimePicker: false }"
                                                    placeholder="期間を選択"
                                                    :locale="ja"
                                                    :format="format"
                                                    auto-apply
                                                    teleport-center
                                                    :disabled="!canEdit"
                                                />
                                            </div>
                                            <div v-if="!form.is_all_day" class="flex flex-col sm:flex-row gap-4 flex-1">
                                                <div class="flex-1">
                                                    <Label class="mb-2 block">開始時刻</Label>
                                                    <Popover>
                                                        <PopoverTrigger as-child>
                                                            <Button variant="outline" class="w-full justify-start font-normal h-10" :disabled="!canEdit">
                                                                <Clock class="mr-2 h-4 w-4" />
                                                                {{ form.start_time || 'Select time' }}
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-auto p-0" v-if="canEdit">
                                                            <time-picker v-model="form.start_time" />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div class="flex-1">
                                                    <Label class="mb-2 block">終了時刻</Label>
                                                    <Popover>
                                                        <PopoverTrigger as-child>
                                                            <Button variant="outline" class="w-full justify-start font-normal h-10" :disabled="!canEdit">
                                                                <Clock class="mr-2 h-4 w-4" />
                                                                {{ form.end_time || 'Select time' }}
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-auto p-0" v-if="canEdit">
                                                            <time-picker v-model="form.end_time" />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="canEditParticipants" class="space-y-2">
                                            <Label class="flex items-center gap-2">
                                                <Users class="h-4 w-4" />
                                                参加者を選択
                                            </Label>
                                            <div class="flex gap-2 mb-2">
                                                <Button type="button" variant="outline" size="sm" @click="form.participants = [...teamMembers]">
                                                    全員選択
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" @click="form.participants = []">
                                                    選択解除
                                                </Button>
                                            </div>
                                            <div class="max-h-[200px] overflow-y-auto border rounded p-2 space-y-1">
                                                <label v-for="member in teamMembers" :key="member.id" class="flex items-center gap-2 p-1 hover:bg-gray-50 rounded cursor-pointer">
                                                    <input 
                                                        type="checkbox" 
                                                        :checked="form.participants.find(p => p.id === member.id) !== undefined"
                                                        @change="(e) => (e.target as HTMLInputElement).checked ? form.participants.push(member) : handleRemoveParticipant(member.id)"
                                                        class="h-4 w-4 text-blue-600 rounded border-gray-300"
                                                    />
                                                    <span class="text-xs">{{ member.name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <Label>{{ canEditParticipants ? '選択済み参加者' : '参加者' }}</Label>
                                            <div class="flex flex-wrap gap-2">
                                                <Badge v-if="form.participants.length === 0" variant="secondary" class="text-sm px-3 py-1">
                                                    選択なし（全員確認可能）
                                                </Badge>
                                                <Badge v-else-if="isAllUsers" variant="secondary" class="text-sm px-3 py-1">
                                                    全員
                                                </Badge>
                                                <template v-else>
                                                    <Badge v-for="participant in form.participants" :key="participant.id" variant="secondary" class="text-sm px-3 py-1 gap-2">
                                                        {{ participant.name }}
                                                        <button v-if="canEditParticipants" @click="handleRemoveParticipant(participant.id)" class="hover:text-red-600">
                                                            <X class="h-3 w-3" />
                                                        </button>
                                                    </Badge>
                                                </template>
                                            </div>
                                        </div>

                                        <div v-if="form.recurrence.is_recurring" class="space-y-4 pt-4">
                                            <div class="space-y-4 pl-6 border-l-2 border-gray-300 ml-3">
                                                <div class="space-y-2">
                                                    <Label for="recurrence_type">繰り返しパターン</Label>
                                                    <Select v-model="form.recurrence.recurrence_type" :disabled="!canEdit">
                                                        <SelectTrigger id="recurrence_type">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="daily">毎日</SelectItem>
                                                            <SelectItem value="weekly">毎週</SelectItem>
                                                            <SelectItem value="monthly">毎月</SelectItem>
                                                            <SelectItem value="yearly">毎年</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div v-if="form.recurrence.recurrence_type === 'weekly'" class="space-y-2">
                                                    <Label>曜日</Label>
                                                    <div class="flex gap-1">
                                                        <Button
                                                            v-for="day in weekdays"
                                                            :key="day.value"
                                                            type="button"
                                                            :variant="form.recurrence.by_day.includes(day.value) ? 'default' : 'outline'"
                                                            size="icon-sm"
                                                            @click="toggleByDay(day.value)"
                                                            :disabled="!canEdit"
                                                            class="rounded-full"
                                                        >
                                                            {{ day.label }}
                                                        </Button>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="recurrence_interval">繰り返し間隔</Label>
                                                    <Input id="recurrence_interval" type="number" min="1" v-model.number="form.recurrence.recurrence_interval" :disabled="!canEdit" class="w-24" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="recurrence_end_date">繰り返し終了日</Label>
                                                    <VueDatePicker
                                                        v-model="form.recurrence.end_date"
                                                        placeholder="終了日を選択"
                                                        :locale="ja"
                                                        auto-apply
                                                        teleport-center
                                                        :disabled="!canEdit"
                                                    />
                                                    <p class="text-xs text-gray-500">空白の場合は無期限で繰り返されます</p>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>
            
                                    <TabsContent value="details" class="space-y-4 min-h-[400px]">
                                        <div class="space-y-2">
                                            <Label for="description" class="flex items-center gap-2">
                                                <FileText class="h-4 w-4" />
                                                詳細・メモ
                                            </Label>
                                            <Textarea id="description" placeholder="予定の詳細、準備事項、議題など..." v-model="form.description" :disabled="!canEdit" rows="8" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="url" class="flex items-center gap-2">
                                                <LinkIcon class="h-4 w-4" />
                                                URL
                                            </Label>
                                            <Input id="url" type="url" placeholder="例：https://zoom.us/j/123456789" v-model="form.url" :disabled="!canEdit" />
                                            <p class="text-xs text-gray-500 mt-2">オンライン会議のURLや関連資料のリンクなど</p>
                                        </div>

                                        <div class="space-y-4 pt-4">
                                            <Label class="flex items-center gap-2">
                                                <Paperclip class="h-4 w-4" />
                                                添付ファイル
                                            </Label>
                                            <div v-if="canEdit">
                                                <Button variant="outline" size="sm" @click="triggerFileInput">
                                                    ファイルを選択
                                                </Button>
                                                <input type="file" multiple @change="handleFileChange" class="hidden" ref="fileInput" />
                                            </div>
                                            <div v-if="form.attachments.existing.length > 0" class="space-y-2">
                                                <p class="text-sm font-medium">既存のファイル:</p>
                                                <div v-for="file in form.attachments.existing" :key="file.attachment_id" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                                                    <span class="text-sm truncate">{{ file.file_name }}</span>
                                                    <Button v-if="canEdit" variant="ghost" size="sm" @click="handleRemoveExistingFile(file.attachment_id)">
                                                        <X class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div v-if="form.attachments.new_files.length > 0" class="space-y-2">
                                                <p class="text-sm font-medium">新しいファイル:</p>
                                                <div v-for="(file, index) in form.attachments.new_files" :key="index" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                                                    <span class="text-sm truncate">{{ file.name }}</span>
                                                    <Button v-if="canEdit" variant="ghost" size="sm" @click="handleRemoveNewFile(index)">
                                                        <X class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>
                                </div>
                            </ScrollArea>
                        </Tabs>
            
                        <DialogFooter>
                            <Button variant="outline" @click="handleClose">{{ canEdit ? 'キャンセル' : '閉じる' }}</Button>
                            <Button v-if="canEdit" variant="outline" @click="handleSave" class="gap-2" :disabled="form.processing">
                                <Save class="h-4 w-4" />
                                {{ form.processing ? '保存中...' : (isEditMode ? '保存' : '作成') }}
                            </Button>
                            <Button v-else variant="outline" @click="handleConfirm" class="gap-2">
                                <CheckCircle class="h-4 w-4" />
                                確認完了
                            </Button>
                        </DialogFooter>
        </DialogContent>
    </Dialog>
    
    <!-- メッセージ表示 -->
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[70] p-3 text-white rounded-lg shadow-lg',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium">{{ saveMessage }}</span>
        </div>
      </div>
    </Transition>
</template>

