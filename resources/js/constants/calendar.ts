import { computed, ref } from 'vue';
import { isDark } from '@/composables/useAppDark';
import axios from 'axios';

export const CATEGORY_COLORS = {
    '会議': '#42A5F5', // 2563eb blue-500 equivalent for consistency
    '業務': '#66BB6A', // 16a34a green-500
    '来客': '#FFA726', // ca8a04 yellow-500
    '出張・外出': '#9575CD', // 9333ea purple-500
    '休暇': '#F06292', // db2777 pink-500
    'その他': '#7F8C8D', // 686f7d gray-500
} as const;

export const DARK_CATEGORY_COLORS = {
    '会議': 'rgba(96, 165, 250, 0.5)', // blue-400/50
    '業務': 'rgba(74, 222, 128, 0.5)', // green-400/50
    '来客': 'rgba(251, 191, 36, 0.5)', // yellow-400/50
    '出張・外出': 'rgba(167, 139, 250, 0.5)', // purple-400/50
    '休暇': 'rgba(244, 114, 182, 0.5)', // pink-400/50
    'その他': 'rgba(156, 163, 175, 0.5)', // gray-400/50
} as const;

export type Category = keyof typeof CATEGORY_COLORS;

// カテゴリーラベルを動的に取得
export const categoryLabels = ref<Record<string, string>>({
    '会議': '会議',
    '業務': '業務',
    '来客': '来客',
    '出張・外出': '出張・外出',
    '休暇': '休暇',
    'その他': 'その他',
});

// カテゴリーラベルを読み込む
export const loadCategoryLabels = async () => {
    try {
        const response = await axios.get('/api/calendar/category-labels');
        categoryLabels.value = response.data;
    } catch (error) {
        console.error('Failed to load category labels:', error);
    }
};

export const CATEGORY_LABELS = computed(() => categoryLabels.value);

export const getEventColor = computed(() => (category: string): string => {
    const colors = isDark.value ? DARK_CATEGORY_COLORS : CATEGORY_COLORS;
    return colors[category as Category] || colors['その他'];
});

export const getCategoryItems = computed(() => {
    const colors = isDark.value ? DARK_CATEGORY_COLORS : CATEGORY_COLORS;
    return Object.entries(CATEGORY_LABELS.value).map(([key, label]) => ({
        label,
        color: colors[key as Category]
    }));
});

// 後方互換性のため
export const CATEGORY_ITEMS = computed(() => {
    const colors = CATEGORY_COLORS;
    return Object.entries(CATEGORY_LABELS.value).map(([key, label]) => ({
        label,
        color: colors[key as Category]
    }));
});

export const IMPORTANCE_LABELS = {
    '重要': { label: '重要', color: '#dc2626' }, // red-600
    '中': { label: '中', color: '#f59e0b' },     // amber-500
    '低': { label: '低', color: '#6b7280' },     // gray-500
} as const;

export const GENRE_FILTERS = {
    ALL: 'all',
    BLUE: 'blue',
    GREEN: 'green',
    YELLOW: 'yellow',
    PURPLE: 'purple',
    PINK: 'pink',
    OTHER: 'other',
} as const;
