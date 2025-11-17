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
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateSurveyDialog from "@/components/CreateSurveyDialog.vue";
import { useToast } from "@/components/ui/toast/use-toast";

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
}>();

const searchQuery = ref("");
const categoryFilter = ref("all");
const activeTab = ref("active");
const isCreateSurveyDialogOpen = ref(false);
const showCreateDialog = ref(false);
const editingSurvey = ref(null);

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
        if (activeTab.value === "active") {
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
    const total = survey.questions[0]?.survey.responses.length || 0;
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
const { toast } = useToast();
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

// 削除処理
const handleDelete = (survey: App.Models.Survey) => {
    if (
        window.confirm(
            "本当にこのアンケートを削除しますか？この操作は取り消せません。"
        )
    ) {
        router.delete(`/surveys/${survey.survey_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: "Success",
                    description: "アンケートを削除しました",
                });
            },
            onError: () => {
                toast({
                    title: "Error",
                    description: "アンケートの削除に失敗しました",
                    variant: "destructive",
                });
            },
        });
    }
};
</script>

<template>
    <Head title="アンケート管理" />
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="router.get('/')"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                        <div class="flex items-center gap-2">
                            <BarChart3 class="h-6 w-6 text-blue-600" />
                            <div>
                                <h1 class="text-blue-600">アンケート管理</h1>
                                <p class="text-xs text-gray-500">
                                    総務部 共同管理
                                </p>
                            </div>
                        </div>
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
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
            <div class="mb-6 space-y-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                        />
                        <Input
                            placeholder="アンケートのタイトル、説明、作成者で検索..."
                            v-model="searchQuery"
                            class="pl-9"
                        />
                    </div>
                </div>
                <Tabs v-model="activeTab">
                    <TabsList>
                        <TabsTrigger value="active" class="gap-2">
                            <CheckCircle2 class="h-4 w-4" />
                            アクティブ ({{
                                surveys.filter((s) => {
                                    const now = new Date();
                                    const deadline = new Date(s.deadline);
                                    return s.is_active && deadline >= now;
                                }).length
                            }})
                        </TabsTrigger>
                        <TabsTrigger value="unanswered" class="gap-2">
                            <AlertCircle class="h-4 w-4" />
                            未回答 ({{
                                surveys.filter((s) => {
                                    const now = new Date();
                                    const deadline = new Date(s.deadline);
                                    return s.is_active && !s.has_responded && deadline >= now;
                                }).length
                            }})
                        </TabsTrigger>
                        <TabsTrigger value="closed" class="gap-2">
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

            <div class="h-[calc(100vh-280px)] overflow-y-auto">
                <div class="space-y-4 pb-6">
                    <div v-if="filteredSurveys.length === 0">
                        <Card>
                            <CardContent class="py-12 text-center">
                                <BarChart3
                                    class="h-12 w-12 mx-auto mb-3 text-gray-300"
                                />
                                <p class="text-gray-500">
                                    {{
                                        searchQuery
                                            ? "該当するアンケートが見つかりません"
                                            : "アンケートがありません"
                                    }}
                                </p>
                            </CardContent>
                        </Card>
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
                                                    class="text-xs"
                                                >
                                                    {{ name }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2" v-if="!survey.has_responded">
                                        <div
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <AlertCircle
                                                class="h-4 w-4 text-orange-600"
                                            />
                                            <span class="text-orange-600"
                                                >未回答</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>

        <CreateSurveyDialog
            :open="showCreateDialog"
            :survey="editingSurvey"
            @update:open="handleDialogClose"
            @open-dialog="showCreateDialog = true"
        />

        <CreateSurveyDialog
            v-if="props.editingSurvey"
            :open="isEditDialogOpen"
            :survey="props.editingSurvey"
            @update:open="
                isEditDialogOpen = $event;
                if (!$event) {
                    router.get('/surveys');
                }
            "
            @open-dialog="isEditDialogOpen = true"
        />
    </div>
</template>
