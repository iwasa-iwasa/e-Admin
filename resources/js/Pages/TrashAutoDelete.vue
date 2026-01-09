<template>
    <div class="max-w-[1800px] mx-auto h-full p-6">
        <div class="h-full flex flex-col space-y-6">
            <!-- 設定カード -->
            <Card class="flex-shrink-0">
                <CardHeader>
                    <CardTitle>ゴミ箱自動削除設定</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateSetting">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                自動削除期間
                            </label>
                            <Select v-model="selectedPeriod">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in options" :key="value" :value="value">
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="mt-2 text-sm text-gray-600">
                                選択した期間を過ぎたゴミ箱のアイテムが自動的に完全削除されます
                            </p>
                        </div>
                        
                        <div class="flex items-center justify-end">
                            <Button type="submit" :disabled="processing">
                                {{ processing ? '更新中...' : '設定を更新' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- 実行ログカード -->
            <Card class="flex-1 min-h-0 flex flex-col">
                <CardHeader class="flex-shrink-0">
                    <CardTitle>自動削除実行ログ</CardTitle>
                </CardHeader>
                <CardContent class="flex-1 min-h-0">
                    <div v-if="uniqueLogs.length === 0" class="text-gray-500 text-center py-8">
                        まだ自動削除は実行されていません
                    </div>
                    <ScrollArea v-else class="h-full w-full">
                        <div class="rounded-md border">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            実行日時
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            設定期間
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            削除件数
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="log in uniqueLogs" :key="log.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatDate(log.executed_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ getPeriodLabel(log.period) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ log.deleted_count }}件
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ScrollArea } from '@/components/ui/scroll-area'

defineOptions({
  layout: AuthenticatedLayout,
})

const props = defineProps({
    currentSetting: String,
    options: Object,
    logs: Array
})

const selectedPeriod = ref(props.currentSetting)
const processing = ref(false)

// 重複を除外してユニークなログのみ表示
const uniqueLogs = ref(
    props.logs.filter((log, index, self) => 
        index === self.findIndex(l => 
            l.executed_at === log.executed_at && 
            l.period === log.period && 
            l.deleted_count === log.deleted_count
        )
    )
)

const updateSetting = () => {
    processing.value = true
    
    useForm({
        period: selectedPeriod.value
    }).post(route('trash.auto-delete.update'), {
        onFinish: () => {
            processing.value = false
        }
    })
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('ja-JP')
}

const getPeriodLabel = (period) => {
    const labels = {
        'disabled': '自動削除しない',
        '1_month': '1ヶ月後',
        '3_months': '3ヶ月後',
        '6_months': '6ヶ月後',
        '1_year': '1年後'
    }
    return labels[period] || period
}
</script>