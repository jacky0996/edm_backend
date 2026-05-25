#!/bin/sh
# Cloud Run 生產環境 entrypoint。
#
# 流程:
#   1. 把 nginx config 的 __PORT__ 替換成 Cloud Run 注入的 $PORT
#   2. 跑 Laravel migrate(讓 schema 跟 code 同步)
#   3. exec supervisord(同時起 nginx + php-fpm)

set -e

PORT=${PORT:-8080}
echo "[entrypoint] Configuring nginx to listen on ${PORT}..."
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/sites-available/default

# Cloud SQL 透過 unix socket,/cloudsql/<conn> 在容器啟動時就由 Cloud Run runtime 建好
# 但 Laravel migrate 偶爾撞到 socket 還沒就緒,做個輕量 retry
echo "[entrypoint] Running Laravel migrations..."
for i in $(seq 1 10); do
  if php artisan migrate --force; then
    echo "[entrypoint] Migrations OK"
    break
  fi
  echo "[entrypoint] migrate failed, retrying in 3s ($i/10)..."
  sleep 3
done

# storage / bootstrap/cache 在某些 redeploy 情境下 owner 會變,保險起見再修一次
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Starting supervisord (nginx + php-fpm)..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf -n
