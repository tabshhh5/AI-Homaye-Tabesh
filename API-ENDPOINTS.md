# همای تابش - راهنمای REST API Endpoints

این سند فهرست کامل تمام REST API endpoints افزونه همای تابش را شامل می‌شود.

## 📊 Health & Diagnostics

### Health Check
- **Endpoint:** `GET /wp-json/homaye/v1/health`
- **دسترسی:** عمومی
- **توضیحات:** بررسی سریع سلامت افزونه برای ابزارهای مانیتورینگ
- **پاسخ نمونه:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01 12:00:00",
  "version": "1.0.0",
  "database": "ok",
  "tables": "ok"
}
```

### Detailed Health Check
- **Endpoint:** `GET /wp-json/homaye/v1/health/detailed`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** بررسی کامل سلامت با گزارش جزئیات
- **پاسخ نمونه:**
```json
{
  "status": "healthy",
  "checks": [
    {"name": "PHP Version", "status": "pass", "message": "PHP 8.2.0"},
    {"name": "Database Tables", "status": "pass", "message": "All tables exist"}
  ],
  "errors": [],
  "warnings": [],
  "recommendations": []
}
```

### API Endpoints Status
- **Endpoint:** `GET /wp-json/homaye/v1/health/endpoints`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** بررسی وضعیت تمام endpoints ثبت شده
- **پاسخ نمونه:**
```json
{
  "status": "ok",
  "summary": {
    "active": 15,
    "total": 16,
    "percentage": 93.75
  },
  "endpoints": [
    {
      "route": "/homaye/v1/chat",
      "description": "AI chat interface",
      "registered": true,
      "status": "active"
    }
  ]
}
```

### Error Reporting
- **Endpoint:** `POST /wp-json/homaye/v1/error-report`
- **دسترسی:** کاربران احراز هویت شده
- **توضیحات:** گزارش خطاهای JavaScript از فرانت‌اند
- **پارامترها:**
  - `error` (object, required): اطلاعات خطا
  - `context` (object, optional): اطلاعات زمینه‌ای
- **پاسخ نمونه:**
```json
{
  "success": true,
  "message": "Error reported successfully"
}
```

## 💬 AI & Chat

### Chat with AI
- **Endpoint:** `POST /wp-json/homaye/v1/chat`
- **دسترسی:** کاربران سایت
- **توضیحات:** ارسال پیام به هوش مصنوعی و دریافت پاسخ
- **پارامترها:**
  - `message` (string, required): پیام کاربر
  - `context` (object, optional): اطلاعات زمینه‌ای
- **پاسخ نمونه:**
```json
{
  "success": true,
  "response": "پاسخ هوش مصنوعی",
  "actions": [],
  "session_id": "abc123"
}
```

## 📈 Telemetry & Tracking

### Track User Event
- **Endpoint:** `POST /wp-json/homaye/v1/telemetry`
- **دسترسی:** کاربران سایت
- **توضیحات:** ثبت رویدادهای رفتار کاربر
- **پارامترها:**
  - `event_type` (string, required): نوع رویداد
  - `element_class` (string): کلاس المان
  - `element_data` (object): اطلاعات المان

## 🎯 Lead Management

### Create/Update Lead
- **Endpoint:** `POST /wp-json/homaye/v1/lead`
- **دسترسی:** کاربران سایت
- **توضیحات:** ایجاد یا به‌روزرسانی lead
- **پارامترها:**
  - `contact_info` (string): اطلاعات تماس
  - `contact_name` (string): نام
  - `requirements` (object): نیازمندی‌ها

### Get Lead Data
- **Endpoint:** `GET /wp-json/homaye/v1/lead`
- **دسترسی:** کاربران سایت
- **توضیحات:** دریافت اطلاعات lead فعلی

## 🗄️ Vault (Omni-Store)

### Store Context Data
- **Endpoint:** `POST /wp-json/homaye/v1/vault/store`
- **دسترسی:** کاربران سایت
- **توضیحات:** ذخیره اطلاعات زمینه‌ای کاربر
- **پارامترها:**
  - `context_key` (string, required): کلید زمینه
  - `context_value` (any, required): مقدار

### Retrieve Context Data
- **Endpoint:** `GET /wp-json/homaye/v1/vault/retrieve`
- **دسترسی:** کاربران سایت
- **توضیحات:** بازیابی اطلاعات ذخیره شده
- **پارامترها:**
  - `context_key` (string, required): کلید زمینه

## 📊 Atlas Analytics

### Get Insights
- **Endpoint:** `GET /wp-json/homaye/v1/atlas/insights`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** دریافت تحلیل‌های رفتار کاربران
- **پاسخ نمونه:**
```json
{
  "total_users": 150,
  "active_sessions": 12,
  "top_personas": ["explorer", "buyer"],
  "conversion_rate": 3.2
}
```

### Get User Journey
- **Endpoint:** `GET /wp-json/homaye/v1/atlas/journey`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** دریافت مسیر کاربر
- **پارامترها:**
  - `user_identifier` (string, required): شناسه کاربر

## 🛒 Post-Purchase

### Create Support Ticket
- **Endpoint:** `POST /wp-json/homaye/v1/support/ticket`
- **دسترسی:** کاربران با سفارش
- **توضیحات:** ایجاد تیکت پشتیبانی
- **پارامترها:**
  - `order_id` (int, required): شماره سفارش
  - `message` (string, required): متن پیام

### Track Order Status
- **Endpoint:** `GET /wp-json/homaye/v1/order/track`
- **دسترسی:** کاربران با سفارش
- **توضیحات:** پیگیری وضعیت سفارش
- **پارامترها:**
  - `order_id` (int, required): شماره سفارش

## 🔍 Global Observer

### Get Plugin Insights
- **Endpoint:** `GET /wp-json/homaye/v1/observer/plugins`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** اطلاعات افزونه‌های نظارت شده

### Get Knowledge Facts
- **Endpoint:** `GET /wp-json/homaye/v1/observer/knowledge`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** دریافت دانش استخراج شده از افزونه‌ها

## 🔒 Security & Access Control

### Check Permissions
- **Endpoint:** `POST /wp-json/homaye/v1/access/check`
- **دسترسی:** کاربران سایت
- **توضیحات:** بررسی سطح دسترسی کاربر
- **پارامترها:**
  - `action` (string, required): اکشن مورد نظر

### Get Security Alerts
- **Endpoint:** `GET /wp-json/homaye/v1/security/alerts`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** دریافت هشدارهای امنیتی

## 💪 Resilience & Fallback

### Check System Status
- **Endpoint:** `GET /wp-json/homaye/v1/resilience/status`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** بررسی وضعیت سیستم‌های بازگشتی

### Test Fallback Mode
- **Endpoint:** `POST /wp-json/homaye/v1/resilience/test-fallback`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** تست حالت fallback

## 📝 Feedback

### Submit Feedback
- **Endpoint:** `POST /wp-json/homaye/v1/feedback`
- **دسترسی:** کاربران سایت
- **توضیحات:** ثبت بازخورد کاربر
- **پارامترها:**
  - `message` (string, required): متن بازخورد
  - `rating` (int): امتیاز (1-5)
  - `context` (object): زمینه بازخورد

## 📈 Console Analytics (Super Console)

### Get Dashboard Data
- **Endpoint:** `GET /wp-json/homaye/v1/console/dashboard`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** داده‌های داشبورد کنترل

### Get System Diagnostics
- **Endpoint:** `GET /wp-json/homaye/v1/console/diagnostics`
- **دسترسی:** فقط مدیر سایت
- **توضیحات:** تشخیص مشکلات سیستم

## 🔑 Authentication

تمام endpoints از WordPress Nonce برای احراز هویت استفاده می‌کنند.

### ارسال Nonce در درخواست‌ها:

```javascript
fetch('/wp-json/homaye/v1/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    message: 'سلام'
  })
});
```

## ⚠️ Error Handling

تمام endpoints در صورت خطا پاسخ استاندارد زیر را برمی‌گردانند:

```json
{
  "code": "error_code",
  "message": "توضیحات خطا",
  "data": {
    "status": 400
  }
}
```

## 🔍 Rate Limiting

- درخواست‌های عمومی: 100 درخواست در دقیقه
- درخواست‌های مدیر: نامحدود

## 📚 منابع بیشتر

- [مستندات کامل](./README.md)
- [راهنمای توسعه](./INSTALL.md)
- [مثال‌های استفاده](./examples/)
