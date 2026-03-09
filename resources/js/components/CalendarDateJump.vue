<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { ja } from 'date-fns/locale'
import '@vuepic/vue-datepicker/dist/main.css'

const props = defineProps<{
    viewMode: string
    currentDate: Date
    currentYear: number
}>()

const emit = defineEmits<{
    jump: [date: Date]
}>()

const showPicker = ref(false)
const internalValue = ref<Date | number | null>(null)

// 表示モードに応じたピッカー設定
const pickerConfig = computed(() => {
    switch (props.viewMode) {
        case 'yearView':
            return { yearPicker: true, monthPicker: false, weekPicker: false }
        case 'dayGridMonth':
            return { yearPicker: false, monthPicker: true, weekPicker: false }
        case 'timeGridWeek':
            return { yearPicker: false, monthPicker: false, weekPicker: true }
        default:
            return { yearPicker: false, monthPicker: false, weekPicker: false }
    }
})

// 現在値の初期化
watch(() => props.viewMode, () => {
    if (props.viewMode === 'yearView') {
        internalValue.value = props.currentYear
    } else {
        internalValue.value = new Date(props.currentDate)
    }
}, { immediate: true })

watch(() => [props.currentDate, props.currentYear], () => {
    if (!showPicker.value) {
        if (props.viewMode === 'yearView') {
            internalValue.value = props.currentYear
        } else {
            internalValue.value = new Date(props.currentDate)
        }
    }
})

// 日付選択時の処理
const handleSelect = (value: any) => {
    if (!value) return
    
    let jumpDate: Date
    
    if (props.viewMode === 'yearView') {
        // 年選択: { year: 2024 } 形式
        const year = typeof value === 'object' && 'year' in value ? value.year : value
        jumpDate = new Date(year, 0, 1)
    } else if (props.viewMode === 'dayGridMonth') {
        // 月選択: { month: 0, year: 2024 } 形式
        if (typeof value === 'object' && 'month' in value && 'year' in value) {
            jumpDate = new Date(value.year, value.month, 1)
        } else {
            jumpDate = new Date(value)
        }
    } else if (props.viewMode === 'timeGridWeek') {
        // 週選択: [startDate, endDate] 形式
        jumpDate = Array.isArray(value) ? new Date(value[0]) : new Date(value)
    } else {
        // 日選択
        jumpDate = new Date(value)
    }
    
    emit('jump', jumpDate)
    showPicker.value = false
}

const handleClickOutside = () => {
    showPicker.value = false
}
</script>

<template>
    <div class="relative">
        <div 
            class="cursor-pointer hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
            @click="showPicker = !showPicker"
            :title="viewMode === 'yearView' ? '年を選択' : viewMode === 'dayGridMonth' ? '月を選択' : viewMode === 'timeGridWeek' ? '週を選択' : '日を選択'"
        >
            <slot />
        </div>
        
        <div 
            v-if="showPicker" 
            class="absolute z-50 mt-2 left-1/2 -translate-x-1/2 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700"
        >
            <VueDatePicker
                :model-value="internalValue"
                @update:model-value="handleSelect"
                :year-picker="pickerConfig.yearPicker"
                :month-picker="pickerConfig.monthPicker"
                :week-picker="pickerConfig.weekPicker"
                :locale="ja"
                :week-start="0"
                inline
                auto-apply
                :enable-time-picker="false"
                :clearable="false"
            />
        </div>
        
        <div 
            v-if="showPicker" 
            class="fixed inset-0 z-40"
            @click="handleClickOutside"
        />
    </div>
</template>

<style scoped>
/* VueDatePicker のダークモード対応 */
:deep(.dp__theme_dark) {
    --dp-background-color: theme('colors.gray.800');
    --dp-text-color: theme('colors.gray.100');
    --dp-hover-color: theme('colors.gray.700');
    --dp-hover-text-color: theme('colors.gray.100');
    --dp-hover-icon-color: theme('colors.gray.100');
    --dp-primary-color: theme('colors.blue.600');
    --dp-primary-text-color: theme('colors.white');
    --dp-secondary-color: theme('colors.gray.700');
    --dp-border-color: theme('colors.gray.700');
    --dp-menu-border-color: theme('colors.gray.700');
    --dp-border-color-hover: theme('colors.gray.600');
    --dp-disabled-color: theme('colors.gray.600');
    --dp-scroll-bar-background: theme('colors.gray.700');
    --dp-scroll-bar-color: theme('colors.gray.600');
    --dp-success-color: theme('colors.green.600');
    --dp-success-color-disabled: theme('colors.green.800');
    --dp-icon-color: theme('colors.gray.400');
    --dp-danger-color: theme('colors.red.600');
    --dp-highlight-color: theme('colors.blue.600');
}
</style>
