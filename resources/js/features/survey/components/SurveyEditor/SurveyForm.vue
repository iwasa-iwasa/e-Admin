<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import QuestionEditor from './QuestionEditor.vue';
import { useSurveyEditor } from '../../composables/useSurveyEditor';
import { questionTemplates, QuestionTemplate } from '../../domain/factory';
import { Survey } from '../../domain/models';
import { ja } from "date-fns/locale";
import '@vuepic/vue-datepicker/dist/main.css';
import { VueDatePicker } from '@vuepic/vue-datepicker';

const props = defineProps<{
    initialData?: Survey;
    teamMembers?: Array<{id: number, name: string}>;
}>();

const {
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
} = useSurveyEditor(props.initialData);

const isAllSelected = computed(() => {
     return props.teamMembers && respondents.value.length === props.teamMembers.length;
});

const showTemplateDialog = ref(false);
const deadlineDateTime = ref<Date | null>(null);
const categoryInput = ref('');
const categories = ref<string[]>([]);

// Initialize categories from initialData
watch(() => props.initialData, (data) => {
    console.log('SurveyForm: initialData changed', data)
    if (data?.deadline) {
        deadlineDateTime.value = new Date(data.deadline)
    }
    if (data?.categories && Array.isArray(data.categories)) {
        categories.value = [...data.categories]
    } else if (data?.category) {
        categories.value = [data.category]
    }
    // 回答者を更新
    if (data?.respondents && Array.isArray(data.respondents) && data.respondents.length > 0) {
        console.log('SurveyForm: Setting respondents to', data.respondents)
        respondents.value = [...data.respondents]
    } else {
        console.log('SurveyForm: No respondents in initialData', data?.respondents)
    }
}, { immediate: true })

// Initialize respondents with all team members if creating new survey
watch(() => props.teamMembers, (members) => {
    if (members && members.length > 0 && !props.initialData) {
        initializeRespondents(members.map(m => m.id))
    }
}, { immediate: true })

watch(deadlineDateTime, (newDate) => {
    if (newDate) {
        deadline.value = newDate.toISOString().slice(0, 16)
    } else {
        deadline.value = ''
    }
})

const handleAddCategory = () => {
    if (categoryInput.value.trim() && !categories.value.includes(categoryInput.value.trim())) {
        categories.value.push(categoryInput.value.trim());
        categoryInput.value = '';
    }
};

const handleRemoveCategory = (catToRemove: string) => {
    categories.value = categories.value.filter((cat) => cat !== catToRemove);
};

const handleAddQuestion = (template: QuestionTemplate) => {
    addQuestion(template);
    showTemplateDialog.value = false;
};

// Expose methods for the parent (Dialog) to call
defineExpose({
    validate,
    getData: () => ({
        ...toSurveyData(),
        categories: categories.value
    }),
    restoreRespondents: (originalRespondents: number[]) => {
        respondents.value = [...originalRespondents];
    }
});
</script>

<template>
    <div class="space-y-6 pb-6">
        <!-- Basic Info -->
        <Card>
            <CardHeader>
                <CardTitle>基本情報</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="title">アンケートタイトル <span class="text-red-500">*</span></Label>
                    <Input id="title" placeholder="例：2025年度 忘年会の候補日アンケート" v-model="title" />
                </div>
                <div class="space-y-2">
                    <Label for="description">説明</Label>
                    <Textarea id="description" placeholder="アンケートの目的や注意事項を入力..." v-model="description" class="min-h-[80px]" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="category">カテゴリ</Label>
                        <div class="flex gap-2">
                            <Input
                                id="category"
                                placeholder="カテゴリを追加"
                                v-model="categoryInput"
                                @keypress.enter.prevent="handleAddCategory"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                @click="handleAddCategory"
                            >
                                追加
                            </Button>
                        </div>
                        <div
                            v-if="categories.length > 0"
                            class="flex flex-wrap gap-2 mt-2"
                        >
                            <Badge
                                v-for="cat in categories"
                                :key="cat"
                                variant="secondary"
                                class="gap-2"
                            >
                                {{ cat }}
                                <button @click="handleRemoveCategory(cat)">
                                    <X class="h-3 w-3" />
                                </button>
                            </Badge>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="deadline">回答期限 <span class="text-red-500">*</span></Label>
                        <VueDatePicker
                            v-model="deadlineDateTime"
                            :locale="ja"
                            :week-start="0"
                            auto-apply
                            teleport-center
                            enable-time-picker
                            placeholder="回答期限を選択"
                        />
                    </div>
                </div>
                
                <div v-if="teamMembers" class="space-y-2">
                    <Label>回答者選択</Label>
                    <div class="border border-gray-300 rounded-md p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">選択された回答者: {{ respondents.length }}/{{ teamMembers.length }}名</span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="toggleAllRespondents(teamMembers?.map(m => m.id) || [])"
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
                                    respondents.includes(member.id)
                                        ? 'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700 hover:bg-blue-200 dark:hover:bg-blue-900/50'
                                        : 'hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-gray-100'
                                ]"
                            >
                                {{ member.name }}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Questions List -->
        <div class="space-y-4">
            <template v-for="(q, index) in questions" :key="q.id">
                <QuestionEditor
                    :model-value="q"
                    :index="index"
                    @update:model-value="updateQuestion(index, $event)"
                    @remove="removeQuestion(index)"
                />
            </template>

            <!-- Empty State / Add Button -->
            <Card v-if="questions.length === 0" class="border-dashed border-2">
                <CardContent class="py-12 text-center">
                    <div class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 cursor-pointer hover:bg-blue-100 transition-colors" @click="showTemplateDialog = true">
                        <Plus class="h-8 w-8 text-blue-600" />
                    </div>
                    <h3 class="mb-2 font-medium">質問を追加しましょう</h3>
                    <p class="text-sm text-gray-500 mb-6">下のボタンから質問形式を選択して追加できます</p>
                    <Button @click="showTemplateDialog = true" class="gap-2">
                        <Plus class="h-4 w-4" />
                        新しい質問を追加
                    </Button>
                </CardContent>
            </Card>
            <Button v-else variant="outline" @click="showTemplateDialog = true" class="w-full gap-2 border-dashed">
                <Plus class="h-4 w-4" />
                新しい質問を追加
            </Button>
        </div>

        <!-- Template Dialog -->
        <Dialog v-model:open="showTemplateDialog">
            <DialogContent class="max-w-3xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>質問形式を選択</DialogTitle>
                    <DialogDescription>作成したい質問の形式を選択してください</DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <Card v-for="template in questionTemplates" :key="template.type" class="cursor-pointer hover:shadow-md hover:border-blue-500 transition-all border-2 border-transparent" @click="handleAddQuestion(template)">
                        <CardContent class="p-6">
                            <div class="flex items-start gap-4">
                                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                                    <component :is="template.icon" class="h-6 w-6" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="mb-2 font-bold">{{ template.name }}</h3>
                                    <p class="text-sm text-gray-600">{{ template.description }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
