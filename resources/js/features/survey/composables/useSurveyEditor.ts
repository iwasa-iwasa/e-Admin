import { ref, watch } from 'vue';
import { Question, Survey } from '../domain/models';
import { QuestionTemplate, createQuestionFromTemplate } from '../domain/factory';

export function useSurveyEditor(initialData?: Partial<Survey>) {
    const title = ref(initialData?.title || '');
    const description = ref(initialData?.description || '');
    const deadline = ref(initialData?.deadline || '');
    const questions = ref<Question[]>(initialData?.questions ? JSON.parse(JSON.stringify(initialData.questions)) : []);
    const category = ref('その他');
    const respondents = ref<number[]>(initialData?.respondents && initialData.respondents.length > 0 ? [...initialData.respondents] : []);

    const addQuestion = (template: QuestionTemplate) => {
        questions.value.push(createQuestionFromTemplate(template));
    };

    const removeQuestion = (index: number) => {
        questions.value.splice(index, 1);
    };

    const updateQuestion = (index: number, newQuestion: Question) => {
        questions.value[index] = newQuestion;
    };

    // Validation (Simple version)
    const validate = () => {
        const errors: string[] = [];
        if (!title.value.trim()) errors.push('タイトルを入力してください');
        if (!deadline.value) errors.push('回答期限を設定してください');
        if (questions.value.length === 0) errors.push('質問を追加してください');

        questions.value.forEach((q, i) => {
            if (!q.question.trim()) errors.push(`質問 ${i + 1} の質問文を入力してください`);
            if (['single', 'multiple', 'dropdown'].includes(q.type)) {
                const validOptions = q.options.filter(o => {
                    return typeof o === 'string' ? o.trim() : (o.text || o.option_text || '').trim();
                });
                if (validOptions.length < 2) errors.push(`質問 ${i + 1} には最低2つの選択肢が必要です`);
            }
        });

        return errors;
    };

    const toggleRespondent = (id: number) => {
        const index = respondents.value.indexOf(id);
        if (index > -1) {
            respondents.value.splice(index, 1);
        } else {
            respondents.value.push(id);
        }
    };

    const toggleAllRespondents = (allIds: number[]) => {
        if (respondents.value.length === allIds.length) {
            respondents.value = [];
        } else {
            respondents.value = [...allIds];
        }
    };
    
    const initializeRespondents = (allIds: number[]) => {
        if (respondents.value.length === 0 && (!initialData?.respondents || initialData.respondents.length === 0)) {
            respondents.value = [...allIds];
        }
    };

    const toSurveyData = () => ({
        title: title.value,
        description: description.value,
        deadline: deadline.value,
        questions: questions.value.map(q => ({
            ...q,
            required: q.required
        })),
        category: category.value,
        respondents: respondents.value,
    });

    return {
        title,
        description,
        deadline,
        questions,
        category,
        respondents,
        addQuestion,
        removeQuestion,
        updateQuestion,
        validate,
        toSurveyData,
        toggleRespondent,
        toggleAllRespondents,
        initializeRespondents
    };
}
