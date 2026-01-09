export const CATEGORY_COLORS = {
    '会議': '#42A5F5', // blue-500 equivalent for consistency
    '業務': '#66BB6A', // green-500
    '来客': '#FFA726', // yellow-500
    '出張': '#9575CD', // purple-500
    '休暇': '#F06292', // pink-500
    'その他': '#7F8C8D', // gray-500
} as const;

export type Category = keyof typeof CATEGORY_COLORS;

export const CATEGORY_LABELS = {
    '会議': '会議',
    '業務': '業務',
    '来客': '来客',
    '出張': '出張',
    '休暇': '休暇',
    'その他': 'その他',
} as const;

export const getEventColor = (category: string): string => {
    return CATEGORY_COLORS[category as Category] || CATEGORY_COLORS['その他'];
};

export const CATEGORY_ITEMS = Object.entries(CATEGORY_LABELS).map(([key, label]) => ({
    label,
    color: CATEGORY_COLORS[key as Category]
}));

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
