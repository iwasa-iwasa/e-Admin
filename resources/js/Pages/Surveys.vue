<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, nextTick, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import {
    BarChart3,
    Plus,
    Search,
    Filter,
    Clock,
    CheckCircle2,
    AlertCircle,
    Users,
    ArrowLeft,
    Calendar as CalendarIcon,
    Edit,
    Trash2,
    CheckCircle,
    Undo2,
    HelpCircle,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateSurveyDialog from "@/components/CreateSurveyDialog.vue";

defineOptions({
    layout: AuthenticatedLayout,
});

// App.Models.Surveyが不完全なため、手動で定義
interface SurveyModel {
    survey_id: number;
    title: string;
    description: string | null;
    created_by: number;
    deadline_date: string | null;
    deadline_time: string | null;
    is_active: boolean;
    is_deleted: boolean;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    creator?: {
        id: number;
        name: string;
    };
    questions: any[]; // 詳細は必要に応じて定義
    responses: any[];
}

interface SurveyWithResponse extends SurveyModel {
    has_responded?: boolean;
    respondent_names?: string[];
    unanswered_names?: string[];
    categories?: string[];
    category?: string;
}

const props = defineProps<{
    surveys: SurveyWithResponse[];
    editSurvey?: SurveyWithResponse;
    teamMembers?: Array<{id: number, name: string}>;
    errors?: Record<string, string>;
    auth?: { user: { id: number; name: string } };
    ziggy?: any;
    flash?: any;
    totalUsers?: number;
    unansweredSurveysCount?: number;
}>();

// リアクティブ変数
const searchQuery = ref("");
const categoryFilter = ref("all");
const activeTab = ref("active");
const isCreateSurveyDialogOpen = ref(false);
const showCreateDialog = ref(false);
const editingSurvey = ref<SurveyWithResponse | null>(null);
const surveyToDelete = ref<SurveyWithResponse | null>(null);
const saveMessage = ref('');
const messageType = ref<'success' | 'delete'>('success');
const messageTimer = ref<number | null>(null);
const lastDeletedSurvey = ref<SurveyWithResponse | null>(null);
const scrollAreaRef = ref<any>(null);
const isHelpOpen = ref(false);

// 全タグを取得
const allCategories = computed(() => {
    const categories = new Set<string>()
    props.surveys.forEach(survey => {
        if (survey.categories && Array.isArray(survey.categories)) {
            survey.categories.forEach((cat: string) => categories.add(cat))
        } else if (survey.category) {
            categories.add(survey.category)
        }
    })
    return Array.from(categories).sort()
});

// メッセージ表示関数
const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    saveMessage.value = message;
    messageType.value = type;
    messageTimer.value = window.setTimeout(() => { // window.setTimeoutに変更
        saveMessage.value = '';
        lastDeletedSurvey.value = null;
    }, 4000);
};


// フィルタリングされたアンケート一覧
const filteredSurveys = computed(() => {
    return props.surveys.filter((survey) => {
        const matchesSearch =
            survey.title
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            (survey.description &&
                survey.description
                    .toLowerCase()
                    .includes(searchQuery.value.toLowerCase())) ||
            (survey.creator &&
                survey.creator.name
                    .toLowerCase()
                    .includes(searchQuery.value.toLowerCase()));

        const matchesCategory = categoryFilter.value === 'all' || (() => {
            if (survey.categories && Array.isArray(survey.categories)) {
                return survey.categories.includes(categoryFilter.value)
            } else if (survey.category) {
                return survey.category === categoryFilter.value
            }
            return false
        })();
        
        const surveyExpired = isExpired(survey);

        let matchesTab = false;
        if (activeTab.value === "all") {
            matchesTab = true;
        } else if (activeTab.value === "active") {
            matchesTab = survey.is_active && !surveyExpired;
        } else if (activeTab.value === "unanswered") {
            matchesTab = survey.is_active && !survey.has_responded && !surveyExpired;
        } else if (activeTab.value === "closed") {
            matchesTab = !survey.is_active || surveyExpired;
        }

        return matchesSearch && matchesCategory && matchesTab;
    });
});

// ユーティリティ関数
const getDeadlineDate = (survey: SurveyWithResponse): Date | null => {
    if (!survey.deadline_date) return null;
    const timeStr = survey.deadline_time || '23:59:59';
    return new Date(`${survey.deadline_date} ${timeStr}`);
};

const isExpired = (survey: SurveyWithResponse): boolean => {
    const deadline = getDeadlineDate(survey);
    return deadline ? deadline < new Date() : false;
};

const getResponseRate = (survey: SurveyWithResponse) => {
    const total = (survey.unanswered_names?.length || 0) + (survey.respondent_names?.length || 0);
    const responded = survey.respondent_names?.length || 0;
    return total > 0 ? (responded / total) * 100 : 0;
};

const getDaysUntilDeadline = (survey: SurveyWithResponse) => {
    const deadline = getDeadlineDate(survey);
    if (!deadline) return 0;
    
    const today = new Date();
    const diffTime = deadline.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
};

const page = usePage();
const isEditDialogOpen = ref(false);

// ウォッチャー
watch(
    () => props.editSurvey,
    (survey) => {
        if (survey) {
            editingSurvey.value = survey;
            showCreateDialog.value = true;
            isEditDialogOpen.value = true;
        }
    },
    { immediate: true }
);

watch(
    () => surveyToDelete.value,
    (survey) => {
        // デバッグコード削除
    }
);

// ハンドラ関数
const handleCreate = () => {
    editingSurvey.value = null;
    showCreateDialog.value = true;
};

const handleEdit = (survey: SurveyWithResponse) => {
    editingSurvey.value = survey;
    showCreateDialog.value = true;
};

const handleAnswer = (survey: SurveyWithResponse) => {
    router.get(`/surveys/${survey.survey_id}/answer`);
};

const handleResults = (survey: SurveyWithResponse) => {
    router.get(`/surveys/${survey.survey_id}/results`);
};

const handleDialogClose = () => {
    showCreateDialog.value = false;
    editingSurvey.value = null;
};

const handleDelete = (survey: SurveyWithResponse) => {
    surveyToDelete.value = survey;
};

const confirmDelete = () => {
    if (surveyToDelete.value) {
        const surveyId = surveyToDelete.value.survey_id;
        lastDeletedSurvey.value = surveyToDelete.value;
        router.delete(`/surveys/${surveyId}`, {
            onSuccess: () => {
                surveyToDelete.value = null;
                showMessage('アンケートを削除しました。', 'delete');
                window.dispatchEvent(new CustomEvent('notification-updated'));
            },
            onError: () => {
                surveyToDelete.value = null;
                lastDeletedSurvey.value = null;
                showMessage('アンケートの削除に失敗しました', 'success');
            },
        });
    }
};

const handleUndoDelete = () => {
    if (!lastDeletedSurvey.value) return;
    
    if (messageTimer.value) {
        clearTimeout(messageTimer.value);
    }
    saveMessage.value = '元に戻しています...';
    
    const surveyToRestore = lastDeletedSurvey.value;
    lastDeletedSurvey.value = null;
    
    router.post(`/surveys/${surveyToRestore.survey_id}/restore`, {}, {
        onSuccess: () => {
            showMessage('アンケートが元に戻されました。', 'success');
            window.dispatchEvent(new CustomEvent('notification-updated'));
        },
        onError: () => {
            showMessage('元に戻す処理に失敗しました。', 'success');
        }
    });
};

onMounted(() => {
    const page = usePage()
    const highlightId = (page.props as any).highlight
    if (highlightId) {
        activeTab.value = 'all'
        nextTick(() => {
            setTimeout(() => {
                const element = document.getElementById(`item-${highlightId}`)
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' })
                    setTimeout(() => {
                        element.classList.add('highlight-flash')
                        setTimeout(() => element.classList.remove('highlight-flash'), 3000)
                    }, 500)
                }
            }, 500)
        })
    }
    
    // flashメッセージの表示
    const flash = (page.props as any).flash?.success
    if (flash) {
        showMessage(flash, 'success')
    }
    
    window.addEventListener('survey-saved', (e: any) => {
        showMessage(e.detail.message, 'success')
    })
});

onUnmounted(() => {
    window.removeEventListener('survey-saved', () => {})
});
</script>

<template>
    <Head title="アンケート管理" />
    
    <div class="max-w-[1800px] mx-auto h-full p-6">
        <Card class="h-full overflow-hidden flex flex-col">
            <!-- ヘッダー部分 -->
            <div class="p-4 border-b border-gray-300 dark:border-gray-700 shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <!-- タイトル部分 -->
                    <div class="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="router.get(route('dashboard'))"
                            class="mr-1"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                        <BarChart3 class="h-6 w-6 text-purple-700" />
                        <CardTitle>アンケート管理</CardTitle>
                        
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-5 w-5 p-0 text-gray-500 hover:text-gray-700"
                            @click="isHelpOpen = true"
                            title="アンケート管理の使い方"
                        >
                            <HelpCircle class="h-5 w-5" />
                        </Button>
                    </div>
                    
                    <!-- 検索・作成ボタン -->
                    <div class="flex items-center gap-2">
                        <Select v-model="categoryFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue>
                                    <div class="flex items-center gap-2">
                                        <Filter class="h-4 w-4" />
                                        <span>{{ categoryFilter === 'all' ? 'カテゴリ絞り込み' : categoryFilter }}</span>
                                    </div>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">すべてのカテゴリ</SelectItem>
                                <SelectItem v-for="cat in allCategories" :key="cat" :value="cat">
                                    {{ cat }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                placeholder="タイトルなどで検索"
                                v-model="searchQuery"
                                class="pl-9 w-80"
                            />
                        </div>
                        <Button variant="outline" class="gap-2" @click="handleCreate">
                            <Plus class="h-4 w-4" />
                            新規作成
                        </Button>
                    </div>
                </div>
                
                <!-- タブ部分 -->
                <div>
                    <Tabs v-model="activeTab">
                        <TabsList class="gap-1.5">
                            <TabsTrigger value="all" class="gap-2 bg-blue-50 text-blue-700 hover:bg-blue-100 data-[state=active]:bg-blue-200 data-[state=active]:text-blue-800 dark:bg-blue-950/30 dark:text-blue-300 dark:hover:bg-blue-900/40 dark:data-[state=active]:bg-blue-900/60 dark:data-[state=active]:text-blue-200">
                                <BarChart3 class="h-4 w-4" />
                                すべて ({{ surveys.length }})
                            </TabsTrigger>
                            <TabsTrigger value="active" class="gap-2 bg-green-50 text-green-700 hover:bg-green-100 data-[state=active]:bg-green-200 data-[state=active]:text-green-800 dark:bg-green-950/30 dark:text-green-300 dark:hover:bg-green-900/40 dark:data-[state=active]:bg-green-900/60 dark:data-[state=active]:text-green-200">
                                <CheckCircle2 class="h-4 w-4" />
                                回答受付中 ({{ surveys.filter(s => s.is_active && !isExpired(s)).length }})
                            </TabsTrigger>
                            <TabsTrigger value="unanswered" class="gap-2 bg-orange-50 text-orange-700 hover:bg-orange-100 data-[state=active]:bg-orange-200 data-[state=active]:text-orange-800 dark:bg-orange-950/30 dark:text-orange-300 dark:hover:bg-orange-900/40 dark:data-[state=active]:bg-orange-900/60 dark:data-[state=active]:text-orange-200">
                                <AlertCircle class="h-4 w-4" />
                                未回答 ({{ surveys.filter(s => s.is_active && !s.has_responded && !isExpired(s)).length }})
                            </TabsTrigger>
                            <TabsTrigger value="closed" class="gap-2 bg-gray-50 text-gray-700 hover:bg-gray-100 data-[state=active]:bg-gray-200 data-[state=active]:text-gray-800 dark:bg-gray-800/30 dark:text-gray-300 dark:hover:bg-gray-700/40 dark:data-[state=active]:bg-gray-700/60 dark:data-[state=active]:text-gray-200">
                                <Clock class="h-4 w-4" />
                                終了済み ({{ surveys.filter(s => !s.is_active || isExpired(s)).length }})
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
            </div>
            
            <!-- メインコンテンツ -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4 pb-6">
                <!-- 空の状態 -->
                <div v-if="filteredSurveys.length === 0" class="text-center py-12">
                    <BarChart3 class="h-12 w-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ searchQuery ? "該当するアンケートが見つかりません" : "アンケートがありません" }}
                    </p>
                </div>

                <!-- アンケート一覧 -->
                <Card
                    v-for="survey in filteredSurveys"
                    :key="survey.survey_id"
                    :id="`item-${survey.survey_id}`"
                    class="hover:shadow-md transition-shadow"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-4">
                            <!-- アンケート情報 -->
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <CardTitle
                                        class="text-lg cursor-pointer hover:text-blue-600 transition-colors"
                                        @click="router.get(`/surveys/${survey.survey_id}/results`)"
                                    >
                                        {{ survey.title }}
                                    </CardTitle>
                                    <Badge
                                        v-if="survey.is_active"
                                        :variant="
                                            getDaysUntilDeadline(survey) < 0
                                                ? 'destructive'
                                                : getDaysUntilDeadline(survey) <= 3
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        class="gap-1"
                                    >
                                        <AlertCircle
                                            v-if="getDaysUntilDeadline(survey) <= 0"
                                            class="h-3 w-3"
                                        />
                                        <Clock v-else class="h-3 w-3" />
                                        {{
                                            getDaysUntilDeadline(survey) < 0
                                                ? "期限切れ"
                                                : getDaysUntilDeadline(survey) === 0
                                                ? "今日が期限"
                                                : `残り${getDaysUntilDeadline(survey)}日`
                                        }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ survey.description }}
                                </p>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1">
                                        <CalendarIcon class="h-3 w-3" />
                                        期限:
                                        <span v-if="survey.deadline_date">
                                            {{ new Date(survey.deadline_date).toLocaleDateString('ja-JP') }}
                                            {{ survey.deadline_time ? survey.deadline_time.substring(0, 5) : '23:59' }}
                                        </span>
                                        <span v-else>なし</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        作成者: {{ survey.creator?.name }}
                                    </div>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ survey.questions.length }}問
                                    </Badge>
                                    <Badge
                                        v-for="(cat, index) in survey.categories"
                                        :key="index"
                                        variant="secondary"
                                        class="text-xs"
                                    >
                                        {{ cat }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- アクションボタン -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <template v-if="activeTab === 'closed'">
                                    <!-- 終了済みの場合は結果と削除のみ -->
                                    <Button
                                        variant="outline"
                                        class="gap-2"
                                        @click="handleResults(survey)"
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                        結果を見る
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="gap-2 text-red-600 hover:bg-red-50 hover:border-red-300 dark:text-red-400 dark:hover:bg-red-950/30 dark:hover:border-red-800"
                                        @click="handleDelete(survey)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        削除
                                    </Button>
                                </template>
                                <template v-else>
                                    <!-- アクティブ・未回答の場合は全ボタン表示 -->
                                    <Button
                                        v-if="survey.created_by === auth?.user?.id"
                                        variant="outline"
                                        class="gap-2"
                                        @click="handleEdit(survey)"
                                    >
                                        <Edit class="h-4 w-4" />
                                        編集
                                    </Button>
                                    <Button
                                        v-if="survey.is_active"
                                        variant="outline"
                                        class="gap-2"
                                        :disabled="!survey.can_respond"
                                        @click="handleAnswer(survey)"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        {{ survey.has_responded ? '回答を編集' : '回答する' }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="gap-2"
                                        @click="handleResults(survey)"
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                        結果を見る
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="gap-2 text-red-600 hover:bg-red-50 hover:border-red-300 dark:text-red-400 dark:hover:bg-red-950/30 dark:hover:border-red-800"
                                        @click="handleDelete(survey)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        削除
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- 回答済み -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <CheckCircle2 class="h-4 w-4 text-green-600" />
                                    <span class="text-green-600">
                                        回答済み ({{ survey.responses.length }}名)
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-if="survey.responses.length === 0"
                                        class="text-sm text-gray-400 dark:text-gray-500"
                                    >
                                        まだ回答者がいません
                                    </span>
                                    <div v-else class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="name in survey.respondent_names"
                                            :key="name"
                                            variant="outline"
                                            class="text-xs text-green-600 border-green-300"
                                        >
                                            {{ name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <!-- 未回答 -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <AlertCircle class="h-4 w-4 text-orange-600" />
                                    <span class="text-orange-600">
                                        未回答 ({{ survey.unanswered_names?.length || 0 }}名)
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-if="!survey.unanswered_names || survey.unanswered_names.length === 0"
                                        class="text-sm text-gray-400 dark:text-gray-500"
                                    >
                                        全員回答済み
                                    </span>
                                    <div v-else class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="name in survey.unanswered_names"
                                            :key="name"
                                            variant="outline"
                                            class="text-xs text-orange-600 border-orange-300"
                                        >
                                            {{ name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Card>

        <!-- ダイアログ -->
        <CreateSurveyDialog
            :open="showCreateDialog"
            :survey="editingSurvey"
            :team-members="teamMembers"
            @update:open="handleDialogClose"
            @open-dialog="showCreateDialog = true"
        />

        <CreateSurveyDialog
            v-if="props.editSurvey"
            :open="isEditDialogOpen"
            :survey="props.editSurvey"
            :team-members="teamMembers"
            @update:open="
                isEditDialogOpen = $event;
                if (!$event) {
                    router.get('/surveys');
                }
            "
            @open-dialog="isEditDialogOpen = true"
        />

        <AlertDialog :open="surveyToDelete !== null">
            <AlertDialogContent class="bg-white dark:bg-gray-900">
                <AlertDialogHeader>
                    <AlertDialogTitle>アンケートを削除しますか？</AlertDialogTitle>
                    <AlertDialogDescription>
                        「{{ surveyToDelete?.title }}」をゴミ箱に移動します。ゴミ箱から後で復元できます。
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="surveyToDelete = null">キャンセル</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">
                        ゴミ箱に移動
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

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
                :class="['fixed bottom-4 left-1/2 transform -translate-x-1/2 z-50 p-3 text-white rounded-lg shadow-lg',
                  messageType === 'success' ? 'bg-green-500' : 'bg-red-500']"
            >
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-5 w-5" />
                    <span class="font-medium">{{ saveMessage }}</span>
                    <Button 
                        v-if="messageType === 'delete' && lastDeletedSurvey"
                        variant="link"
                        class="text-white hover:bg-red-400 p-1 h-auto ml-2"
                        @click.stop="handleUndoDelete"
                    >
                        <Undo2 class="h-4 w-4 mr-1" />
                        <span class="underline">元に戻す</span>
                    </Button>
                </div>
            </div>
        </Transition>
        
        <!-- ヘルプダイアログ -->
        <Dialog :open="isHelpOpen" @update:open="isHelpOpen = $event">
            <DialogContent class="max-w-3xl max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-xl">
                        <BarChart3 class="h-6 w-6 text-purple-700" />
                        アンケート管理の使い方
                    </DialogTitle>
                    <DialogDescription class="text-base">
                        アンケート管理の基本的な使い方をご説明します。チームでの意見収集やフィードバックに活用しましょう。
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-6 overflow-y-auto flex-1 pr-2">
                    <!-- 基本操作 -->
                    <div class="relative pl-4 border-l-4 border-blue-500 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-950/30 p-4 rounded-r-lg">
                        <h3 class="font-semibold mb-3 text-lg">📝 基本操作</h3>
                        <div class="space-y-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none opacity-100">
                                        <Button variant="outline" class="gap-2 shadow-sm" tabindex="-1">
                                            <Plus class="h-4 w-4" />
                                            新規作成
                                        </Button>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">アンケート作成</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            「新規作成」ボタンから、新しいアンケートを作成し、質問を設定できます。複数の質問タイプ（選択式、記述式など）が利用できます。
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none select-none">
                                        <div class="relative p-2 rounded-lg">
                                            <Search class="absolute left-5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                            <Input placeholder="タイトルなどで検索" class="pl-9 h-9 w-48" readonly tabindex="-1" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">検索</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            タイトルや説明文、作成者名でアンケートを検索できます。
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- アクション -->
                    <div class="relative pl-4 border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-transparent dark:from-green-950/30 p-4 rounded-r-lg">
                        <h3 class="font-semibold mb-3 text-lg">⚡ アクション</h3>
                        <div class="space-y-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none">
                                        <div class="flex gap-2 flex-wrap p-2 rounded-lg">
                                            <Button variant="outline" size="sm" class="gap-2" tabindex="-1">
                                                <Edit class="h-4 w-4" />
                                                <span class="text-xs">編集</span>
                                            </Button>
                                            <Button variant="outline" size="sm" class="gap-2" tabindex="-1">
                                                <CheckCircle2 class="h-4 w-4" />
                                                <span class="text-xs">回答する</span>
                                            </Button>
                                            <Button variant="outline" size="sm" class="gap-2 text-red-600" tabindex="-1">
                                                <Trash2 class="h-4 w-4" />
                                                <span class="text-xs">削除</span>
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">編集・回答・削除</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            アンケートの編集、回答、または不要なアンケートの削除が行えます。削除したアンケートはゴミ箱から復元できます。
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none">
                                        <div class="p-2 rounded-lg">
                                            <Button variant="outline" size="sm" class="gap-2" tabindex="-1">
                                                <BarChart3 class="h-4 w-4" />
                                                <span class="text-xs">結果を見る</span>
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">結果確認</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            集計結果や、誰が回答済みかなどの詳細を確認できます。グラフやチャートで視覚的に表示されます。
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 状態確認 -->
                    <div class="relative pl-4 border-l-4 border-purple-500 bg-gradient-to-r from-purple-50 to-transparent dark:from-purple-950/30 p-4 rounded-r-lg">
                        <h3 class="font-semibold mb-3 text-lg">📊 状態確認</h3>
                        <div class="space-y-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none">
                                        <div class="flex gap-1 p-2 rounded-lg">
                                            <Badge variant="default" class="gap-1">
                                                <Clock class="h-3 w-3" />
                                                残り3日
                                            </Badge>
                                            <Badge variant="destructive" class="gap-1">
                                                <AlertCircle class="h-3 w-3" />
                                                期限切れ
                                            </Badge>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">期限表示</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            締め切りまでの日数や、期限切れの状態がバッジで表示されます。期限が近づくと色が変わります。
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0 pt-1 pointer-events-none">
                                        <div class="flex flex-col gap-1 p-2 rounded-lg">
                                            <div class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                                <CheckCircle2 class="h-3 w-3" />
                                                <span>回答済み (5名)</span>
                                            </div>
                                            <div class="flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400">
                                                <AlertCircle class="h-3 w-3" />
                                                <span>未回答 (2名)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">回答状況</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                            各カード内で、回答済み・未回答の人数やメンバーを素早く確認できます。バッジでメンバー名が表示されます。
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-800 flex-shrink-0">
                    <p class="text-sm text-blue-800 dark:text-blue-200 flex items-center gap-2">
                        <span class="text-lg">💡</span>
                        <span>アンケート管理はタブで絞り込み、効率的に管理できます</span>
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
