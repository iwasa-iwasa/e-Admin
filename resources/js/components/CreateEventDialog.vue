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
import { useToast } from "@/components/ui/toast/use-toast";
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
const { toast } = useToast()

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

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        form.attachments.new_files.push(...Array.from(target.files));
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
  const transformData = (data: typeof form) => ({
    ...data,
    participants: data.participants.map(p => p.id)
  });

  const options = {
    onSuccess: () => {
      toast({ title: "Success", description: `予定を${isEditMode.value ? '更新' : '作成'}しました` })
      handleClose()
    },
    onError: (errors: any) => {
      console.log(errors)
      // Pick the first error and show it.
      const firstError = Object.values(errors)[0]
      toast({
            title: "Error",
            description: firstError,
            variant: "destructive",
        });
    }
  };

  if (isEditMode.value && props.event) {
    form.transform(transformData).put(route('events.update', { event: props.event.event_id }), options);
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
        case "高": return "text-red-600";
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
                                            <Label for="importance">重要度</Label>
                                            <Select v-model="form.importance">
                                                <SelectTrigger id="importance">
                                                    <div class="flex items-center gap-2">
                                                        <AlertCircle :class="['h-4 w-4', getImportanceColor(form.importance)]" />
                                                        <SelectValue />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="高">高</SelectItem>
                                                    <SelectItem value="中">中</SelectItem>
                                                    <SelectItem value="低">低</SelectItem>
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
                            <Button @click="handleSave" class="gap-2 bg-gray-900 text-white hover:bg-gray-800">
                                <Save class="h-4 w-4" />
                                {{ isEditMode ? '保存' : '作成' }}
                            </Button>
                        </DialogFooter>        </DialogContent>
    </Dialog>
</template>
