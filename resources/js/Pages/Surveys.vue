<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
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
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateSurveyDialog from "@/components/CreateSurveyDialog.vue";


defineOptions({
    layout: AuthenticatedLayout,
});

interface SurveyWithResponse extends App.Models.Survey {
    has_responded?: boolean;
}

const props = defineProps<{
    surveys: SurveyWithResponse[];
    editingSurvey?: App.Models.Survey | null;
    editSurvey?: Object;
    teamMembers?: Array<{id: number, name: string}>;
}>();

const searchQuery = ref("");
const categoryFilter = ref("all");
const activeTab = ref("active");
const isCreateSurveyDialogOpen = ref(false);
const showCreateDialog = ref(false);
const editingSurvey = ref(null);
const surveyToDelete = ref<App.Models.Survey | null>(null);
const saveMessage = ref('');
const messageType = ref<'success' | 'delete'>('success');
const messageTimer = ref<number | null>(null);
const lastDeletedSurvey = ref<App.Models.Survey | null>(null);

const showMessage = (message: string, type: 'success' | 'delete' = 'success') => {
  if (messageTimer.value) {
    clearTimeout(messageTimer.value);
  }
  saveMessage.value = message;
  messageType.value = type;
  messageTimer.value = setTimeout(() => {
    saveMessage.value = '';
    lastDeletedSurvey.value = null;
  }, 4000);
};

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

        const matchesCategory = true;
        
        const now = new Date();
        const deadline = new Date(survey.deadline);
        const isExpired = deadline < now;

        let matchesTab = false;
        if (activeTab.value === "all") {
            // すべて: すべてのアンケートを表示
            matchesTab = true;
        } else if (activeTab.value === "active") {
            // アクティブ: アクティブかつ期限切れでない
            matchesTab = survey.is_active && !isExpired;
        } else if (activeTab.value === "unanswered") {
            // 未回答: アクティブかつ未回答かつ期限切れでない
            matchesTab = survey.is_active && !survey.has_responded && !isExpired;
        } else if (activeTab.value === "closed") {
            // 終了済み: 非アクティブまたは期限切れ
            matchesTab = !survey.is_active || isExpired;
        }

        return matchesSearch && matchesCategory && matchesTab;
    });
});



const getResponseRate = (survey: App.Models.Survey) => {
    const total = (survey.unanswered_names?.length || 0) + (survey.respondent_names?.length || 0);
    const responded = survey.responses.length;
    return total > 0 ? (responded / total) * 100 : 0;
};

const getDaysUntilDeadline = (deadline: string) => {
    const today = new Date();
    const deadlineDate = new Date(deadline);
    const diffTime = deadlineDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
};

const page = usePage();
const isEditDialogOpen = ref(false);



// 編集ダイアログを開く
watch(
    () => props.editingSurvey,
    (survey) => {
        if (survey) {
            isEditDialogOpen.value = true;
        }
    },
    { immediate: true }
);

// editSurveyが存在する場合、自動的にダイアログを開く
watch(
    () => props.editSurvey,
    (survey) => {
        if (survey) {
            editingSurvey.value = survey;
            showCreateDialog.value = true;
        }
    },
    { immediate: true }
);

// 削除ダイアログの状態を監視
watch(
    () => surveyToDelete.value,
    (survey) => {
        console.log('surveyToDelete changed:', survey);
    }
);

// ハンドラ関数
const handleCreate = () => {
    editingSurvey.value = null;
    showCreateDialog.value = true;
};

const handleEdit = (survey: App.Models.Survey) => {
    editingSurvey.value = survey;
    showCreateDialog.value = true;
};

const handleAnswer = (survey: App.Models.Survey) => {
    router.get(`/surveys/${survey.survey_id}/answer`);
};

const handleResults = (survey: App.Models.Survey) => {
    router.get(`/surveys/${survey.survey_id}/results`);
};

const handleDialogClose = () => {
    showCreateDialog.value = false;
    editingSurvey.value = null;
};

// 削除確認ダイアログを表示
const handleDelete = (survey: App.Models.Survey) => {
    console.log('handleDelete called for survey:', survey.survey_id);
    surveyToDelete.value = survey;
    console.log('surveyToDelete set to:', surveyToDelete.value);
};

// 実際の削除処理
const confirmDelete = () => {
    console.log('confirmDelete called');
    if (surveyToDelete.value) {
        const surveyId = surveyToDelete.value.survey_id;
        console.log('Deleting survey:', surveyId);
        lastDeletedSurvey.value = surveyToDelete.value;
        router.delete(`/surveys/${surveyId}`, {
            onSuccess: (page) => {
                console.log('Delete success:', page);
                surveyToDelete.value = null;
                showMessage('アンケートを削除しました。', 'delete');
            },
            onError: (errors) => {
                console.error('Delete error:', errors);
                surveyToDelete.value = null;
                lastDeletedSurvey.value = null;
                showMessage('アンケートの削除に失敗しました', 'success');
            },
        });
    }
};

// 元に戻す処理
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
        },
        onError: () => {
            showMessage('元に戻す処理に失敗しました。', 'success');
        }
    });
};
</script>

<template>
    <Head title="アンケート管理" />
    <div class="max-w-[1800px] mx-auto h-[calc(100vh-140px)]">
        <Card class="h-full overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="router.get('/')"
                            class="mr-1"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                        <BarChart3 class="h-6 w-6 text-blue-600" />
                        <h1 class="text-blue-600">アンケート管理</h1>
                    </div>
                    <Button
                        variant="outline"
                        class="gap-2"
                        @click="handleCreate"
                    >
                        <Plus class="h-4 w-4" />
                        新しいアンケートを作成
                    </Button>
                </div>
                <p class="text-sm text-gray-500">総務部 共同管理</p>
                <div class="">
                    <div class="relative mt-2 mb-1">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                        />
                        <Input
                            placeholder="アンケートのタイトル、説明、作成者で検索..."
                            v-model="searchQuery"
                            class="pl-9"
                        />
                    </div>
                    <Tabs v-model="activeTab">
                        <TabsList class="gap-1.5">
                        <TabsTrigger value="all" class="gap-2 bg-blue-50 text-blue-700 hover:bg-blue-100 data-[state=active]:bg-blue-200 data-[state=active]:text-blue-800">
                            <BarChart3 class="h-4 w-4" />
                            すべて ({{ surveys.length }})
                        </TabsTrigger>
                        <TabsTrigger value="active" class="gap-2 bg-green-50 text-green-700 hover:bg-green-100 data-[state=active]:bg-green-200 data-[state=active]:text-green-800">
                            <CheckCircle2 class="h-4 w-4" />
                            アクティブ ({{
                                surveys.filter((s) => {
                                    const now = new Date();
                                    const deadline = new Date(s.deadline);
                                    return s.is_active && deadline >= now;
                                }).length
                            }})
                        </TabsTrigger>
                        <TabsTrigger value="unanswered" class="gap-2 bg-orange-50 text-orange-700 hover:bg-orange-100 data-[state=active]:bg-orange-200 data-[state=active]:text-orange-800">
                            <AlertCircle class="h-4 w-4" />
                            未回答 ({{
                                surveys.filter((s) => {
                                    const now = new Date();
                                    const deadline = new Date(s.deadline);
                                    return s.is_active && !s.has_responded && deadline >= now;
                                }).length
                            }})
                        </TabsTrigger>
                        <TabsTrigger value="closed" class="gap-2 bg-gray-50 text-gray-700 hover:bg-gray-100 data-[state=active]:bg-gray-200 data-[state=active]:text-gray-800">
                            <Clock class="h-4 w-4" />
                            終了済み ({{
                                surveys.filter((s) => {
                                    const now = new Date();
                                    const deadline = new Date(s.deadline);
                                    return !s.is_active || deadline < now;
                                }).length
                            }})
                        </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
            </div>
            
            <ScrollArea class="flex-1">
                <div class="p-6 space-y-4 pb-6">
                    <div v-if="filteredSurveys.length === 0" class="text-center py-12">
                        <BarChart3 class="h-12 w-12 mx-auto mb-3 text-gray-300" />
                        <p class="text-gray-500">
                            {{
                                searchQuery
                                    ? "該当するアンケートが見つかりません"
                                    : "アンケートがありません"
                            }}
                        </p>
                    </div>
                    <Card
                        v-for="survey in filteredSurveys"
                        :key="survey.survey_id"
                        class="hover:shadow-md transition-shadow"
                    >
                        <CardHeader>
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <CardTitle
                                            class="text-lg cursor-pointer hover:text-blue-600 transition-colors"
                                            @click="
                                                router.get(
                                                    `/surveys/${survey.survey_id}/results`
                                                )
                                            "
                                        >
                                            {{ survey.title }}
                                        </CardTitle>
                                        <Badge
                                            v-if="survey.is_active"
                                            :variant="
                                                getDaysUntilDeadline(
                                                    survey.deadline
                                                ) < 0
                                                    ? 'destructive'
                                                    : getDaysUntilDeadline(
                                                          survey.deadline
                                                      ) <= 3
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            class="gap-1"
                                        >
                                            <AlertCircle
                                                v-if="
                                                    getDaysUntilDeadline(
                                                        survey.deadline
                                                    ) <= 0
                                                "
                                                class="h-3 w-3"
                                            />
                                            <Clock v-else class="h-3 w-3" />
                                            {{
                                                getDaysUntilDeadline(
                                                    survey.deadline
                                                ) < 0
                                                    ? "期限切れ"
                                                    : getDaysUntilDeadline(
                                                          survey.deadline
                                                      ) === 0
                                                    ? "今日が期限"
                                                    : `残り${getDaysUntilDeadline(
                                                          survey.deadline
                                                      )}日`
                                            }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3">
                                        {{ survey.description }}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-3 text-xs text-gray-500"
                                    >
                                        <div class="flex items-center gap-1">
                                            <CalendarIcon class="h-3 w-3" />
                                            期限:
                                            {{
                                                new Date(
                                                    survey.deadline
                                                ).toLocaleDateString()
                                            }}
                                        </div>
                                        <div class="flex items-center gap-1">
                                            作成者: {{ survey.creator?.name }}
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            class="text-xs"
                                            >{{
                                                survey.questions.length
                                            }}問</Badge
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 flex-shrink-0"
                                >
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
                                            class="gap-2 text-red-600 hover:bg-red-50 hover:border-red-300"
                                            @click="handleDelete(survey)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            削除
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <!-- アクティブ・未回答の場合は全ボタン表示 -->
                                        <Button
                                            variant="outline"
                                            class="gap-2"
                                            @click="handleEdit(survey)"
                                        >
                                            <Edit class="h-4 w-4" />
                                            編集
                                        </Button>
                                        <Button
                                            v-if="survey.is_active && !survey.has_responded"
                                            variant="outline"
                                            class="gap-2"
                                            @click="handleAnswer(survey)"
                                        >
                                            <CheckCircle2 class="h-4 w-4" />
                                            回答する
                                        </Button>
                                        <Button
                                            v-if="survey.is_active && survey.has_responded"
                                            variant="outline"
                                            class="gap-2 opacity-50 cursor-not-allowed"
                                            disabled
                                        >
                                            <CheckCircle2 class="h-4 w-4" />
                                            回答済み
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
                                            class="gap-2 text-red-600 hover:bg-red-50 hover:border-red-300"
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
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <div
                                        class="flex items-center justify-between text-sm"
                                    >
                                        <span class="text-gray-600"
                                            >回答状況</span
                                        >
                                    </div>
                                    <Progress :model-value="0" class="h-2" />
                                </div>
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div class="space-y-2">
                                        <div
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <CheckCircle2
                                                class="h-4 w-4 text-green-600"
                                            />
                                            <span class="text-green-600"
                                                >回答済み ({{
                                                    survey.responses.length
                                                }}名)</span
                                            >
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-if="
                                                    survey.responses.length ===
                                                    0
                                                "
                                                class="text-sm text-gray-400"
                                                >まだ回答者がいません</span
                                            >
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
                                    <div class="space-y-2">
                                        <div
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <AlertCircle
                                                class="h-4 w-4 text-orange-600"
                                            />
                                            <span class="text-orange-600"
                                                >未回答 ({{ survey.unanswered_names?.length || 0 }}名)</span
                                            >
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-if="!survey.unanswered_names || survey.unanswered_names.length === 0"
                                                class="text-sm text-gray-400"
                                                >全員回答済み</span
                                            >
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
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </ScrollArea>
        </Card>

        <CreateSurveyDialog
            :open="showCreateDialog"
            :survey="editingSurvey"
            :team-members="teamMembers"
            @update:open="handleDialogClose"
            @open-dialog="showCreateDialog = true"
        />

        <CreateSurveyDialog
            v-if="props.editingSurvey"
            :open="isEditDialogOpen"
            :survey="props.editingSurvey"
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
            <AlertDialogContent class="bg-white">
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
    </div>
</template>
