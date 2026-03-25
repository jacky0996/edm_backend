# 使用 PHP 8.2 FPM 作為基礎映像
FROM php:8.2-fpm

# 設定工作目錄
WORKDIR /var/www

# 安裝系統依賴與 PHP 擴充
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    netcat-openbsd

# 清理快取
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝 PHP 擴充
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 複製現有的應用程式目錄內容
COPY . /var/www

# 複製現有的應用程式目錄權限
COPY --chown=www-data:www-data . /var/www

# 設定 entrypoint 腳本並給予執行權限
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 切換到 www-data 用戶
USER www-data

# 暴露 9000 端口
EXPOSE 9000

# 使用 entrypoint 啟動
ENTRYPOINT ["entrypoint.sh"]
