<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { ja } from 'date-fns/locale';
import '@vuepic/vue-datepicker/dist/main.css';
import { BaseInputProps } from './types';

const props = defineProps<BaseInputProps>();
const emit = defineEmits(['update:modelValue']);

const dateValue = ref<Date | null>(props.modelValue ? new Date(props.modelValue as string) : null);

watch(() => props.modelValue, (newVal) => {
    dateValue.value = newVal ? new Date(newVal as string) : null;
});

watch(dateValue, (newVal) => {
    if (newVal) {
        emit('update:modelValue', newVal.toISOString().slice(0, 16));
    } else {
        emit('update:modelValue', '');
    }
});

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
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded border dark:border-gray-700 text-gray-700 dark:text-gray-300">
                {{ modelValue ? formatDate(String(modelValue)) : '(未回答)' }}
            </div>
        </template>
        <template v-else>
            <VueDatePicker
                v-model="dateValue"
                enable-time-picker
                placeholder="日時を選択"
                :locale="ja"
                :week-start="0"
                auto-apply
                :clearable="true"
                :teleport="true"
                :disabled="mode === 'preview'"
            />
        </template>
    </div>
</template>
