# ADR-0001: JWT 採用共享 APP_KEY 本地驗證 (HS256)

- **狀態**: Accepted
- **日期**: 2026-04-19
- **決策者**: Shane (SA / 開發者)

## Context — 我們在解決什麼問題?

EDM Backend 是 [Middle Platform](../../../Middle_Platform) SSO 的下游消費者。每個進來的 `/api/edm/*` request 都帶有一個由中台簽發的 JWT,EDM Backend 需要回答兩件事:

1. 這個 JWT 是真的嗎?(沒被偽造、沒被竄改)
2. 持有者是誰?(從 payload 解出 user 資訊)

技術上的兩個基本選項:

- **本地驗證**:EDM Backend 自己持有簽章用的密鑰,直接解 JWT 簽章
- **回中台 verify**:EDM Backend 把 token POST 給 `/api/edm/sso/verify-token`,信任中台的回應

選哪個,影響:中台負載、跨系統耦合、密鑰管理風險、故障隔離度。

## Decision — 我們選了什麼?

**採用 HS256 本地驗證,EDM Backend 與中台共享 `APP_KEY`**:

- 中台簽發 JWT 時用 `SECRET_KEY` (HS256)
- EDM Backend 用 `config('app.key')` (Laravel 的 `APP_KEY`,內容**等同**中台的 `SECRET_KEY`)
- `AuthorizeJwt` middleware 用 `firebase/php-jwt` 的 `JWT::decode()` 直接驗

## Considered Options — 還評估過哪些?

### 選項 1 — 本地驗證 + 共享 APP_KEY (HS256) 【選中】

- ✅ 中台離線不影響 EDM Backend 已收到 JWT 的處理
- ✅ 每個 request 只多 1 次本地解碼運算,效能極佳
- ✅ 程式簡單,`firebase/php-jwt` 是 PHP 生態最成熟套件
- ⚠ APP_KEY 必須跟中台**完全一致**,部署時容易出錯(漏設 / 漏 base64 解碼)
- ⚠ 任何持有 APP_KEY 的人都能**偽造**任意身分的 JWT,共享範圍越大風險越高

### 選項 2 — 每個 request 回中台 verify

- ✅ EDM Backend 不必持有 secret,密鑰只在中台
- ✅ 中台可立即撤銷 token(維護一個 active list / blacklist)
- ❌ 中台變單點瓶頸,每個 request 加一次 RTT
- ❌ 中台短暫不可用 → EDM Backend 整個無法服務

### 選項 3 — RS256 非對稱密鑰 + JWKS

- ✅ EDM Backend 只需要中台的**公鑰**,沒有偽造風險
- ✅ 中台可做 key rotation 而不影響業務系統(透過 JWKS endpoint)
- ❌ 中台需要先升級到 RS256(目前是 HS256)
- ❌ 部署複雜度上升(JWKS endpoint、key rotation 流程)
- 🔁 列為 **明確的 Roadmap**:中台 ADR-0002 已標記同樣方向

## Consequences — 這個決定帶來什麼?

### ✅ 正面

- **故障隔離**:中台升級 / 重啟,EDM Backend 完全不受影響
- **效能與可擴展**:無遠端呼叫成本,EDM Backend 可水平擴展不黏中台
- **依賴最小**:不依賴中台網路可達性

### ⚠ 負面 / Trade-off

- **APP_KEY 部署風險**:必須跟中台完全一致(包含 `base64:` 前綴的處理)。緩解:
  - `AuthorizeJwt` 在失敗時 log `secret_length`,協助判斷是不是 key 不對
  - CI 流程加強制檢查:`APP_KEY` 不能是預設值、不能等於範本

- **無法立即撤銷單一 token**:JWT 在 TTL 內無法被中台主動 revoke。緩解:
  - 中台 access token TTL 已設短(30 分鐘)
  - 對「立即登出」場景容忍度應該被定義為「30 分鐘內生效」

- **共用密鑰擴大攻擊面**:任一持有 APP_KEY 的服務洩漏 = 整個生態都能被偽造 token 攻擊
  - 短期:`APP_KEY` 只放 server 環境變數,不入 repo,不夾在 docker image
  - 長期:Roadmap 切到 RS256(選項 3)

### 🔁 後續追蹤

- 第二個業務系統加入時(從 1 個業務系統 → 2 個),重新評估是否該升 RS256
- 監控 401 異常率,若異常飆高可能是 key 不對齊

## References

- Code:
  - [`app/Http/Middleware/AuthorizeJwt.php`](../../app/Http/Middleware/AuthorizeJwt.php)
  - [`config/sso.php`](../../config/sso.php)
- 文件:
  - [`docs/sequence-diagrams.md` 第 1 節](../sequence-diagrams.md#1-jwt-驗證流程)
  - [`docs/api-spec.md` 第 2.1 節](../api-spec.md#21-認證--jwt-bearer)
  - [Middle Platform ADR-0002](../../../Middle_Platform/docs/adr/0002-jwt-vs-session.md) — 中台側對應的決策
- 外部:
  - [Firebase PHP-JWT](https://github.com/firebase/php-jwt)
  - [JWT.io](https://jwt.io/) — token 解碼/檢視
  - [OWASP JWT Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
