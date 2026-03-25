#!/bin/sh

# 前往工作目錄
cd /var/www

# 修正 Git 偵測疑慮路徑的問題 (dubious ownership)
git config --global --add safe.directory /var/www

# 修正目錄擁有者為 www-data
echo "Ensuring file permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 安裝相依套件 (若 vendor 不存在或需要更新)
if [ ! -d "vendor" ]; then
  echo "Installing dependencies via composer..."
  # 以 root 身份執行但標明為安全，或使用 --no-dev 
  composer install --no-interaction --optimize-autoloader --no-dev --quiet
  # 安裝完後再次確認 vendor 權限
  chown -R www-data:www-data /var/www/vendor
fi

# 等待資料庫準備就緒 (db 為 docker-compose.yml 內之服務名稱)
echo "Waiting for database to be ready..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

# 執行資料庫遷移
php artisan migrate --force

# 啟動 php-fpm (此時 php-fpm 的設定檔通常會指定用 www-data 跑行程)
echo "Starting PHP-FPM..."
exec php-fpm
