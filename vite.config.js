import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            // ↓ Breeze が設定したエイリアス
            '@': '/resources/js', 
            // ↓ shadcn-vue のために追加 (tsconfig.json と合わせる)
            '@/Components': path.resolve(__dirname, 'resources/js/components'),
            '@/lib': path.resolve(__dirname, 'resources/js/lib'),
        },
    },
});
