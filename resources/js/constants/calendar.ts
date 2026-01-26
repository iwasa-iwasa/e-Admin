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

export const GENRE_COLORS = {
    blue: {
        id: 'blue',
        label: '会議',
        hex: '#3b82f6', // Updated to match CreateNoteDialog/SharedNotes preference closer if needed, or stick to Calendar? NoteDialog used #3b82f6, Calendar used #42A5F5. #3b82f6 is tailwind blue-500. #42A5F5 is blue-400. I'll use blue-500 #3b82f6 for consistency with standard tailwind palette often used.
        bg: 'bg-blue-100',
        noteClass: 'bg-blue-100 border-blue-300 dark:bg-card dark:border-blue-600',
    },
    green: {
        id: 'green',
        label: '業務',
        hex: '#66bb6a',
        bg: 'bg-green-100',
        noteClass: 'bg-green-100 border-green-300 dark:bg-card dark:border-green-600',
    },
    yellow: {
        id: 'yellow',
        label: '来客',
        hex: '#ffa726',
        bg: 'bg-yellow-100',
        noteClass: 'bg-yellow-100 border-yellow-300 dark:bg-card dark:border-yellow-600',
    },
    purple: {
        id: 'purple',
        label: '出張',
        hex: '#9575cd',
        bg: 'bg-purple-100',
        noteClass: 'bg-purple-100 border-purple-300 dark:bg-card dark:border-purple-600',
    },
    pink: {
        id: 'pink',
        label: '休暇',
        hex: '#f06292',
        bg: 'bg-pink-100',
        noteClass: 'bg-pink-100 border-pink-300 dark:bg-card dark:border-pink-600',
    },
    gray: {
        id: 'gray',
        label: 'その他',
        hex: '#9e9e9e', // CreateNoteDialog used #9e9e9e
        bg: 'bg-gray-100',
        noteClass: 'bg-gray-100 border-gray-300 dark:bg-card dark:border-gray-600',
    },
} as const;

export type GenreColorKey = keyof typeof GENRE_COLORS;

export const getGenreColor = (color: string) => {
    return GENRE_COLORS[color as GenreColorKey] || GENRE_COLORS['gray'];
};

export const GENRE_ITEMS = Object.values(GENRE_COLORS);
