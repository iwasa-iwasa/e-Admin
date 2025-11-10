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

const props = defineProps<{
    surveys: App.Models.Survey[];
    editingSurvey?: App.Models.Survey | null;
}>();

const searchQuery = ref("");
const categoryFilter = ref("all");
const activeTab = ref("active");
const isCreateSurveyDialogOpen = ref(false);

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

        // This part needs to be adapted as category is not in the model
        // const matchesCategory = categoryFilter.value === 'all' || survey.category === categoryFilter.value
        const matchesCategory = true;

        const status = survey.is_active ? "active" : "closed";
        const matchesTab = status === activeTab.value;

        return matchesSearch && matchesCategory && matchesTab;
    });
});

const categories = computed(() =>
    Array.from(new Set(props.surveys.map((survey) => survey.category || "N/A")))
);

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

// フラッシュメッセージの表示
watch(
    () => page.props.flash?.success,
    (success) => {
        if (success) {
            toast({
                title: "Success",
                description: success,
            });
        }
    },
    { immediate: true }
);

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
                        @click="isCreateSurveyDialogOpen = true"
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
                                surveys.filter((s) => s.is_active).length
                            }})
                        </TabsTrigger>
                        <TabsTrigger value="closed" class="gap-2">
                            <Clock class="h-4 w-4" />
                            終了済み ({{
                                surveys.filter((s) => !s.is_active).length
                            }})
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <ScrollArea class="h-[calc(100vh-280px)]">
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
                                    <Button
                                        variant="outline"
                                        class="gap-2"
                                        @click="
                                            router.get(
                                                `/surveys/${survey.survey_id}/edit`
                                            )
                                        "
                                    >
                                        <Edit class="h-4 w-4" />
                                        編集
                                    </Button>
                                    <Button
                                        v-if="survey.is_active"
                                        class="gap-2"
                                        disabled
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        回答する
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="gap-2"
                                        @click="
                                            router.get(
                                                `/surveys/${survey.survey_id}/results`
                                            )
                                        "
                                    >
                                        <BarChart3 class="h-4 w-4" />
                                        結果を見る
                                    </Button>
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
                                                >未回答</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </ScrollArea>
        </main>

        <CreateSurveyDialog
            :open="isCreateSurveyDialogOpen"
            @update:open="isCreateSurveyDialogOpen = $event"
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
        />
    </div>
</template>
