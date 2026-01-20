<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { BaseInputProps } from './types';

defineProps<BaseInputProps>();
defineEmits(['update:modelValue']);

const formatDate = (val: string) => {
    if (!val) return '';
    return new Date(val).toLocaleString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <div class="w-full">
        <template v-if="mode === 'read'">
            <div class="p-3 bg-gray-50 rounded border text-gray-700">
                {{ modelValue ? formatDate(String(modelValue)) : '(未回答)' }}
            </div>
        </template>
        <template v-else>
            <Input
                type="datetime-local"
                step="60"
                :model-value="modelValue"
                @update:model-value="$emit('update:modelValue', $event)"
                :disabled="mode === 'preview'"
                class="w-full"
            />
        </template>
    </div>
</template>
