<script setup lang="ts">
import { computed } from 'vue';
import { BaseInputProps } from './types';
import { Star } from 'lucide-vue-next';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const maxStars = computed(() => props.question.scaleMax || 5);

const updateValue = (val: number) => {
    if (props.mode === 'read' || props.mode === 'preview') return;
    emit('update:modelValue', val);
};
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="n in maxStars"
            :key="n"
            type="button"
            @click="updateValue(n)"
            :disabled="mode === 'preview' || mode === 'read'"
            class="focus:outline-none transition-transform active:scale-95"
            :class="{
                'cursor-default': mode === 'read' || mode === 'preview',
                'cursor-pointer hover:scale-110': mode === 'edit'
            }"
        >
            <Star
                class="h-8 w-8 transition-colors"
                :class="n <= (Number(modelValue) || 0)
                    ? 'text-yellow-400 fill-yellow-400'
                    : 'text-gray-300'"
            />
        </button>
        <span v-if="mode === 'read'" class="ml-2 text-sm text-gray-500">
            ({{ modelValue || 0 }} / {{ maxStars }})
        </span>
    </div>
</template>
