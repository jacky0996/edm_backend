#!/bin/sh

# 前往工作目錄
cd /var/www

# 安裝相依套件 (若 vendor 不存在或需要更新)
if [ ! -d "vendor" ]; then
  composer install --no-interaction --optimize-autoloader
fi

# 等待資料庫準備就緒 (db 為 docker-compose.yml 內之服務名稱)
echo "Waiting for database to be ready..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

# 執行資料庫遷移
php artisan migrate --force

# 啟動 php-fpm
exec php-fpm
