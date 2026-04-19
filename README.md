# EDM Backend

Laravel 12 + PHP 8.2-FPM 打造的 **電子郵件行銷 (EDM) 後端服務**，負責會員/群組/活動管理、活動邀請信發送、Google Forms 問卷整合，以及 SSO JWT 驗證。

---

## 目錄

- [技術棧](#技術棧)
- [服務架構](#服務架構)
- [專案結構](#專案結構)
- [快速啟動 (Docker)](#快速啟動-docker)
- [環境變數](#環境變數)
- [API 路由](#api-路由)
- [資料模型](#資料模型)
- [背景任務 (Queue)](#背景任務-queue)
- [中介層 (Middleware)](#中介層-middleware)
- [開發工具](#開發工具)
- [常用指令](#常用指令)

---

## 技術棧

| 類別 | 技術 |
|---|---|
| 語言 / 框架 | PHP 8.2、Laravel 12 |
| 資料庫 | MySQL 8.4 |
| Web Server | Nginx (alpine) |
| 容器 | Docker / Docker Compose |
| 認證 | Firebase JWT (HS256) |
| 郵件 | AWS SES (`aws/aws-sdk-php`) |
| 第三方整合 | Google API Client (Forms / Sheets / Drive) |
| API 文件 | L5 Swagger |
| 除錯 | Laravel Telescope、Pail |

---

## 服務架構

Docker Compose 啟動三個服務，使用 `backend` bridge network 互通：

```
┌────────────────────┐    ┌────────────────────┐    ┌────────────────────┐
│   edm-nginx        │    │  edm-backend-app   │    │     edm-db         │
│   nginx:alpine     │───►│  php:8.2-fpm       │───►│   mysql:8.4        │
│   host :81 → :80   │    │  FastCGI :9000     │    │   host :3307 → :3306│
└────────────────────┘    └────────────────────┘    └────────────────────┘
```

| 容器 | 角色 | Host Port | 容器內 Port |
|---|---|---|---|
| `edm-nginx` | 反向代理，`fastcgi_pass app:9000` | **81** | 80 |
| `edm-backend-app` | Laravel App (PHP-FPM) | — | 9000 |
| `edm-db` | MySQL 資料庫 | **3307** | 3306 |

> **Port 配置說明**：Laravel App 容器透過 Docker 內部網路以 `db:3306` 連線資料庫，所以 `.env` 內 `DB_PORT` 保持 `3306`。Host 上的 `3307` 僅提供本機 DB 客戶端 (TablePlus、Navicat 等) 存取使用，避開 host 既有的 3306 服務。

---

## 專案結構

```
app/
├── Console/              # Artisan 自訂指令
├── Http/
│   ├── Controllers/EDM/  # EDM 四個主控制器 (Member / Group / Event / Mail)
│   └── Middleware/       # AuthorizeJwt, WhitelistIpMiddleware
├── Jobs/Common/          # SendAwsMailJob (佇列寄信)
├── Libraries/            # 共用工具
├── Models/
│   ├── EDM/              # Member / Group / Event / EventRelation / Image / Emails / Mobiles / Organization
│   ├── Google/           # GoogleForm / GoogleFormResponse / GoogleFormStat
│   └── Meeting/          # MeetingUser
├── Presenters/           # 資料輸出格式化
├── Providers/            # Laravel ServiceProvider
├── Repositories/         # 資料存取層
└── Services/             # AwsSesService, GoogleApiService, UserService

bootstrap/app.php         # Laravel 12 統一設定入口
config/
├── sso.php               # SSO 相關 (HWS_VERIFY_URL, IP 白名單, 前端 URL)
├── l5-swagger.php        # Swagger 設定
└── telescope.php         # Telescope 設定
database/migrations/      # 17 張資料表 schema
docker-compose/
├── nginx/                # Nginx 設定檔 (default.conf / prod.conf)
└── mysql/init.sql        # 資料庫初始化 (建立 edm_db 與 developer 帳號)
routes/
├── Api/edm.php           # 所有 API 路由 (prefix: /api/edm)
├── web.php               # Web 首頁
└── console.php           # Artisan 排程
Dockerfile                # PHP 8.2-FPM 映像 (含 gd, pdo_mysql, zip 等擴充)
entrypoint.sh             # 啟動時自動產生 .env、composer install、migrate、啟動 FPM
docker-compose.yml        # 基礎服務定義
docker-compose.local.yml  # 本地開發 override (APP_DEBUG=true, host.docker.internal)
docker-compose.prod.yml   # 生產環境 override (APP_ENV=production, 不對外暴露 DB)
```

---

## 快速啟動 (Docker)

### 本地開發

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

啟動流程 (由 [`entrypoint.sh`](entrypoint.sh) 執行)：

1. 若無 `.env`，自動產生預設本地開發配置
2. 修正 `storage`、`bootstrap/cache`、`vendor` 權限為 `www-data`
3. 執行 `composer install` 同步依賴
4. 等待 `db:3306` 可連線 (使用 `nc` 輪詢)
5. 執行 `php artisan migrate --force`
6. 啟動 PHP-FPM

完成後可存取：

| 服務 | 網址 |
|---|---|
| API Root | http://localhost:81 |
| Swagger 文件 | http://localhost:81/api/documentation |
| Health Check | http://localhost:81/up |
| Laravel Telescope | http://localhost:81/telescope |
| MySQL (給 DB IDE) | `localhost:3307` / 帳號 `developer` |

### 生產環境

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

差異：
- Nginx 使用 `prod.conf`，映射到 host `:80`
- MySQL 不對外暴露 port (僅 Docker 內部訪問)
- `APP_ENV=production`、`APP_DEBUG=false`、`restart: always`

### 停止 / 移除

```bash
# 停止 (保留容器與資料)
docker compose -f docker-compose.yml -f docker-compose.local.yml stop

# 停止並移除容器 (保留 DB volume)
docker compose -f docker-compose.yml -f docker-compose.local.yml down

# 完全清除 (含 DB 資料)
docker compose -f docker-compose.yml -f docker-compose.local.yml down -v
```

---

## 環境變數

[`.env.example`](.env.example) 提供完整模板。關鍵變數：

| 變數 | 說明 | 本地預設值 |
|---|---|---|
| `APP_URL` | 應用程式網址 | `http://localhost:81` |
| `DB_HOST` | 資料庫主機 (Docker 內部 service name) | `db` |
| `DB_PORT` | 資料庫 port (容器內部，固定 3306) | `3306` |
| `DB_DATABASE` | 資料庫名稱 | `edm_db` |
| `DB_USERNAME` / `DB_PASSWORD` | 由 [`init.sql`](docker-compose/mysql/init.sql) 建立的 `developer` 帳號 | — |
| `QUEUE_CONNECTION` | 佇列驅動 | `database` |
| `CACHE_STORE` | 快取驅動 | `database` |
| `HWS_VERIFY_URL` | HWS 核心系統 SSO 驗證 API | `https://uathws.hwacom.com/api/sso/verify-token` |
| `ALLOWED_EDM_IPS` | API 白名單 IP (逗號分隔，`*` 代表不限) | `*` |
| `EDM_FRONTEND_URL` | 允許的前端來源 (CORS) | `https://uatedm.hwacom.com` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` | AWS SES 寄信憑證 | — |

---

## API 路由

所有 EDM API 以 `/api/edm` 為前綴，**統一使用 POST**。定義於 [`routes/Api/edm.php`](routes/Api/edm.php)。

### Member (`/api/edm/member`)

| Method | Path | Controller | 說明 |
|---|---|---|---|
| POST | `/list` | `MemberController@list` | 會員列表 |
| POST | `/view` | `MemberController@view` | 會員詳細 |
| POST | `/add` | `MemberController@add` | 新增會員 |
| POST | `/editStatus` | `MemberController@editStatus` | 修改狀態 |
| POST | `/editEmail` | `MemberController@editEmail` | 修改信箱 |
| POST | `/editMobile` | `MemberController@editMobile` | 修改手機 |
| POST | `/editSales` | `MemberController@editSales` | 修改業務歸屬 |

### Group (`/api/edm/group`)

| Method | Path | Controller | 說明 |
|---|---|---|---|
| POST | `/list` | `GroupController@list` | 群組列表 |
| POST | `/view` | `GroupController@view` | 群組詳細 (含成員) |
| POST | `/create` | `GroupController@create` | 建立群組 |
| POST | `/editStatus` | `GroupController@editStatus` | 修改群組狀態 |
| POST | `/getEventList` | `GroupController@getEventList` | 取得群組曾參與的活動 |

### Event (`/api/edm/event`)

| Method | Path | Controller | 說明 |
|---|---|---|---|
| POST | `/list` | `EventController@list` | 活動列表 |
| POST | `/view` | `EventController@view` | 活動詳細 |
| POST | `/create` | `EventController@create` | 建立活動 |
| POST | `/update` | `EventController@update` | 更新活動 |
| POST | `/editStatus` | `EventController@editStatus` | 修改活動狀態 |
| POST | `/imageUpload` | `EventController@imageUpload` | 上傳活動圖片 |
| POST | `/getImage` | `EventController@getImage` | 取得活動圖片 |
| POST | `/getInviteList` | `EventController@getInviteList` | 取得邀請名單 |
| POST | `/importGroup` | `EventController@importGroup` | 從群組匯入邀請對象 |
| POST | `/getDisplayList` | `EventController@getDisplayList` | 活動顯示清單 |
| POST | `/updateDisplay` | `EventController@updateDisplay` | 更新活動顯示設定 |
| POST | `/createGoogleForm` | `EventController@createGoogleForm` | 建立 Google Form 問卷 |
| POST | `/updateGoogleForm` | `EventController@updateGoogleForm` | 更新問卷 |
| POST | `/delGoogleForm` | `EventController@delGoogleForm` | 刪除問卷 |
| POST | `/getGoogleForm` | `EventController@getGoogleForm` | 取得問卷資訊 |
| POST | `/updateResponseStatus` | `EventController@updateResponseStatus` | 更新問卷回應狀態 |

### Mail (`/api/edm/mail`)

| Method | Path | Controller | 說明 |
|---|---|---|---|
| POST | `/mail/inviteMail` | `MailController@inviteMail` | 將活動邀請信分批推進 AWS SES 佇列 |

### 系統路由

| Method | Path | 說明 |
|---|---|---|
| GET | `/up` | Laravel 健康檢查 |
| GET | `/api/documentation` | Swagger UI |
| GET | `/docs` | Swagger JSON |
| GET | `/telescope` | Telescope 監控面板 |

> 完整列表可用 `docker exec edm-backend-app php artisan route:list` 檢視，共約 85 條路由。

---

## 資料模型

| 分類 | Model | 資料表 | 用途 |
|---|---|---|---|
| EDM | `Member` | `member` | 會員主檔 |
| EDM | `Emails` | `emails` | 會員 Email (1:N) |
| EDM | `Mobiles` | `mobile` | 會員手機 (1:N) |
| EDM | `Organization` | `organization` | 組織/公司 |
| EDM | `Group` | `group` | 名單群組 |
| EDM | `Event` | `event` | 活動主檔 |
| EDM | `EventRelation` | `event_relation` | 活動 ↔ 會員關聯 (邀請名單) |
| EDM | `Image` | — | 活動圖片 |
| Google | `GoogleForm` | `google_form` | 綁定的 Google 問卷 |
| Google | `GoogleFormResponse` | `google_form_responses` | 問卷回應 (含狀態欄位) |
| Google | `GoogleFormStat` | `google_form_stats` | 問卷統計 |
| Meeting | `MeetingUser` | — | 會議使用者 |
| 系統 | `DocumentCount` | `document_count` | 文件計數器 |
| 系統 | `User` | `users` | Laravel 預設使用者 |

Migrations 位於 [`database/migrations/`](database/migrations/)，共 17 個檔案。

---

## 背景任務 (Queue)

佇列採 `QUEUE_CONNECTION=database`，不需額外啟 Redis。

### SendAwsMailJob

[`app/Jobs/Common/SendAwsMailJob.php`](app/Jobs/Common/SendAwsMailJob.php)

- 由 `MailController@inviteMail` 分塊派送 (chunk-based dispatch)
- 每批包含多筆 `['email', 'subject', 'body', 'from']`
- 交由 [`AwsSesService`](app/Services/AwsSesService.php) 透過 AWS SDK 呼叫 SES 寄送

### 啟動 Worker

```bash
docker exec -d edm-backend-app php artisan queue:work --tries=3 --timeout=60
```

> 目前 compose 未預設開一支 worker 容器，若要長期跑建議另建一個 service 共用 `edm-backend-image`，`command: php artisan queue:work`。

---

## 中介層 (Middleware)

| Middleware | 檔案 | 功能 |
|---|---|---|
| `AuthorizeJwt` | [`app/Http/Middleware/AuthorizeJwt.php`](app/Http/Middleware/AuthorizeJwt.php) | 驗證 Header `Authorization: Bearer <jwt>`，以 `APP_KEY` (HS256) 解密；成功則把 payload 併入 `request('auth')`，失敗回 401 |
| `WhitelistIpMiddleware` | [`app/Http/Middleware/WhitelistIpMiddleware.php`](app/Http/Middleware/WhitelistIpMiddleware.php) | 比對 `sso.allowed_edm_ips`；`*` 代表不限；不符回 403 |

> 目前 [`routes/Api/edm.php:11-12`](routes/Api/edm.php#L11-L12) 的 `AuthorizeJwt` middleware 已被註解掉，若要開啟驗證請取消註解。

---

## 開發工具

| 工具 | 路徑 / 指令 | 用途 |
|---|---|---|
| Swagger UI | http://localhost:81/api/documentation | API 互動文件 (L5 Swagger) |
| Laravel Telescope | http://localhost:81/telescope | 請求/查詢/Job/例外全紀錄面板 |
| Laravel Pail | `php artisan pail` | Terminal 即時串流 log |
| Laravel Pint | `./vendor/bin/pint` | PHP 程式碼風格修正 |
| PHPUnit | `./vendor/bin/phpunit` | 單元測試 |

---

## 常用指令

在 host 執行 (都針對 `edm-backend-app` 容器)：

```bash
# 進入容器
docker exec -it edm-backend-app bash

# 查看即時 log
docker logs -f edm-backend-app

# Artisan
docker exec edm-backend-app php artisan route:list
docker exec edm-backend-app php artisan migrate
docker exec edm-backend-app php artisan migrate:fresh --seed
docker exec edm-backend-app php artisan tinker
docker exec edm-backend-app php artisan queue:work

# 清除 cache / config
docker exec edm-backend-app php artisan optimize:clear

# 進 MySQL CLI
docker exec -it edm-db mysql -udeveloper -p edm_db
```
