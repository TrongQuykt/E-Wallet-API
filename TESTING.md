# 🧪 Hướng Dẫn Testing — E-Wallet API

Tài liệu này hướng dẫn 2 phương pháp kiểm thử:
1. **Automated Test** — PHPUnit chạy tự động qua terminal
2. **Manual Test** — Gọi API thủ công qua Swagger UI hoặc PowerShell

---

## 📑 Mục Lục

- [Automated Tests (PHPUnit)](#-automated-tests-phpunit)
- [Manual Tests — Swagger UI](#-manual-tests--swagger-ui)
- [Manual Tests — PowerShell](#-manual-tests--powershell)
- [Tạo Signature Webhook Deposit](#-tạo-signature-webhook-deposit)
- [Artisan Audit Command](#-artisan-audit-command)

---

## ✅ Automated Tests (PHPUnit)

Các test chạy với **SQLite in-memory** — không cần MySQL hay Redis đang chạy.

```bash
php artisan test
```

### Kết Quả Mong Đợi

```
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\AuthTest
  ✓ register creates user and wallet successfully      0.28s
  ✓ login returns token successfully                   0.02s
  ✓ login lockout after five failed attempts           0.23s

   PASS  Tests\Feature\DepositTest
  ✓ deposit with valid checksum success                0.04s
  ✓ deposit with invalid checksum fails 400            0.02s

   PASS  Tests\Feature\TransferTest
  ✓ transfer success                                   0.04s
  ✓ transfer fails when insufficient balance           0.02s
  ✓ transfer fails when limit exceeded                 0.02s
  ✓ pessimistic locking prevents race condition        0.02s

  Tests: 11 passed (54 assertions)
```

### Chạy Một Test Cụ Thể

```bash
# Chỉ chạy AuthTest
php artisan test --filter AuthTest

# Chỉ chạy 1 method cụ thể
php artisan test --filter test_transfer_success
```

---

## 🌐 Manual Tests — Swagger UI

### Bước 0: Khởi Chạy Server

```bash
php artisan serve
# → http://127.0.0.1:8000
```

Mở trình duyệt: **http://127.0.0.1:8000/api/documentation**

---

### 🔐 1. Đăng Ký Tài Khoản

**Endpoint:** `POST /api/v1/auth/register`

Vào Swagger UI → Click **Authentication** → Click **POST /register** → **Try it out** → Nhập:

```json
{
  "name": "Nguyễn Văn A",
  "email": "vana@gmail.com",
  "phone": "0987654321",
  "password": "Password123@",
  "password_confirmation": "Password123@"
}
```

**Response thành công (201):**
```json
{
  "status": "success",
  "message": "Đăng ký tài khoản thành công và đã tự động khởi tạo ví.",
  "data": {
    "id": 1,
    "name": "Nguyễn Văn A",
    "email": "vana@gmail.com",
    "wallet": {
      "id": 1,
      "balance": "0.00",
      "currency": "VND"
    }
  }
}
```

---

### 🔑 2. Đăng Nhập

**Endpoint:** `POST /api/v1/auth/login`

```json
{
  "email": "vana@gmail.com",
  "password": "Password123@"
}
```

**Response (200):**
```json
{
  "status": "success",
  "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz..."
}
```

> **Quan trọng:** Copy token này để dùng ở các bước tiếp theo.

---

### 🔓 3. Xác Thực Token Trên Swagger UI

Click nút **Authorize 🔓** ở góc trên phải → Nhập vào ô `bearerAuth`:

```
Bearer 1|AbCdEfGhIjKlMnOpQrStUvWxYz...
```

Click **Authorize** rồi **Close**.

---

### 💰 4. Kiểm Tra Số Dư

**Endpoint:** `GET /api/v1/wallet/balance`

Không cần body. Click **Execute**.

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "wallet_id": 1,
    "balance": "0.00",
    "currency": "VND"
  }
}
```

---

### 💳 5. Nạp Tiền (Webhook Deposit)

**Endpoint:** `POST /api/v1/wallet/deposit`

**⚠️ Bắt buộc phải tạo Signature đúng.** Xem phần [Tạo Signature](#-tạo-signature-webhook-deposit) bên dưới.

```json
{
  "wallet_id": 1,
  "amount": "500000.00",
  "reference_id": "dep_test_001",
  "signature": "<HMAC_SHA256_ĐƯỢC_TÍNH>",
  "metadata": {
    "bank": "Vietcombank",
    "channel": "QR_CODE"
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "transaction_id": 1,
    "balance_after": "500000.00",
    "reference_id": "dep_test_001"
  }
}
```

---

### 💸 6. Chuyển Tiền

**Yêu cầu:** Phải có ít nhất 2 tài khoản. Đăng ký thêm tài khoản thứ 2 trước:

```json
// Đăng ký user B
{
  "name": "Nguyễn Văn B",
  "email": "vanb@gmail.com",
  "password": "Password123@",
  "password_confirmation": "Password123@"
}
```

**Endpoint:** `POST /api/v1/wallet/transfer` (đang đăng nhập với user A)

```json
{
  "receiver_wallet_id": 2,
  "amount": "100000.00",
  "note": "Trả tiền ăn trưa"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "reference_id": "uuid-xxxx-yyyy-zzzz",
    "balance": "400000.00"
  }
}
```

**Kiểm tra DB:** Sẽ thấy 2 records trong bảng `transactions` cùng `reference_id`:
- `type: transfer_out` | `wallet_id: 1` | `amount: -100000.00`
- `type: transfer_in` | `wallet_id: 2` | `amount: 100000.00`

---

### 🔒 7. Test Lockout Brute-force

Thử đăng nhập sai **6 lần liên tiếp** với email đúng nhưng password sai:

```json
{
  "email": "vana@gmail.com",
  "password": "SaiPassword"
}
```

Từ lần **thứ 6** sẽ nhận:
```json
{
  "message": "Tài khoản bị khóa do đăng nhập sai nhiều lần. Vui lòng thử lại sau X giây."
}
```

---

## 💻 Manual Tests — PowerShell

### Đăng Ký

```powershell
Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8000/api/v1/auth/register" `
  -Headers @{"Content-Type"="application/json"; "Accept"="application/json"} `
  -Body '{"name":"Test User","email":"test@gmail.com","password":"Password123@","password_confirmation":"Password123@"}' |
  ConvertTo-Json
```

### Đăng Nhập & Lưu Token

```powershell
$response = Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8000/api/v1/auth/login" `
  -Headers @{"Content-Type"="application/json"; "Accept"="application/json"} `
  -Body '{"email":"test@gmail.com","password":"Password123@"}'

$token = $response.token
Write-Host "Token: $token"
```

### Kiểm Tra Số Dư

```powershell
Invoke-RestMethod -Method Get `
  -Uri "http://127.0.0.1:8000/api/v1/wallet/balance" `
  -Headers @{"Authorization"="Bearer $token"; "Accept"="application/json"} |
  ConvertTo-Json
```

### Chuyển Tiền

```powershell
Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8000/api/v1/wallet/transfer" `
  -Headers @{"Authorization"="Bearer $token"; "Content-Type"="application/json"; "Accept"="application/json"} `
  -Body '{"receiver_wallet_id":2,"amount":"50000.00","note":"Test"}' |
  ConvertTo-Json
```

---

## 🔑 Tạo Signature Webhook Deposit

Signature là **HMAC-SHA256** của chuỗi `"{wallet_id}|{amount}|{reference_id}"` dùng key `WALLET_CHECKSUM_SECRET` trong `.env`.

### Cách 1: PHP (nhanh nhất)

```bash
php -r "echo hash_hmac('sha256', '1|500000.00|dep_test_001', 'my-e-wallet-secure-checksum-key-2026-secret');"
```

Copy output dán vào field `signature`.

### Cách 2: PowerShell

```powershell
$secret = "my-e-wallet-secure-checksum-key-2026-secret"
$payload = "1|500000.00|dep_test_001"  # wallet_id|amount|reference_id

$hmac = [System.Security.Cryptography.HMACSHA256]::new([System.Text.Encoding]::UTF8.GetBytes($secret))
$hash = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($payload))
$signature = [System.BitConverter]::ToString($hash).Replace("-", "").ToLower()
Write-Host $signature
```

### Cách 3: Nạp Tiền Đầy Đủ qua PowerShell

```powershell
# Tính signature tự động
$secret  = "my-e-wallet-secure-checksum-key-2026-secret"
$walletId = "1"; $amount = "500000.00"; $refId = "dep_test_001"
$payload  = "$walletId|$amount|$refId"
$hmac      = [System.Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($secret))
$signature = [BitConverter]::ToString($hmac.ComputeHash([Text.Encoding]::UTF8.GetBytes($payload))).Replace("-","").ToLower()

# Gọi API deposit
Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8000/api/v1/wallet/deposit" `
  -Headers @{"Content-Type"="application/json"; "Accept"="application/json"} `
  -Body (ConvertTo-Json @{wallet_id=1; amount=$amount; reference_id=$refId; signature=$signature}) |
  ConvertTo-Json
```

---

## 🔎 Artisan Audit Command

Sau khi đã có giao dịch, chạy lệnh đối soát số dư:

```bash
php artisan wallet:audit
```

**Khi tất cả hợp lệ:**
```
Bắt đầu quy trình đối soát số dư ví điện tử...
Đã hoàn tất đối soát 2 ví.
Thành công: Toàn bộ số dư của các ví trùng khớp hoàn hảo với doanh số lịch sử.
```

Log giao dịch được ghi tại: `storage/logs/transactions.log` (JSON format)
