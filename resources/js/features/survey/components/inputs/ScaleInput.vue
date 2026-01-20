<script setup lang="ts">
import { computed } from 'vue';
import { BaseInputProps } from './types';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const maxScale = computed(() => props.question.scaleMax || 5);
const minScale = computed(() => props.question.scaleMin || 1);

const range = computed(() => {
    const arr: number[] = [];
    for (let i = minScale.value; i <= maxScale.value; i++) {
        arr.push(i);
    }
    return arr;
});

const updateValue = (val: number) => {
    if (props.mode === 'read' || props.mode === 'preview') return;
    emit('update:modelValue', val);
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between text-sm text-gray-500 px-1">
            <span>{{ question.scaleMinLabel || minScale }}</span>
            <span>{{ question.scaleMaxLabel || maxScale }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="n in range"
                :key="n"
                type="button"
                @click="updateValue(n)"
                :disabled="mode === 'preview' || mode === 'read'"
                class="w-10 h-10 flex items-center justify-center rounded-full border transition-all"
                :class="[
                    n === Number(modelValue)
                        ? 'bg-blue-600 text-white border-blue-600 shadow-md'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400',
                    mode === 'edit' ? 'cursor-pointer' : 'cursor-default opacity-90'
                ]"
            >
                {{ n }}
            </button>
        </div>
    </div>
</template>
