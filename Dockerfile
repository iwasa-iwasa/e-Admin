# ベースイメージとしてPHP 8.2 + Apacheを使用
FROM php:8.2-apache

# 1. 必要なOSパッケージとPHP拡張モジュールのインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    git \
    curl \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# 2. Node.js (v20) のインストール (Viteのビルドに必要)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 3. Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. 作業ディレクトリの設定
WORKDIR /var/www/html

# 5. プロジェクトファイルをコンテナにコピー
COPY . /var/www/html

# 6. PHPの依存関係インストール
RUN composer install --no-dev --optimize-autoloader

# 7. フロントエンドの依存関係インストールとビルド
RUN npm install && npm run build

# 8. パーミッションの設定
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Apacheの設定 (DocumentRootをpublicに向ける & .htaccessを有効化)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# ここが追加箇所です：AllowOverride None を AllowOverride All に書き換える
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# 10. mod_rewriteを有効化 (Laravelのルーティングに必要)
RUN a2enmod rewrite

# ポート開放
EXPOSE 80

# サーバー起動 (マイグレーションを実行してからApacheを起動)
CMD sh -c "php artisan migrate --force --seed && apache2-foreground"