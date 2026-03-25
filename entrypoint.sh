#!/bin/sh

# 前往工作目錄
cd /var/www

# 修正 Git 偵測疑慮路徑的問題 (dubious ownership)
git config --global --add safe.directory /var/www

# 修正目錄擁有者為 www-data
echo "Ensuring file permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 強制執行相依套件安裝 (確保與 composer.lock 同步)
echo "Syncing dependencies via composer..."
composer install --no-interaction --optimize-autoloader --no-dev --quiet
# 安裝完後再次確認 vendor 權限
chown -R www-data:www-data /var/www/vendor

# 等待資料庫準備就緒 (db 為 docker-compose.yml 內之服務名稱)
echo "Waiting for database to be ready..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

# 執行資料庫遷移
php artisan migrate --force

# 啟動 PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm
