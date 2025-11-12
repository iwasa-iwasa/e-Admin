<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { formatDate } from "@/lib/utils";
import { ref, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import {
    ArrowLeft,
    Download,
    Users,
    Calendar as CalendarIcon,
    Clock,
    CheckCircle2,
    AlertCircle,
    Filter,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Badge } from "@/components/ui/badge";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Progress } from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Bar, Pie, Radar } from "vue-chartjs";
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
} from "chart.js";

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler
);
import { useToast } from "@/components/ui/toast/use-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

defineOptions({
    layout: AuthenticatedLayout,
});

interface SurveyResult {
    id: string;
    title: string;
    description: string;
    deadline: string;
    createdBy: string;
    createdAt: string;
    status: "active" | "closed";
    category: string;
    totalRespondents: number;
    totalNonRespondents: number;
    questions: QuestionResult[];
}

interface QuestionResult {
    id: string;
    question: string;
    type:
        | "single"
        | "multiple"
        | "text"
        | "textarea"
        | "rating"
        | "scale"
        | "dropdown"
        | "date";
    required: boolean;
    responses: any[];
    aggregatedData?: any;
}

const mockSurveyResult: SurveyResult = {
    id: "1",
    title: "2025年度 忘年会の候補日アンケート",
    description: "12月の忘年会について、参加可能な日程を教えてください。",
    deadline: "2025-10-20",
    createdBy: "田中",
    createdAt: "2025-10-10",
    status: "active",
    category: "イベント",
    totalRespondents: 3,
    totalNonRespondents: 1,
    questions: [
        {
            id: "q1",
            question: "忘年会の参加可否を教えてください",
            type: "single",
            required: true,
            responses: [
                { value: "参加する", respondent: "田中" },
                { value: "参加する", respondent: "佐藤" },
                { value: "参加する", respondent: "鈴木" },
            ],
            aggregatedData: [
                { name: "参加する", value: 3, percentage: 100 },
                { name: "参加しない", value: 0, percentage: 0 },
                { name: "わからない", value: 0, percentage: 0 },
            ],
        },
        {
            id: "q2",
            question: "都合の良い日程を選択してください（複数選択可）",
            type: "multiple",
            required: true,
            responses: [
                { value: ["12/20（水）", "12/22（金）"], respondent: "田中" },
                { value: ["12/22（金）"], respondent: "佐藤" },
                { value: ["12/20（水）", "12/22（金）"], respondent: "鈴木" },
            ],
            aggregatedData: [
                { name: "12/20（水）", value: 2 },
                { name: "12/21（木）", value: 0 },
                { name: "12/22（金）", value: 3 },
                { name: "12/23（土）", value: 0 },
            ],
        },
        {
            id: "q3",
            question: "忘年会の満足度を評価してください（前回の参考）",
            type: "rating",
            required: false,
            responses: [
                { value: 5, respondent: "田中" },
                { value: 4, respondent: "佐藤" },
                { value: 5, respondent: "鈴木" },
            ],
            aggregatedData: [
                { name: "1", value: 0 },
                { name: "2", value: 0 },
                { name: "3", value: 0 },
                { name: "4", value: 1 },
                { name: "5", value: 2 },
            ],
        },
        {
            id: "q4",
            question: "忘年会で食べたい料理のジャンルを評価してください",
            type: "scale",
            required: false,
            responses: [
                {
                    和食: 5,
                    洋食: 3,
                    中華: 4,
                    イタリアン: 2,
                    respondent: "田中",
                },
                {
                    和食: 4,
                    洋食: 4,
                    中華: 3,
                    イタリアン: 4,
                    respondent: "佐藤",
                },
                {
                    和食: 3,
                    洋食: 5,
                    中華: 4,
                    イタリアン: 5,
                    respondent: "鈴木",
                },
            ],
            aggregatedData: [
                { subject: "和食", A: 4.0, fullMark: 5 },
                { subject: "洋食", A: 4.0, fullMark: 5 },
                { subject: "中華", A: 3.7, fullMark: 5 },
                { subject: "イタリアン", A: 3.7, fullMark: 5 },
            ],
        },
        {
            id: "q5",
            question: "ご意見・ご要望があればお書きください",
            type: "textarea",
            required: false,
            responses: [
                {
                    value: "楽しい忘年会を期待しています。去年も楽しかったです。",
                    respondent: "田中",
                    timestamp: "2025-10-15 10:30",
                },
                {
                    value: "アレルギー対応のメニューがあると助かります。",
                    respondent: "佐藤",
                    timestamp: "2025-10-15 14:20",
                },
                {
                    value: "早めに会場を教えていただけると嬉しいです。",
                    respondent: "鈴木",
                    timestamp: "2025-10-16 09:15",
                },
            ],
            aggregatedData: null,
        },
    ],
};

const COLORS = [
    "#3b82f6",
    "#10b981",
    "#f59e0b",
    "#ef4444",
    "#8b5cf6",
    "#ec4899",
    "#06b6d4",
    "#84cc16",
];

const departmentFilter = ref("all");
const periodFilter = ref("all");

const { toast } = useToast();

const props = defineProps<{
    survey?: App.Models.Survey;
    responses?: App.Models.SurveyResponse[];
    statistics?: any;
}>();

const handleDownloadCSV = () => {
    if (props.survey) {
        // CSVダウンロードは直接URLにアクセス
        window.location.href = `/surveys/${props.survey.survey_id}/export`;
        toast({
            title: "Success",
            description: "CSVファイルをダウンロードしました",
        });
    } else {
        toast({
            title: "Error",
            description: "アンケートデータが見つかりません",
            variant: "destructive",
        });
    }
};

// 実際のデータから計算
const responseRate = computed(() => {
    if (!props.survey || !props.responses) {
        return 0;
    }
    // 総ユーザー数は仮に設定（実際の実装ではユーザー数を取得）
    const totalUsers = 10; // 仮の値
    const respondedCount = props.responses.length;
    return totalUsers > 0 ? (respondedCount / totalUsers) * 100 : 0;
});

// アンケートデータの取得（propsから）
const surveyData = computed(() => {
    if (props.survey) {
        return {
            id: String(props.survey.survey_id),
            title: props.survey.title,
            description: props.survey.description,
            deadline: props.survey.deadline,
            createdBy: props.survey.creator?.name || "不明",
            createdAt: props.survey.created_at,
            status: props.survey.is_active ? "active" : "closed",
            category: "イベント", // カテゴリは現在DBに保存されていないため
            totalRespondents: props.responses?.length || 0,
            totalNonRespondents: 0, // 仮の値
            questions:
                props.survey.questions?.map((q: any) => {
                    const questionStats =
                        props.statistics?.[q.question_id] || {};
                    return {
                        id: String(q.question_id),
                        question: q.question_text,
                        type: mapQuestionTypeToFrontend(q.question_type),
                        required: q.is_required,
                        responses: getQuestionResponses(
                            q,
                            props.responses || []
                        ),
                        aggregatedData: questionStats.distribution
                            ? Object.entries(questionStats.distribution).map(
                                  ([name, value]) => ({
                                      name,
                                      value,
                                      percentage:
                                          questionStats.total_responses > 0
                                              ? Math.round(
                                                    (Number(value) /
                                                        questionStats.total_responses) *
                                                        100
                                                )
                                              : 0,
                                  })
                              )
                            : null,
                    };
                }) || [],
        };
    }
    return mockSurveyResult;
});

// 質問タイプのマッピング
const mapQuestionTypeToFrontend = (dbType: string): string => {
    const mapping: Record<string, string> = {
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

// 質問ごとの回答を取得
const getQuestionResponses = (
    question: any,
    responses: App.Models.SurveyResponse[]
) => {
    const questionResponses: any[] = [];

    responses.forEach((response) => {
        const answer = response.answers?.find(
            (a: any) => a.question_id === question.question_id
        );
        if (answer) {
            let value: any = answer.answer_text;

            // 選択肢がある場合は選択肢テキストを使用
            if (answer.selected_option_id && question.options) {
                const option = question.options.find(
                    (opt: any) => opt.option_id === answer.selected_option_id
                );
                if (option) {
                    value = option.option_text;
                }
            }

            questionResponses.push({
                value,
                respondent: response.respondent?.name || "匿名",
                timestamp: response.submitted_at,
            });
        }
    });

    return questionResponses;
};
</script>

<template>
    <Head title="アンケート結果" />
    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="router.get('/surveys')"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 class="text-blue-600">アンケート結果</h1>
                            <p class="text-xs text-gray-500">集計結果の分析</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button @click="handleDownloadCSV" class="gap-2">
                            <Download class="h-4 w-4" />
                            CSVダウンロード
                        </Button>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
            <Card class="mb-6">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <CardTitle class="text-xl mb-2">{{
                                surveyData.title
                            }}</CardTitle>
                            <p class="text-gray-600 mb-4">
                                {{ surveyData.description }}
                            </p>
                            <div
                                class="flex flex-wrap items-center gap-4 text-sm text-gray-500"
                            >
                                <div class="flex items-center gap-1">
                                    <CalendarIcon class="h-4 w-4" />
                                    締切: {{ formatDate(surveyData.deadline) }}
                                </div>
                                <div class="flex items-center gap-1">
                                    作成者: {{ surveyData.createdBy }}
                                </div>
                                <Badge variant="outline">{{
                                    surveyData.category
                                }}</Badge>
                                <Badge
                                    :class="[
                                        surveyData.status === 'active'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-gray-100 text-gray-700',
                                    ]"
                                >
                                    {{
                                        surveyData.status === "active"
                                            ? "アクティブ"
                                            : "終了"
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="bg-blue-50 border border-blue-200 rounded-lg p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <CheckCircle2
                                        class="h-6 w-6 text-blue-600"
                                    />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">
                                        回答済み
                                    </p>
                                    <p class="text-2xl text-blue-600">
                                        {{ surveyData.totalRespondents }}名
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-orange-50 border border-orange-200 rounded-lg p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-orange-100 rounded-lg">
                                    <AlertCircle
                                        class="h-6 w-6 text-orange-600"
                                    />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">未回答</p>
                                    <p class="text-2xl text-orange-600">
                                        {{ surveyData.totalNonRespondents }}名
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-green-50 border border-green-200 rounded-lg p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <Users class="h-6 w-6 text-green-600" />
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-600 mb-1">
                                        回答率
                                    </p>
                                    <Progress
                                        :model-value="responseRate"
                                        class="h-2 mb-1"
                                    />
                                    <p class="text-sm text-green-600">
                                        {{ Math.round(responseRate) }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="mb-6">
                <CardContent class="pt-6">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <Filter class="h-4 w-4 text-gray-500" />
                            <span class="text-sm text-gray-600"
                                >フィルター:</span
                            >
                        </div>
                        <Select v-model="departmentFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="部署で絞り込み" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all"
                                    >すべての部署</SelectItem
                                >
                                <SelectItem value="soumu">総務部</SelectItem>
                                <SelectItem value="jinji">人事部</SelectItem>
                                <SelectItem value="keiri">経理部</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="periodFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="期間で絞り込み" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">全期間</SelectItem>
                                <SelectItem value="today">今日</SelectItem>
                                <SelectItem value="week">今週</SelectItem>
                                <SelectItem value="month">今月</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-gray-900">質問ごとの集計結果</h2>
                    <Badge variant="secondary"
                        >全{{ surveyData.questions.length }}問</Badge
                    >
                </div>
                <Card
                    v-for="(question, index) in surveyData.questions"
                    :key="question.id"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <Badge variant="outline"
                                        >質問 {{ index + 1 }}</Badge
                                    >
                                    <Badge
                                        v-if="question.required"
                                        variant="secondary"
                                        class="text-xs"
                                        >必須</Badge
                                    >
                                </div>
                                <CardTitle class="text-lg">{{
                                    question.question
                                }}</CardTitle>
                            </div>
                            <Badge class="ml-4"
                                >{{ question.responses.length }}件の回答</Badge
                            >
                        </div>
                    </CardHeader>
                    <CardContent>
                        <template
                            v-if="
                                question.type === 'single' ||
                                question.type === 'dropdown'
                            "
                        >
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-sm mb-4 text-gray-600">
                                        回答の割合
                                    </h4>
                                    <div
                                        style="
                                            height: 300px;
                                            position: relative;
                                        "
                                    >
                                        <Pie
                                            :data="{
                                                labels: question.aggregatedData.map(
                                                    (d) =>
                                                        `${d.name} (${d.percentage}%)`
                                                ),
                                                datasets: [
                                                    {
                                                        backgroundColor: COLORS,
                                                        data: question.aggregatedData.map(
                                                            (d) => d.value
                                                        ),
                                                    },
                                                ],
                                            }"
                                            :options="{
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        position: 'top',
                                                    },
                                                },
                                            }"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm mb-4 text-gray-600">
                                        回答数
                                    </h4>
                                    <div
                                        style="
                                            height: 300px;
                                            position: relative;
                                        "
                                    >
                                        <Bar
                                            :data="{
                                                labels: question.aggregatedData.map(
                                                    (d) => d.name
                                                ),
                                                datasets: [
                                                    {
                                                        label: '回答数',
                                                        backgroundColor:
                                                            '#3b82f6',
                                                        data: question.aggregatedData.map(
                                                            (d) => d.value
                                                        ),
                                                    },
                                                ],
                                            }"
                                            :options="{
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        display: false,
                                                    },
                                                },
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="question.type === 'multiple'">
                            <div>
                                <h4 class="text-sm mb-4 text-gray-600">
                                    選択された回数（複数選択可）
                                </h4>
                                <div style="height: 300px; position: relative">
                                    <Bar
                                        :data="{
                                            labels: question.aggregatedData.map(
                                                (d) => d.name
                                            ),
                                            datasets: [
                                                {
                                                    label: '選択された回数',
                                                    backgroundColor: '#10b981',
                                                    data: question.aggregatedData.map(
                                                        (d) => d.value
                                                    ),
                                                },
                                            ],
                                        }"
                                        :options="{
                                            indexAxis: 'y',
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: {
                                                    display: false,
                                                },
                                            },
                                        }"
                                    />
                                </div>
                            </div>
                        </template>
                        <template v-if="question.type === 'rating'">
                            <div class="space-y-6">
                                <div
                                    class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center"
                                >
                                    <p class="text-sm text-gray-600 mb-2">
                                        平均評価
                                    </p>
                                    <p class="text-4xl text-blue-600 mb-2">
                                        {{
                                            (
                                                question.responses.reduce(
                                                    (sum, r) => sum + r.value,
                                                    0
                                                ) / question.responses.length
                                            ).toFixed(1)
                                        }}
                                    </p>
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <span
                                            v-for="i in 5"
                                            :key="i"
                                            :class="[
                                                'text-2xl',
                                                i <
                                                Math.round(
                                                    question.responses.reduce(
                                                        (sum, r) =>
                                                            sum + r.value,
                                                        0
                                                    ) /
                                                        question.responses
                                                            .length
                                                )
                                                    ? 'text-yellow-400'
                                                    : 'text-gray-300',
                                            ]"
                                            >★</span
                                        >
                                    </div>
                                    <p class="text-sm text-gray-500 mt-2">
                                        {{ question.responses.length }}件の回答
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm mb-4 text-gray-600">
                                        評価の分布
                                    </h4>
                                    <div
                                        style="
                                            height: 250px;
                                            position: relative;
                                        "
                                    >
                                        <Bar
                                            :data="{
                                                labels: question.aggregatedData.map(
                                                    (d) => d.name
                                                ),
                                                datasets: [
                                                    {
                                                        label: '回答数',
                                                        backgroundColor:
                                                            '#f59e0b',
                                                        data: question.aggregatedData.map(
                                                            (d) => d.value
                                                        ),
                                                    },
                                                ],
                                            }"
                                            :options="{
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: {
                                                    legend: {
                                                        display: false,
                                                    },
                                                },
                                                scales: {
                                                    x: {
                                                        title: {
                                                            display: true,
                                                            text: '星の数',
                                                        },
                                                    },
                                                    y: {
                                                        title: {
                                                            display: true,
                                                            text: '回答数',
                                                        },
                                                    },
                                                },
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-if="question.type === 'scale'">
                            <div>
                                <h4 class="text-sm mb-4 text-gray-600">
                                    各項目の平均評価（5段階）
                                </h4>
                                <div style="height: 400px; position: relative">
                                    <Radar
                                        :data="{
                                            labels: question.aggregatedData.map(
                                                (d) => d.subject
                                            ),
                                            datasets: [
                                                {
                                                    label: '平均評価',
                                                    backgroundColor:
                                                        'rgba(139, 92, 246, 0.6)',
                                                    borderColor: '#8b5cf6',
                                                    pointBackgroundColor:
                                                        '#8b5cf6',
                                                    data: question.aggregatedData.map(
                                                        (d) => d.A
                                                    ),
                                                },
                                            ],
                                        }"
                                        :options="{
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                r: {
                                                    angleLines: {
                                                        display: false,
                                                    },
                                                    suggestedMin: 0,
                                                    suggestedMax: 5,
                                                },
                                            },
                                        }"
                                    />
                                </div>
                            </div>
                        </template>
                        <template
                            v-if="
                                question.type === 'text' ||
                                question.type === 'textarea'
                            "
                        >
                            <div class="space-y-4">
                                <h4 class="text-sm text-gray-600">
                                    回答一覧（{{
                                        question.responses.length
                                    }}件）
                                </h4>
                                <ScrollArea class="h-[400px] pr-4">
                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                response, index
                                            ) in question.responses"
                                            :key="index"
                                            class="bg-gray-50 border border-gray-200 rounded-lg p-4"
                                        >
                                            <div
                                                class="flex items-start justify-between mb-2"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Badge
                                                        variant="outline"
                                                        class="text-xs"
                                                        >{{
                                                            response.respondent
                                                        }}</Badge
                                                    >
                                                    <span
                                                        v-if="
                                                            response.timestamp
                                                        "
                                                        class="text-xs text-gray-500"
                                                        >{{
                                                            formatDate(
                                                                response.timestamp
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <p
                                                class="text-gray-700 whitespace-pre-wrap"
                                            >
                                                {{ response.value }}
                                            </p>
                                        </div>
                                    </div>
                                </ScrollArea>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </main>
    </div>
</template>
