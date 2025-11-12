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
}>();
const emit = defineEmits(["update:open"]);

const page = usePage()
const { toast } = useToast()

const teamMembers = computed(() => page.props.teamMembers as App.Models.User[])
// Current authenticated user id from Inertia page props
const currentUserId = computed(() => (page.props as any).auth?.user?.id ?? null)

const form = useForm({
  title: '',
  is_all_day: false,
  date_range: [new Date(), new Date()],
  start_time: '09:00',
  end_time: '10:00',
  participants: [] as App.Models.User[],
  location: '',
  description: '',
  category: '会議',
  importance: '中',
})
const is_all_day = ref(form.is_all_day);
const date = ref(form.date_range);
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

const handleSave = () => {
  form.transform(data => ({
    ...data,
    participants: data.participants.map(p => p.id)
  })).post(route('events.store'), {
    onSuccess: () => {
      toast({ title: "Success", description: "予定を作成しました" })
      handleClose()
    },
    onError: (errors) => {
      console.log(errors)
      // Pick the first error and show it.
      const firstError = Object.values(errors)[0]
      toast({
            title: "Error",
            description: firstError,
            variant: "destructive",
        });
    }
  })
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
        default: return "bg-gray-500";
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-3xl max-h-[90vh]">
            <DialogHeader>
                <DialogTitle>新規予定作成</DialogTitle>
                <DialogDescription>部署内共有カレンダーに予定を追加します</DialogDescription>
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
                        </TabsContent>

                        <TabsContent value="details" class="space-y-4 min-h-[400px]">
                            <div class="space-y-2">
                                <Label for="description" class="flex items-center gap-2">
                                    <FileText class="h-4 w-4" />
                                    詳細・メモ
                                </Label>
                                <Textarea id="description" placeholder="予定の詳細、準備事項、議題など..." v-model="form.description" rows="8" />
                            </div>
                        </TabsContent>
                    </div>
                </ScrollArea>
            </Tabs>

            <DialogFooter>
                <Button variant="outline" @click="handleClose">キャンセル</Button>
                <Button @click="handleSave" class="gap-2 bg-gray-900 text-white hover:bg-gray-800">
                    <Save class="h-4 w-4" />
                    作成
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
