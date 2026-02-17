<script setup lang="ts">
import { computed } from 'vue';
import { Question, QuestionType } from '../../domain/models';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { GripVertical, Trash2 } from "lucide-vue-next";
import OptionEditor from './OptionEditor.vue';
import * as Inputs from '../inputs';
import { questionTemplates } from '../../domain/factory';

const props = defineProps<{
    modelValue: Question;
    index: number;
}>();

const emit = defineEmits(['update:modelValue', 'remove']);

const question = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const InputComponent = computed(() => {
    switch (question.value.type) {
        case 'single': return Inputs.SingleChoice;
        case 'multiple': return Inputs.MultipleChoice;
        case 'text': return Inputs.TextInput;
        case 'textarea': return Inputs.TextareaInput;
        case 'rating': return Inputs.RatingInput;
        case 'scale': return Inputs.ScaleInput;
        case 'dropdown': return Inputs.DropdownInput;
        case 'date': return Inputs.DateInput;
        default: return Inputs.TextInput;
    }
});

const typeLabel = computed(() => {
    const t = questionTemplates.find(t => t.type === question.value.type);
    return t ? t.name : question.value.type;
});

const hasOptions = computed(() => ['single', 'multiple', 'dropdown'].includes(question.value.type));
const isRating = computed(() => question.value.type === 'rating');
const isScale = computed(() => question.value.type === 'scale');

// Safe update helpers
const updateField = (field: keyof Question, val: any) => {
    console.log(`QuestionEditor: updateField called - field: ${field}, value:`, val);
    const updated = { ...question.value, [field]: val };
    console.log('QuestionEditor: emitting updated question:', updated);
    emit('update:modelValue', updated);
};
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-base flex items-center gap-2 drag-handle cursor-grab active:cursor-grabbing">
                    <GripVertical class="h-4 w-4 text-gray-400" />
                    質問 {{ index + 1 }}
                    <span class="text-xs font-normal text-gray-500 ml-2 py-1 px-2 bg-gray-100 rounded">
                        {{ typeLabel }}
                    </span>
                </CardTitle>
                <Button variant="ghost" size="sm" @click="$emit('remove')" type="button">
                    <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-500" />
                </Button>
            </div>
        </CardHeader>

        <CardContent class="space-y-6">
            <!-- Question Text -->
            <div class="space-y-2">
                <Label>質問文 <span class="text-red-500">*</span></Label>
                <Input
                    :model-value="question.question"
                    @update:model-value="updateField('question', $event)"
                    placeholder="質問を入力してください"
                    class="font-medium"
                />
            </div>

            <!-- Configuration: Options -->
            <div v-if="hasOptions">
                <OptionEditor
                    :model-value="question.options"
                    @update:model-value="updateField('options', $event)"
                    :type="question.type === 'single' ? 'single' : question.type === 'multiple' ? 'multiple' : 'dropdown'"
                />
            </div>

            <!-- Configuration: Rating/Scale -->
            <div v-if="isRating" class="grid grid-cols-1 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="space-y-2">
                    <Label>星の数（3〜15）</Label>
                    <Input
                        type="number"
                        min="3"
                        max="15"
                        :model-value="question.scaleMax || 5"
                        @update:model-value="updateField('scaleMax', Number($event))"
                    />
                </div>
            </div>

            <div v-if="isScale" class="grid grid-cols-1 gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="space-y-2">
                    <Label>段階数（2〜10）</Label>
                    <Input
                        type="number"
                        min="2"
                        max="10"
                        :model-value="question.scaleMax || 5"
                        @update:model-value="updateField('scaleMax', Number($event))"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                         <Label>最小値ラベル</Label>
                         <Input
                            :model-value="question.scaleMinLabel || ''"
                            @update:model-value="updateField('scaleMinLabel', $event)"
                            placeholder="例: とても悪い"
                         />
                    </div>
                    <div class="space-y-2">
                         <Label>最大値ラベル</Label>
                         <Input
                            :model-value="question.scaleMaxLabel || ''"
                            @update:model-value="updateField('scaleMaxLabel', $event)"
                            placeholder="例: とても良い"
                         />
                    </div>
                </div>
            </div>

            <!-- Preview & Settings Footer -->
            <div class="flex items-start justify-between border-t pt-4 gap-4">
                 <!-- Live Preview -->
                 <div class="flex-1 space-y-2">
                    <Label class="text-xs text-gray-500">プレビュー</Label>
                    <div :class="{'opacity-50': !question.options?.length && hasOptions}">
                        <component 
                            :is="InputComponent" 
                            :question="question" 
                            mode="preview"
                            :model-value="isRating || isScale ? Math.ceil((question.scaleMax||5)/2) : null" 
                        />
                    </div>
                 </div>

                 <!-- Basic Settings -->
                 <div class="flex items-center space-x-2 pt-6">
                    <div 
                        class="flex items-center space-x-2 cursor-pointer"
                        @click="() => {
                            console.log('Container clicked, toggling required from:', question.required);
                            updateField('required', !question.required);
                        }"
                    >
                        <Checkbox
                            :id="`req-${question.id}`"
                            :checked="question.required"
                        />
                        <Label :for="`req-${question.id}`" class="text-sm whitespace-nowrap">
                            必須にする
                        </Label>
                    </div>
                 </div>
            </div>
        </CardContent>
    </Card>
</template>
