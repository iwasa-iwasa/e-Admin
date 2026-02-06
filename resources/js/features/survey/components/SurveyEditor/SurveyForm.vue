<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus } from 'lucide-vue-next';
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
    toggleAllRespondents
} = useSurveyEditor(props.initialData);

const isAllSelected = computed(() => {
     return props.teamMembers && respondents.value.length === props.teamMembers.length;
});

const showTemplateDialog = ref(false);
const deadlineDateTime = ref<Date | null>(null);

// Initialize deadlineDateTime from deadline
watch(() => props.initialData, (data) => {
    if (data?.deadline) {
        deadlineDateTime.value = new Date(data.deadline)
    }
}, { immediate: true })

watch(deadlineDateTime, (newDate) => {
    if (newDate) {
        deadline.value = newDate.toISOString().slice(0, 16)
    } else {
        deadline.value = ''
    }
})

const handleAddQuestion = (template: QuestionTemplate) => {
    addQuestion(template);
    showTemplateDialog.value = false;
};

// Expose methods for the parent (Dialog) to call
defineExpose({
    validate,
    getData: toSurveyData
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
                        <Select v-model="category">
                            <SelectTrigger id="category">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="イベント">イベント</SelectItem>
                                <SelectItem value="備品">備品</SelectItem>
                                <SelectItem value="システム">システム</SelectItem>
                                <SelectItem value="オフィス環境">オフィス環境</SelectItem>
                                <SelectItem value="会議">会議</SelectItem>
                                <SelectItem value="その他">その他</SelectItem>
                            </SelectContent>
                        </Select>
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
                                <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
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
