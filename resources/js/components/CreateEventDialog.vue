<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
}>();
const emit = defineEmits(["update:open"]);

const isEditMode = computed(() => !!props.event);
const fileInput = ref<HTMLInputElement | null>(null);

const page = usePage()
const saveMessage = ref('')
const messageType = ref<'success' | 'error'>('success')
const messageTimer = ref<number | null>(null)

const teamMembers = computed(() => page.props.teamMembers as App.Models.User[])
// Current authenticated user id from Inertia page props
const currentUserId = computed(() => (page.props as any).auth?.user?.id ?? null)

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
  progress: 0,

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
      form.progress = eventData.progress || 0;

      
      if (eventData.recurrence) {
        form.recurrence.is_recurring = true;
        form.recurrence.recurrence_type = eventData.recurrence.recurrence_type;
        form.recurrence.recurrence_interval = eventData.recurrence.recurrence_interval;
        form.recurrence.by_day = eventData.recurrence.by_day || [];
        form.recurrence.by_set_pos = eventData.recurrence.by_set_pos;
        form.recurrence.end_date = eventData.recurrence.end_date ? new Date(eventData.recurrence.end_date) : null;
      } else {
        form.reset('recurrence');
      }

      form.attachments.existing = eventData.attachments || [];
      form.attachments.new_files = [];
      form.attachments.removed_ids = [];

      date.value = form.date_range;
      is_all_day.value = form.is_all_day;
    } else {
      form.reset();
      const now = new Date();
      date.value = [now, now];
      form.date_range = [now, now];
      is_all_day.value = false;
    }
  }
}, { immediate: true });


watch(date, (newDate) => {
    if (newDate && newDate.length === 2) {
        form.date_range = newDate;
    }
});

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

const handleAddParticipant = (memberId: unknown) => {
    if (memberId === null || memberId === undefined) return
    const id = Number(memberId as any)
    if (Number.isNaN(id)) return
    const member = teamMembers.value.find((m) => m.id === id)
    if (member && !form.participants.find((p) => p.id === member.id)) {
        form.participants.push(member)
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

const handleSave = () => {
  const transformData = (data: typeof form) => {
    const transformed: Record<string, any> = {
        ...data,
        participants: data.participants.map(p => p.id)
    };
    if (isEditMode.value) {
        transformed._method = 'put';
    }
    return transformed;
  };

  const options = {
    onSuccess: () => {
      handleClose()
      // ダイアログを閉じた後にメッセージを表示
      setTimeout(() => {
        showMessage(`予定を${isEditMode.value ? '更新' : '作成'}しました`, 'success')
      }, 100)
    },
    onError: (errors: any) => {
      console.log(errors)
      // Pick the first error and show it.
      const firstError = Object.values(errors)[0]
      showMessage(firstError || '保存に失敗しました', 'error')
    }
  };

  if (isEditMode.value && props.event) {
    form.transform(transformData).post(route('events.update', { event: props.event.event_id }), options);
  } else {
    form.transform(transformData).post(route('events.store'), options);
  }
}

const handleClose = () => {
    form.reset()
    date.value = [new Date(), new Date()];
    emit("update:open", false);
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
        case "会議": return "bg-purple-500";
        case "MTG": return "bg-green-500";
        case "期限": return "bg-orange-500";
        case "重要": return "bg-red-500";
        case "有給": return "bg-teal-500";
        case "業務": return "bg-blue-500";
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
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-3xl max-h-[90vh]">
            <DialogHeader>
                            <DialogTitle>{{ isEditMode ? '予定編集' : '新規予定作成' }}</DialogTitle>
                            <DialogDescription>{{ isEditMode ? '部署内共有カレンダーの予定を編集します' : '部署内共有カレンダーに予定を追加します' }}</DialogDescription>
                        </DialogHeader>
            
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
                                            <Input id="title" placeholder="例：部署ミーティング" v-model="form.title" autofocus />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="category">ジャンル</Label>
                                            <Select v-model="form.category">
                                                <SelectTrigger id="category">
                                                    <div class="flex items-center gap-2">
                                                        <div :class="['w-3 h-3 rounded-full', getCategoryColor(form.category)]"></div>
                                                        <SelectValue />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="会議">会議</SelectItem>
                                                    <SelectItem value="MTG">MTG</SelectItem>
                                                    <SelectItem value="期限">期限</SelectItem>
                                                    <SelectItem value="重要">重要</SelectItem>
                                                    <SelectItem value="有給">有給</SelectItem>
                                                    <SelectItem value="業務">業務</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="importance">優先度</Label>
                                            <Select v-model="form.importance">
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
                                            <Label for="location" class="flex items-center gap-2">
                                                <MapPin class="h-4 w-4" />
                                                場所・会議室
                                            </Label>
                                            <Input id="location" placeholder="例：会議室A、オンライン（Zoom）" v-model="form.location" />
                                        </div>
                                        <div v-if="isEditMode" class="space-y-2">
                                            <Label for="progress">進捗 ({{ form.progress }}%)</Label>
                                            <div class="relative">
                                                <div 
                                                    class="w-full h-2 rounded-lg overflow-hidden mb-2"
                                                    :style="{ background: `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${form.progress}%, #e5e7eb ${form.progress}%, #e5e7eb 100%)` }"
                                                >
                                                </div>
                                                <input 
                                                    id="progress" 
                                                    type="range" 
                                                    min="0" 
                                                    max="100" 
                                                    v-model.number="form.progress" 
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
                                        <div class="flex items-center space-x-2">
                                            <Switch
                                              id="allDay"
                                              :checked="is_all_day"
                                              v-model="form.is_all_day"
                                              @update:checked="handleAllDayToggle"
                                            />
                                            <Label for="allDay" class="text-sm cursor-pointer">終日</Label>
                                        </div>
                                        <div class="space-y-2">
                                            <Label class="flex items-center gap-2">
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
                                            />
                                        </div>
                                        <div v-if="!form.is_all_day" class="flex flex-col sm:flex-row gap-4">
                                            <div class="space-y-2 flex-1">
                                                <Label>開始時刻</Label>
                                                <Popover>
                                                    <PopoverTrigger as-child>
                                                        <Button variant="outline" class="w-full justify-start font-normal">
                                                            <Clock class="mr-2 h-4 w-4" />
                                                            {{ form.start_time || 'Select time' }}
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent class="w-auto p-0">
                                                        <time-picker v-model="form.start_time" />
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                            <div class="space-y-2 flex-1">
                                                <Label>終了時刻</Label>
                                                <Popover>
                                                    <PopoverTrigger as-child>
                                                        <Button variant="outline" class="w-full justify-start font-normal">
                                                            <Clock class="mr-2 h-4 w-4" />
                                                            {{ form.end_time || 'Select time' }}
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent class="w-auto p-0">
                                                        <time-picker v-model="form.end_time" />
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="addParticipant" class="flex items-center gap-2">
                                                <Users class="h-4 w-4" />
                                                参加者を追加
                                            </Label>
                                            <Select @update:model-value="handleAddParticipant">
                                                <SelectTrigger id="addParticipant">
                                                    <SelectValue placeholder="メンバーを選択..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                        <SelectItem v-for="member in teamMembers.filter(m => m.id !== currentUserId && !form.participants.find(p => p.id === m.id))" :key="member.id" :value="member.id">
                                                            {{ member.name }}
                                                        </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div v-if="form.participants.length > 0" class="space-y-2">
                                            <Label>選択済み参加者</Label>
                                            <div class="flex flex-wrap gap-2">
                                                <Badge v-for="participant in form.participants" :key="participant.id" variant="secondary" class="text-sm px-3 py-1 gap-2">
                                                    {{ participant.name }}
                                                    <button @click="handleRemoveParticipant(participant.id)" class="hover:text-red-600">
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </Badge>
                                            </div>
                                        </div>

                                        <div class="space-y-4 pt-4">
                                            <div class="flex items-center space-x-2">
                                                <Switch id="recurring" v-model="form.recurrence.is_recurring" />
                                                <Label for="recurring" class="text-sm cursor-pointer flex items-center gap-2">
                                                    <Repeat class="h-4 w-4" />
                                                    この予定を繰り返す
                                                </Label>
                                            </div>
                                            <div v-if="form.recurrence.is_recurring" class="space-y-4 pl-6 border-l-2 border-gray-200 ml-3">
                                                <div class="space-y-2">
                                                    <Label for="recurrence_type">繰り返しパターン</Label>
                                                    <Select v-model="form.recurrence.recurrence_type">
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
                                                            class="rounded-full"
                                                        >
                                                            {{ day.label }}
                                                        </Button>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="recurrence_interval">繰り返し間隔</Label>
                                                    <Input id="recurrence_interval" type="number" min="1" v-model.number="form.recurrence.recurrence_interval" class="w-24" />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label for="recurrence_end_date">繰り返し終了日</Label>
                                                    <VueDatePicker
                                                        v-model="form.recurrence.end_date"
                                                        placeholder="終了日を選択"
                                                        :locale="ja"
                                                        auto-apply
                                                        teleport-center
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
                                            <Textarea id="description" placeholder="予定の詳細、準備事項、議題など..." v-model="form.description" rows="8" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="url" class="flex items-center gap-2">
                                                <LinkIcon class="h-4 w-4" />
                                                URL
                                            </Label>
                                            <Input id="url" type="url" placeholder="例：https://zoom.us/j/123456789" v-model="form.url" />
                                            <p class="text-xs text-gray-500 mt-2">オンライン会議のURLや関連資料のリンクなど</p>
                                        </div>

                                        <div class="space-y-4 pt-4">
                                            <Label class="flex items-center gap-2">
                                                <Paperclip class="h-4 w-4" />
                                                添付ファイル
                                            </Label>
                                            <div>
                                                <Button variant="outline" size="sm" @click="triggerFileInput">
                                                    ファイルを選択
                                                </Button>
                                                <input type="file" multiple @change="handleFileChange" class="hidden" ref="fileInput" />
                                            </div>
                                            <div v-if="form.attachments.existing.length > 0" class="space-y-2">
                                                <p class="text-sm font-medium">既存のファイル:</p>
                                                <div v-for="file in form.attachments.existing" :key="file.attachment_id" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                                                    <span class="text-sm truncate">{{ file.file_name }}</span>
                                                    <Button variant="ghost" size="sm" @click="handleRemoveExistingFile(file.attachment_id)">
                                                        <X class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <div v-if="form.attachments.new_files.length > 0" class="space-y-2">
                                                <p class="text-sm font-medium">新しいファイル:</p>
                                                <div v-for="(file, index) in form.attachments.new_files" :key="index" class="flex items-center justify-between p-2 bg-gray-50 rounded-md">
                                                    <span class="text-sm truncate">{{ file.name }}</span>
                                                    <Button variant="ghost" size="sm" @click="handleRemoveNewFile(index)">
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
                            <Button variant="outline" @click="handleClose">キャンセル</Button>
                            <Button variant="outline" @click="handleSave" class="gap-2">
                                <Save class="h-4 w-4" />
                                {{ isEditMode ? '保存' : '作成' }}
                            </Button>
                        </DialogFooter>        </DialogContent>
    
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
    </Dialog>
</template>
