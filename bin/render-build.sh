#!/usr/bin/env bash
# エラーが発生したら停止する設定
set -o errexit

# フロントエンドのビルド (Vue + Inertia + Vite)
npm install
npm run build

# バックエンドのセットアップ (Laravel)
composer install --no-dev --optimize-autoloader

# データベースマイグレーション (Forceオプションが必要)
php artisan migrate --force

# キャッシュクリアと最適化
php artisan config:cache
php artisan route:cache
php artisan view:cache