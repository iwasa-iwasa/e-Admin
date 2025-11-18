<script setup lang="ts">
import { ref, h, onMounted, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import {
    Plus,
    Save,
    Trash2,
    GripVertical,
    CheckSquare,
    Circle,
    Type,
    Star,
    List,
    Clock,
    BarChart2,
    X,
    CheckCircle,
    Undo2,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { useToast } from "@/components/ui/toast/use-toast";

type QuestionType =
    | "single"
    | "multiple"
    | "text"
    | "textarea"
    | "rating"
    | "scale"
    | "dropdown"
    | "date";

interface Question {
    id: string;
    type: QuestionType;
    question: string;
    options: string[];
    required: boolean;
    scaleMin?: number;
    scaleMax?: number;
    scaleMinLabel?: string;
    scaleMaxLabel?: string;
}

interface QuestionTemplate {
    type: QuestionType;
    name: string;
    description: string;
    icon: any;
    defaultOptions?: string[];
    scaleMin?: number;
    scaleMax?: number;
}

const props = defineProps<{
    open: boolean;
    survey?: App.Models.Survey | null;
    teamMembers?: Array<{id: number, name: string}> | null;
}>();
const emit = defineEmits(["update:open", "open-dialog"]);

const title = ref("");
const description = ref("");
const deadline = ref("");
const questions = ref<Question[]>([]);
const selectedRespondents = ref<number[]>([]);
const showTemplateDialog = ref(false);
const draftSavedAt = ref<Date | null>(null);
const showDraftBanner = ref(false);
const saveMessage = ref('');
const messageType = ref<'success' | 'delete' | 'error'>('success');
const messageTimer = ref<number | null>(null);
const deletedDraft = ref<DraftData | null>(null);
const undoTimer = ref<number | null>(null);

const { toast } = useToast();

const DRAFT_KEY = "survey_draft";
const AUTO_SAVE_DELAY = 30000; // 30秒
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

const showMessage = (message: string, type: 'success' | 'delete' | 'error' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value)
  }
  
  saveMessage.value = message
  messageType.value = type
  
  messageTimer.value = setTimeout(() => {
    saveMessage.value = ''
  }, 4000)
}

const form = useForm({
    title: "",
    description: "",
    deadline: "",
    questions: [] as Array<{
        question: string;
        type: QuestionType;
        required: boolean;
        options: string[];
    }>,
    respondents: [] as number[],
    isDraft: false,
});

const questionTemplates: QuestionTemplate[] = [
    {
        type: "single",
        name: "単一選択（ラジオボタン）",
        description: "複数の選択肢から1つを選ぶ形式",
        icon: Circle,
        defaultOptions: ["選択肢1", "選択肢2", "選択肢3"],
    },
    {
        type: "multiple",
        name: "複数選択（チェックボックス）",
        description: "複数の選択肢から複数選べる形式",
        icon: CheckSquare,
        defaultOptions: ["選択肢1", "選択肢2", "選択肢3"],
    },
    {
        type: "text",
        name: "自由記述（短文）",
        description: "短いテキストを入力する形式",
        icon: Type,
        defaultOptions: [],
    },
    {
        type: "textarea",
        name: "自由記述（長文）",
        description: "長いテキストを入力する形式",
        icon: Type,
        defaultOptions: [],
    },
    {
        type: "rating",
        name: "評価スケール（星評価）",
        description: "星で満足度などを評価する形式",
        icon: Star,
        defaultOptions: [],
        scaleMin: 1,
        scaleMax: 5,
    },
    {
        type: "scale",
        name: "評価スケール（リッカート）",
        description: "段階的に評価する形式（例：5段階評価）",
        icon: BarChart2,
        defaultOptions: [],
        scaleMin: 1,
        scaleMax: 5,
    },
    {
        type: "dropdown",
        name: "ドロップダウン",
        description: "リストから1つを選ぶ形式",
        icon: List,
        defaultOptions: ["選択肢1", "選択肢2", "選択肢3"],
    },
    {
        type: "date",
        name: "日付/時刻",
        description: "特定の日時を入力する形式",
        icon: Clock,
        defaultOptions: [],
    },
];

const createQuestionFromTemplate = (template: QuestionTemplate) => {
    const newQuestion: Question = {
        id: String(Date.now()),
        type: template.type,
        question: "",
        options: template.defaultOptions || [],
        required: false,
        scaleMin: template.scaleMin,
        scaleMax: template.scaleMax,
        scaleMinLabel: template.scaleMin === 1 ? "とても悪い" : "",
        scaleMaxLabel: template.scaleMax === 5 ? "とても良い" : "",
    };
    questions.value.push(newQuestion);
    showTemplateDialog.value = false;
};

const removeQuestion = (id: string) => {
    if (questions.value.length > 0) {
        questions.value = questions.value.filter((q) => q.id !== id);
    }
};

const updateQuestion = (id: string, field: keyof Question, value: any) => {
    questions.value = questions.value.map((q) =>
        q.id === id ? { ...q, [field]: value } : q
    );
};

const addOption = (questionId: string) => {
    questions.value = questions.value.map((q) => {
        if (q.id === questionId) {
            return { ...q, options: [...q.options, ""] };
        }
        return q;
    });
};

const updateOption = (
    questionId: string,
    optionIndex: number,
    value: string
) => {
    questions.value = questions.value.map((q) => {
        if (q.id === questionId) {
            const newOptions = [...q.options];
            newOptions[optionIndex] = value;
            return { ...q, options: newOptions };
        }
        return q;
    });
};

const removeOption = (questionId: string, optionIndex: number) => {
    questions.value = questions.value.map((q) => {
        if (q.id === questionId && q.options.length > 2) {
            const newOptions = q.options.filter((_, i) => i !== optionIndex);
            return { ...q, options: newOptions };
        }
        return q;
    });
};

const handleSave = (isDraft: boolean = false) => {
    // クライアント側のバリデーション
    const errors: string[] = [];
    
    if (!title.value.trim()) {
        errors.push('アンケートのタイトルを入力してください');
    }
    if (!deadline.value) {
        errors.push('回答期限を設定してください');
    }
    if (questions.value.length === 0) {
        errors.push('最低1つの質問を追加してください');
    }

    for (const question of questions.value) {
        if (!question.question.trim()) {
            errors.push('すべての質問を入力してください');
            break;
        }
        if (["single", "multiple", "dropdown"].includes(question.type)) {
            const validOptions = question.options.filter((opt) => opt.trim());
            if (validOptions.length < 2) {
                errors.push('選択肢形式の質問には最低2つの選択肢が必要です');
                break;
            }
        }
    }
    
    if (errors.length > 0) {
        showMessage(errors.join('\n'), 'error');
        return;
    }

    // フォームデータを準備
    form.title = title.value;
    form.description = description.value;
    form.deadline = deadline.value;
    form.isDraft = isDraft;
    form.respondents = selectedRespondents.value;
    form.questions = questions.value.map((q) => ({
        question: q.question,
        type: q.type,
        required: q.required,
        options: q.options,
    }));

    // リクエストを送信
    if (isEditMode.value && props.survey) {
        // 編集モード: PUTリクエスト
        form.put(`/surveys/${props.survey.survey_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showMessage('アンケートを更新しました。', 'success');
                handleClose();
            },
            onError: (errors) => {
                const errorMessages: any[] = [];
                for (const [field, messages] of Object.entries(errors)) {
                    if (Array.isArray(messages)) {
                        errorMessages.push(...messages);
                    } else {
                        errorMessages.push(String(messages));
                    }
                }
                if (errorMessages.length > 0) {
                    showMessage(errorMessages.join('\n'), 'error');
                }
            },
        });
    } else {
        // 新規作成モード: POSTリクエスト
        form.post("/surveys", {
            preserveScroll: true,
            onSuccess: () => {
                // 本保存成功時は一時保存データを削除
                if (!isDraft) {
                    clearDraft();
                }
                if (isDraft) {
                    showMessage('アンケートが保存されました。', 'success')
                } else {
                    showMessage('アンケートを公開しました。', 'success')
                }
                handleClose();
            },
            onError: (errors) => {
                const errorMessages: any[] = [];
                for (const [field, messages] of Object.entries(errors)) {
                    if (Array.isArray(messages)) {
                        errorMessages.push(...messages);
                    } else {
                        errorMessages.push(String(messages));
                    }
                }
                if (errorMessages.length > 0) {
                    showMessage(errorMessages.join('\n'), 'error');
                }
            },
        });
    }
};

const handleClose = () => {
    // 自動保存タイマーをクリア
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = null;
    }

    title.value = "";
    description.value = "";
    deadline.value = "";
    questions.value = [];
    selectedRespondents.value = [];
    showTemplateDialog.value = false;
    draftSavedAt.value = null;
    showDraftBanner.value = false;
    form.reset();
    form.clearErrors();
    emit("update:open", false);
};

const handleCancel = () => {
    if (
        title.value ||
        description.value ||
        questions.value.some((q) => q.question || q.options.some((o) => o))
    ) {
        if (window.confirm("入力内容が失われますが、よろしいですか？")) {
            handleClose();
        }
    } else {
        handleClose();
    }
};

const getQuestionTypeLabel = (type: QuestionType) => {
    const template = questionTemplates.find((t) => t.type === type);
    return template?.name || type;
};

// 一時保存データの型定義
interface DraftData {
    title: string;
    description: string;
    deadline: string;
    questions: Question[];
    saved_at: string;
}

// 一時保存処理
const saveDraft = () => {
    try {
        // 入力内容が空の場合は保存しない
        if (
            !title.value.trim() &&
            !description.value.trim() &&
            questions.value.length === 0
        ) {
            showMessage('保存する内容がありません', 'error');
            return;
        }

        const draftData: DraftData = {
            title: title.value,
            description: description.value,
            deadline: deadline.value,
            questions: questions.value,
            saved_at: new Date().toISOString(),
        };

        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        draftSavedAt.value = new Date();
        showDraftBanner.value = true;

        showMessage('アンケートが保存されました。', 'success');
    } catch (error) {
        console.error("一時保存に失敗:", error);
        showMessage('一時保存に失敗しました。', 'error');
    }
};

// 一時保存データの復元
const loadDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_KEY);
        if (!saved) return null;

        const draftData: DraftData = JSON.parse(saved);

        title.value = draftData.title || "";
        description.value = draftData.description || "";
        deadline.value = draftData.deadline || "";
        questions.value = draftData.questions || [];
        draftSavedAt.value = new Date(draftData.saved_at);
        showDraftBanner.value = true;

        return draftData;
    } catch (error) {
        console.error("復元に失敗:", error);
        toast({
            title: "Error",
            description: "下書きの復元に失敗しました",
            variant: "destructive",
        });
        return null;
    }
};

// 一時保存データの削除
const clearDraft = () => {
    try {
        const saved = localStorage.getItem(DRAFT_KEY);
        if (saved) {
            deletedDraft.value = JSON.parse(saved);
        }
        
        localStorage.removeItem(DRAFT_KEY);
        draftSavedAt.value = null;
        showDraftBanner.value = false;
        showMessage('下書きを削除しました。', 'delete');
        
        // 10秒後に削除されたデータをクリア
        if (undoTimer.value) {
            clearTimeout(undoTimer.value);
        }
        undoTimer.value = setTimeout(() => {
            deletedDraft.value = null;
        }, 10000);
    } catch (error) {
        console.error("下書き削除に失敗:", error);
    }
};

// 下書き削除の取り消し
const undoDeleteDraft = () => {
    if (deletedDraft.value) {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(deletedDraft.value));
            draftSavedAt.value = new Date(deletedDraft.value.saved_at);
            showDraftBanner.value = true;
            
            title.value = deletedDraft.value.title || "";
            description.value = deletedDraft.value.description || "";
            deadline.value = deletedDraft.value.deadline || "";
            questions.value = deletedDraft.value.questions || [];
            
            deletedDraft.value = null;
            if (undoTimer.value) {
                clearTimeout(undoTimer.value);
                undoTimer.value = null;
            }
            
            showMessage('下書きを復元しました。', 'success');
            
            // ダイアログを開く
            emit("open-dialog");
        } catch (error) {
            console.error("下書き復元に失敗:", error);
            showMessage('下書きの復元に失敗しました。', 'error');
        }
    }
};

// 保存時刻の表示用フォーマット
const formatSavedTime = (date: Date | null): string => {
    if (!date) return "";

    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return "たった今";
    if (minutes < 60) return `${minutes}分前`;
    if (hours < 24) return `${hours}時間前`;
    if (days < 7) return `${days}日前`;

    return date.toLocaleDateString("ja-JP", {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// 自動保存のスケジュール
const scheduleAutoSave = () => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }

    autoSaveTimer = setTimeout(() => {
        // 入力内容がある場合のみ自動保存
        if (
            title.value.trim() ||
            description.value.trim() ||
            questions.value.length > 0
        ) {
            saveDraft();
        }
    }, AUTO_SAVE_DELAY);
};

// 編集モードの判定
const isEditMode = computed(() => !!props.survey);

// 回答者選択の処理
const toggleRespondent = (memberId: number) => {
    const index = selectedRespondents.value.indexOf(memberId);
    if (index > -1) {
        selectedRespondents.value.splice(index, 1);
    } else {
        selectedRespondents.value.push(memberId);
    }
};

const toggleAllRespondents = () => {
    if (!props.teamMembers) return;
    
    if (selectedRespondents.value.length === props.teamMembers.length) {
        selectedRespondents.value = [];
    } else {
        selectedRespondents.value = props.teamMembers.map(m => m.id);
    }
};

const isAllSelected = computed(() => {
    return props.teamMembers && selectedRespondents.value.length === props.teamMembers.length;
});

// 入力内容の変更を監視して自動保存をスケジュール（新規作成時のみ）
watch(
    [title, description, deadline, questions, selectedRespondents],
    () => {
        if (props.open && !isEditMode.value) {
            scheduleAutoSave();
        }
    },
    { deep: true }
);

// データベースの質問タイプをフロントエンドのタイプに変換
const mapQuestionTypeFromDb = (dbType: string): QuestionType => {
    const mapping: Record<string, QuestionType> = {
        single_choice: "single",
        multiple_choice: "multiple",
        text: "text",
        textarea: "textarea",
        rating: "rating",
        scale: "scale",
        dropdown: "dropdown",
        date: "date",
    };
    return mapping[dbType] || "text";
};



// 編集データの読み込み
const loadEditData = () => {
    if (!props.survey) return;

    title.value = props.survey.title || "";
    description.value = props.survey.description || "";
    deadline.value = props.survey.deadline
        ? new Date(props.survey.deadline).toISOString().split("T")[0]
        : "";

    // 質問データを変換
    questions.value =
        props.survey.questions?.map((q: any, index: number) => {
            const question: Question = {
                id: String(q.question_id || Date.now() + index),
                type: mapQuestionTypeFromDb(q.question_type),
                question: q.question_text || "",
                options:
                    q.options?.map((opt: any) => opt.option_text || "") || [],
                required: q.is_required || false,
                scaleMin: q.scaleMin,
                scaleMax: q.scaleMax,
                scaleMinLabel: q.scaleMinLabel,
                scaleMaxLabel: q.scaleMaxLabel,
            };
            return question;
        }) || [];
};

// ダイアログが開いた時の処理
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            // デフォルトで全員を選択
            if (props.teamMembers && selectedRespondents.value.length === 0) {
                selectedRespondents.value = props.teamMembers.map(m => m.id);
            }
            
            if (isEditMode.value) {
                // 編集モード: 既存データを読み込む
                loadEditData();
            } else {
                // 新規作成モード: 一時保存データをチェック
                const saved = localStorage.getItem(DRAFT_KEY);
                if (saved && !draftSavedAt.value) {
                    // 確認ダイアログを表示
                    const shouldRestore = window.confirm(
                        "前回の下書きが見つかりました。復元しますか？"
                    );

                    if (shouldRestore) {
                        loadDraft();
                    } else {
                        clearDraft();
                    }
                }
            }
        }
    }
);
</script>

<template>
    <Dialog :open="props.open" @update:open="handleClose">
        <DialogContent class="max-w-4xl max-h-[90vh]">
            <DialogHeader>
                <DialogTitle>{{
                    isEditMode ? "アンケートを編集" : "新しいアンケートを作成"
                }}</DialogTitle>
                <DialogDescription>{{
                    isEditMode
                        ? "アンケートの内容を編集します"
                        : "総務部 共同管理のアンケートを作成します"
                }}</DialogDescription>
            </DialogHeader>

            <!-- 一時保存バナー（新規作成時のみ） -->
            <div
                v-if="!isEditMode && showDraftBanner && draftSavedAt"
                class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md flex items-center justify-between"
            >
                <div class="flex items-center gap-2 text-sm text-blue-700">
                    <Clock class="h-4 w-4" />
                    <span>最終保存: {{ formatSavedTime(draftSavedAt) }}</span>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="clearDraft"
                    class="h-6 px-2 text-blue-700 hover:text-blue-900"
                >
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <ScrollArea class="max-h-[60vh] mt-4">
                <div class="space-y-6 pr-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>基本情報</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="title">アンケートタイトル *</Label>
                                <Input
                                    id="title"
                                    placeholder="例：2025年度 忘年会の候補日アンケート"
                                    v-model="title"
                                    :class="{
                                        'border-red-500': form.errors.title,
                                    }"
                                />
                                <p
                                    v-if="form.errors.title"
                                    class="text-sm text-red-500"
                                >
                                    {{ form.errors.title }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="description">説明</Label>
                                <Textarea
                                    id="description"
                                    placeholder="アンケートの目的や注意事項を入力..."
                                    v-model="description"
                                    class="min-h-[80px]"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="deadline">回答期限 *</Label>
                                <Input
                                    id="deadline"
                                    type="date"
                                    v-model="deadline"
                                    :class="{
                                        'border-red-500': form.errors.deadline,
                                    }"
                                />
                                <p
                                    v-if="form.errors.deadline"
                                    class="text-sm text-red-500"
                                >
                                    {{ form.errors.deadline }}
                                </p>
                            </div>
                            <div v-if="teamMembers" class="space-y-2">
                                <Label>回答者選択</Label>
                                <div class="border rounded-md p-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium">選択された回答者: {{ selectedRespondents.length }}/{{ teamMembers.length }}名</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="toggleAllRespondents"
                                            class="text-xs"
                                        >
                                            {{ isAllSelected ? '全員解除' : '全員選択' }}
                                        </Button>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <Button
                                            v-for="member in teamMembers"
                                            :key="member.id"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="toggleRespondent(member.id)"
                                            :class="[
                                                'text-xs',
                                                selectedRespondents.includes(member.id)
                                                    ? 'bg-blue-100 text-blue-700 border-blue-300'
                                                    : 'hover:bg-gray-50'
                                            ]"
                                        >
                                            {{ member.name }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="space-y-4">
                        <Card
                            v-if="questions.length === 0"
                            class="border-dashed border-2"
                        >
                            <CardContent class="py-12 text-center">
                                <div
                                    class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4"
                                >
                                    <Plus class="h-8 w-8 text-blue-600" />
                                </div>
                                <h3 class="mb-2">質問を追加しましょう</h3>
                                <p class="text-sm text-gray-500 mb-6">
                                    下のボタンから質問形式を選択して追加できます
                                </p>
                                <Button
                                    @click="showTemplateDialog = true"
                                    class="gap-2"
                                >
                                    <Plus class="h-4 w-4" />
                                    新しい質問を追加
                                </Button>
                            </CardContent>
                        </Card>
                        <template v-else>
                            <Card
                                v-for="(question, index) in questions"
                                :key="question.id"
                            >
                                <CardHeader>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <CardTitle
                                            class="text-base flex items-center gap-2"
                                        >
                                            <GripVertical
                                                class="h-4 w-4 text-gray-400"
                                            />
                                            質問 {{ index + 1 }}
                                        </CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="removeQuestion(question.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="space-y-2">
                                        <Label>質問文 *</Label>
                                        <Input
                                            placeholder="質問を入力してください"
                                            :model-value="question.question"
                                            @update:model-value="
                                                updateQuestion(
                                                    question.id,
                                                    'question',
                                                    $event
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>回答形式</Label>
                                        <div
                                            class="p-3 bg-gray-50 rounded-md border"
                                        >
                                            <p class="text-sm">
                                                {{
                                                    getQuestionTypeLabel(
                                                        question.type
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="question.type === 'single'"
                                        class="space-y-2"
                                    >
                                        <Label>選択肢</Label>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(
                                                    option, optionIndex
                                                ) in question.options"
                                                :key="optionIndex"
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="w-4 h-4 rounded-full border-2 border-gray-400 flex-shrink-0"
                                                />
                                                <Input
                                                    :placeholder="`選択肢 ${
                                                        optionIndex + 1
                                                    }`"
                                                    :model-value="option"
                                                    @update:model-value="
                                                        updateOption(
                                                            question.id,
                                                            optionIndex,
                                                            String($event)
                                                        )
                                                    "
                                                    class="flex-1"
                                                />
                                                <Button
                                                    v-if="
                                                        question.options
                                                            .length > 2
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removeOption(
                                                            question.id,
                                                            optionIndex
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="addOption(question.id)"
                                            class="gap-2"
                                        >
                                            <Plus class="h-4 w-4" />
                                            選択肢を追加
                                        </Button>
                                    </div>
                                    <div
                                        v-if="question.type === 'multiple'"
                                        class="space-y-2"
                                    >
                                        <Label>選択肢</Label>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(
                                                    option, optionIndex
                                                ) in question.options"
                                                :key="optionIndex"
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="w-4 h-4 rounded border-2 border-gray-400 flex-shrink-0"
                                                ></div>
                                                <Input
                                                    :placeholder="`選択肢 ${
                                                        optionIndex + 1
                                                    }`"
                                                    :model-value="option"
                                                    @update:model-value="
                                                        updateOption(
                                                            question.id,
                                                            optionIndex,
                                                            String($event)
                                                        )
                                                    "
                                                    class="flex-1"
                                                />
                                                <Button
                                                    v-if="
                                                        question.options
                                                            .length > 2
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removeOption(
                                                            question.id,
                                                            optionIndex
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="addOption(question.id)"
                                            class="gap-2"
                                        >
                                            <Plus class="h-4 w-4" />
                                            選択肢を追加
                                        </Button>
                                    </div>
                                    <div
                                        v-if="question.type === 'dropdown'"
                                        class="space-y-2"
                                    >
                                        <Label>選択肢</Label>
                                        <div class="space-y-2">
                                            <div
                                                v-for="(
                                                    option, optionIndex
                                                ) in question.options"
                                                :key="optionIndex"
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm text-gray-500 w-6"
                                                    >{{
                                                        optionIndex + 1
                                                    }}.</span
                                                >
                                                <Input
                                                    :placeholder="`選択肢 ${
                                                        optionIndex + 1
                                                    }`"
                                                    :model-value="option"
                                                    @update:model-value="
                                                        updateOption(
                                                            question.id,
                                                            optionIndex,
                                                            String($event)
                                                        )
                                                    "
                                                    class="flex-1"
                                                />
                                                <Button
                                                    v-if="
                                                        question.options
                                                            .length > 2
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    @click="
                                                        removeOption(
                                                            question.id,
                                                            optionIndex
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="addOption(question.id)"
                                            class="gap-2"
                                        >
                                            <Plus class="h-4 w-4" />
                                            選択肢を追加
                                        </Button>
                                    </div>
                                    <div
                                        v-if="question.type === 'text'"
                                        class="p-4 bg-gray-50 rounded-md border"
                                    >
                                        <Input
                                            placeholder="回答者がここに短文を入力します"
                                            disabled
                                        />
                                    </div>
                                    <div
                                        v-if="question.type === 'textarea'"
                                        class="p-4 bg-gray-50 rounded-md border"
                                    >
                                        <Textarea
                                            placeholder="回答者がここに長文を入力します"
                                            disabled
                                            class="min-h-[100px]"
                                        />
                                    </div>
                                    <div
                                        v-if="question.type === 'rating'"
                                        class="space-y-3"
                                    >
                                        <div
                                            class="p-4 bg-gray-50 rounded-md border"
                                        >
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <Star
                                                    v-for="star in Array.from(
                                                        {
                                                            length:
                                                                question.scaleMax ||
                                                                5,
                                                        },
                                                        (_, i) => i + 1
                                                    )"
                                                    :key="star"
                                                    class="h-8 w-8 text-gray-300 fill-gray-200"
                                                />
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="space-y-2">
                                                <Label>最小値</Label>
                                                <Input
                                                    type="number"
                                                    :model-value="
                                                        question.scaleMin || 1
                                                    "
                                                    @update:model-value="
                                                        updateQuestion(
                                                            question.id,
                                                            'scaleMin',
                                                            parseInt(String($event))
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label>最大値</Label>
                                                <Input
                                                    type="number"
                                                    :model-value="
                                                        question.scaleMax || 5
                                                    "
                                                    @update:model-value="
                                                        updateQuestion(
                                                            question.id,
                                                            'scaleMax',
                                                            parseInt(String($event))
                                                        )
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="question.type === 'scale'"
                                        class="space-y-3"
                                    >
                                        <div class="space-y-2">
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="space-y-2">
                                                    <Label>最小値</Label>
                                                    <Input
                                                        type="number"
                                                        :model-value="
                                                            question.scaleMin ||
                                                            1
                                                        "
                                                        @update:model-value="
                                                            updateQuestion(
                                                                question.id,
                                                                'scaleMin',
                                                                parseInt(String($event))
                                                            )
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label>最大値</Label>
                                                    <Input
                                                        type="number"
                                                        :model-value="
                                                            question.scaleMax ||
                                                            5
                                                        "
                                                        @update:model-value="
                                                            updateQuestion(
                                                                question.id,
                                                                'scaleMax',
                                                                parseInt(String($event))
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="space-y-2">
                                                    <Label
                                                        >最小値ラベル（任意）</Label
                                                    >
                                                    <Input
                                                        placeholder="例：とても悪い"
                                                        :model-value="
                                                            question.scaleMinLabel ||
                                                            ''
                                                        "
                                                        @update:model-value="
                                                            updateQuestion(
                                                                question.id,
                                                                'scaleMinLabel',
                                                                $event
                                                            )
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label
                                                        >最大値ラベル（任意）</Label
                                                    >
                                                    <Input
                                                        placeholder="例：とても良い"
                                                        :model-value="
                                                            question.scaleMaxLabel ||
                                                            ''
                                                        "
                                                        @update:model-value="
                                                            updateQuestion(
                                                                question.id,
                                                                'scaleMaxLabel',
                                                                $event
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="p-4 bg-gray-50 rounded-md border"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    class="text-sm text-gray-600"
                                                    >{{
                                                        question.scaleMinLabel ||
                                                        question.scaleMin ||
                                                        1
                                                    }}</span
                                                >
                                                <div class="flex gap-2">
                                                    <div
                                                        v-for="value in Array.from(
                                                            {
                                                                length:
                                                                    (question.scaleMax ||
                                                                        5) -
                                                                    (question.scaleMin ||
                                                                        1) +
                                                                    1,
                                                            },
                                                            (_, i) =>
                                                                i +
                                                                (question.scaleMin ||
                                                                    1)
                                                        )"
                                                        :key="value"
                                                        class="w-10 h-10 rounded-full border-2 border-gray-300 flex items-center justify-center text-sm"
                                                    >
                                                        {{ value }}
                                                    </div>
                                                </div>
                                                <span
                                                    class="text-sm text-gray-600"
                                                    >{{
                                                        question.scaleMaxLabel ||
                                                        question.scaleMax ||
                                                        5
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="question.type === 'date'"
                                        class="p-4 bg-gray-50 rounded-md border"
                                    >
                                        <Input type="datetime-local" disabled />
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            :id="`required-${question.id}`"
                                            :checked="question.required"
                                            @update:checked="
                                                updateQuestion(
                                                    question.id,
                                                    'required',
                                                    $event
                                                )
                                            "
                                        />
                                        <Label
                                            :for="`required-${question.id}`"
                                            class="text-sm cursor-pointer"
                                            >必須項目にする</Label
                                        >
                                    </div>
                                </CardContent>
                            </Card>
                            <Button
                                variant="outline"
                                @click="showTemplateDialog = true"
                                class="w-full gap-2"
                            >
                                <Plus class="h-4 w-4" />
                                新しい質問を追加
                            </Button>
                        </template>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button
                    variant="outline"
                    @click="handleCancel"
                    :disabled="form.processing"
                    >キャンセル</Button
                >
                <div class="flex gap-2">
                    <Button
                        v-if="!isEditMode"
                        variant="outline"
                        @click="saveDraft"
                        :disabled="form.processing"
                        >一時保存</Button
                    >
                    <Button
                        @click="handleSave(false)"
                        variant="outline"
                        class="gap-2"
                        :disabled="form.processing"
                    >
                        <Save class="h-4 w-4" />
                        {{
                            form.processing
                                ? "保存中..."
                                : isEditMode
                                ? "更新"
                                : "作成"
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showTemplateDialog">
        <DialogContent class="max-w-3xl max-h-[80vh] overflow-y-auto z-[60]">
            <DialogHeader>
                <DialogTitle>質問形式を選択</DialogTitle>
                <DialogDescription
                    >作成したい質問の形式を選択してください</DialogDescription
                >
            </DialogHeader>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <Card
                    v-for="template in questionTemplates"
                    :key="template.type"
                    class="cursor-pointer hover:shadow-md hover:border-blue-500 transition-all"
                    @click="createQuestionFromTemplate(template)"
                >
                    <CardContent class="p-6">
                        <div class="flex items-start gap-4">
                            <div
                                class="p-3 bg-blue-50 rounded-lg text-blue-600"
                            >
                                <component
                                    :is="template.icon"
                                    class="h-6 w-6"
                                />
                            </div>
                            <div class="flex-1">
                                <h3 class="mb-2">{{ template.name }}</h3>
                                <p class="text-sm text-gray-600">
                                    {{ template.description }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
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
        :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[70] p-3 text-white rounded-lg shadow-lg pointer-events-auto',
          messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
      >
        <div class="flex items-center gap-2">
          <CheckCircle class="h-5 w-5" />
          <span class="font-medium whitespace-pre-line">{{ saveMessage }}</span>
          <Button 
            v-if="messageType === 'delete' && deletedDraft"
            variant="ghost"
            size="sm"
            @click="undoDeleteDraft"
            class="ml-2 text-white hover:bg-white/20"
          >
            元に戻す
          </Button>
        </div>
      </div>
    </Transition>
</template>
