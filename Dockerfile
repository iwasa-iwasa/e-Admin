# ベースイメージとしてPHP 8.2 + Apacheを使用
FROM php:8.2-apache

# 1. 必要なOSパッケージのインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. PHP拡張モジュールのインストール（個別に）
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install exif
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install gd

# 3. Node.js (v20) のインストール (Viteのビルドに必要)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 4. Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. 作業ディレクトリの設定
WORKDIR /var/www/html

# 6. プロジェクトファイルをコンテナにコピー
COPY . /var/www/html

# 7. PHPの依存関係インストール（コメントアウト - 手動で実行）
# RUN composer install --no-dev --optimize-autoloader

# 8. フロントエンドの依存関係インストールとビルド（コメントアウト）
# RUN npm install --legacy-peer-deps && npm run build

# 9. パーミッションの設定
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# 10. Apacheの設定
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# 11. mod_rewriteを有効化
RUN a2enmod rewrite

# ポート開放
EXPOSE 80

# サーバー起動
CMD apache2-foreground
