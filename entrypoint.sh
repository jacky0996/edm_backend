#!/bin/sh

# 前往工作目錄
cd /var/www

# 修正 Git 偵測疑慮路徑的問題 (dubious ownership)
git config --global --add safe.directory /var/www

# 檢查 .env 是否存在，若不存在則主動建立
if [ ! -f ".env" ]; then
  echo "Missing .env file, generating default environment config..."
  cat > .env <<'EOF'
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:PIXXS+rR1dq70HfK2CS4gZ9Zmn/2PjSwlNEeGFDiQCY=
APP_DEBUG=true
APP_URL=http://localhost:81

LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
LOG_STACK=daily
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=edm_db
DB_USERNAME=developer
DB_PASSWORD='wiN]d^yGwwZ$I_&N!Q0n'

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

L5_SWAGGER_CONST_HOST=http://localhost:81/api
EOF
  # 如果金鑰為空，則手動觸發生成 (防止拷貝後沒 Key 會 500)
  if grep -q "APP_KEY=$" .env; then
    php artisan key:generate
  fi
fi

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
