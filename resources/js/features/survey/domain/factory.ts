import { Question, QuestionType } from './models'
import {
    Circle,
    CheckSquare,
    Type,
    Star,
    BarChart2,
    List,
    Clock,
} from "lucide-vue-next";

export interface QuestionTemplate {
    type: QuestionType;
    name: string;
    description: string;
    icon: any;
    defaultOptions?: string[];
    scaleMin?: number;
    scaleMax?: number;
}

export const questionTemplates: QuestionTemplate[] = [
    {
        type: "single",
        name: "単一選択（ラジオボタン）",
        description: "複数の選択肢から1つを選ぶ形式",
        icon: Circle,
        defaultOptions: ["", "", ""],
    },
    {
        type: "multiple",
        name: "複数選択（チェックボックス）",
        description: "複数の選択肢から複数選べる形式",
        icon: CheckSquare,
        defaultOptions: ["", "", ""],
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
        description: "段階的に評価する形式（1〜10段階）",
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
        defaultOptions: ["", "", ""],
    },
    {
        type: "date",
        name: "日付/時刻",
        description: "特定の日時を入力する形式",
        icon: Clock,
        defaultOptions: [],
    },
];

export const createQuestionFromTemplate = (template: QuestionTemplate): Question => {
    return {
        id: String(Date.now()),
        type: template.type,
        question: "",
        options: template.defaultOptions ? [...template.defaultOptions] : [],
        required: false,
        scaleMin: template.scaleMin,
        scaleMax: template.scaleMax,
        scaleMinLabel: template.scaleMin === 1 ? "とても悪い" : "",
        scaleMaxLabel: template.scaleMax === 5 ? "とても良い" : "",
    }
}

// Backend to Frontend Adapter
export const mapQuestionTypeFromDb = (dbType: string): QuestionType => {
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

export const convertQuestionFromBackend = (q: any, index: number = 0): Question => {
    return {
        id: q.question_id || String(Date.now() + index),
        type: mapQuestionTypeFromDb(q.question_type),
        question: q.question_text || "",
        options: q.options?.map((opt: any) => ({
            option_id: opt.option_id,
            text: opt.option_text || opt.text || ""
        })) || [],
        required: !!q.is_required,
        scaleMin: q.scale_min,
        scaleMax: q.scale_max,
        scaleMinLabel: q.scale_min_label,
        scaleMaxLabel: q.scale_max_label
    };
};

export const convertFromBackend = (survey: any): any => {
    if (!survey) return null;

    return {
        id: survey.survey_id,
        title: survey.title || "",
        description: survey.description || "",
        deadline: survey.deadline_date
            ? `${survey.deadline_date}T${(survey.deadline_time || '23:59:00').slice(0, 5)}`
            : "",
        category: survey.category || "その他",
        questions: survey.questions?.map((q: any, index: number) => convertQuestionFromBackend(q, index)) || [],
        respondents: survey.targeted_respondents?.map((r: any) => r.id) || []
    };
};
