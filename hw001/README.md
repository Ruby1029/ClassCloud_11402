# 簡易餐廳掃碼點餐與後台接單系統

## 專案內容
- 顧客端點餐頁：選擇餐點與數量，送出訂單
- 後台廚房頁：顯示待處理訂單並可完成出餐
- 後端 API：PHP + MySQL

## 目錄結構
- api/：PHP API (`orders.php`, `db.php`)
- customer/：顧客端頁面
- boss/：後台頁面
- database/：資料庫 schema (`schema.sql`)

## 本機啟動（示意）
- 需有 Apache/PHP/MySQL 環境
- 匯入 `database/schema.sql`
- 設定 `api/db.php` 的資料庫帳密
- 瀏覽器開啟
  - `http://localhost/homework001/customer/customer.html`
  - `http://localhost/homework001/boss/boss.html`

## AWS 部署（簡述）
- EC2 安裝 Apache/PHP/MySQL/phpMyAdmin
- 上傳本專案到 `/var/www/html/homework001`
- 在 phpMyAdmin 匯入 `database/schema.sql`
- 透過瀏覽器操作前後台並在 phpMyAdmin 驗證資料寫入
