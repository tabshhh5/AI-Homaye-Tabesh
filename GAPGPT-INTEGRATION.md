# راهنمای یکپارچه‌سازی GapGPT

## نمای کلی
این افزونه اکنون از اتصال به دو سرویس‌دهنده هوش مصنوعی پشتیبانی می‌کند:
1. **Google Gemini Direct** - اتصال مستقیم به API گوگل جمینی
2. **GapGPT Gateway** - دروازه سازگار با OpenAI با دسترسی به مدل‌های مختلف

## تنظیمات جدید

### پیکربندی سراسری هوش مصنوعی
در صفحه تنظیمات «همای تابش»، یک بخش جدید با عنوان **«پیکربندی سراسری هوش مصنوعی»** اضافه شده است که شامل موارد زیر است:

#### 1. انتخاب سرویس‌دهنده
- **Google Gemini Direct**: اتصال مستقیم به API گوگل جمینی
- **GapGPT Gateway**: استفاده از GapGPT برای دسترسی به مدل‌های متنوع

#### 2. انتخاب مدل هوشمند
مدل‌های در دسترس:
- `grok-3-mini` - Grok 3 Mini
- `gemini-2.0-flash` - Gemini 2.0 Flash
- `qwen3-235b-a22b` - Qwen3 235B A22B
- `deepseek-chat` - DeepSeek Chat
- `claude-sonnet-4-20250514` - Claude Sonnet 4
- `gpt-4o-mini` - GPT-4o Mini

**توجه**: برخی مدل‌ها (مانند Gemini) از طریق هر دو ارائه‌دهنده قابل دسترسی هستند.

#### 3. آدرس پایه API
- مقدار پیش‌فرض: `https://api.gapgpt.app/v1`
- آدرس جایگزین برای CDN خارجی: `https://api.gapapi.com/v1`

#### 4. کلید API GapGPT
- فیلد ورود توکن API از پنل توسعه‌دهندگان GapGPT
- فقط برای GapGPT Gateway مورد نیاز است

## نحوه استفاده

### راه‌اندازی اولیه

#### استفاده از Google Gemini Direct
1. به تنظیمات افزونه بروید
2. در بخش «پیکربندی سراسری هوش مصنوعی»:
   - **سرویس‌دهنده**: Google Gemini Direct را انتخاب کنید
   - **مدل**: یکی از مدل‌های Gemini را انتخاب کنید
3. کلید API گوگل خود را در فیلد «کلید API گوگل Gemini» وارد کنید
4. روی «ذخیره تنظیمات» کلیک کنید

#### استفاده از GapGPT Gateway
1. ابتدا از [پنل توسعه‌دهندگان GapGPT](https://gapgpt.app) یک کلید API دریافت کنید
2. به تنظیمات افزونه بروید
3. در بخش «پیکربندی سراسری هوش مصنوعی»:
   - **سرویس‌دهنده**: GapGPT Gateway را انتخاب کنید
   - **مدل**: مدل مورد نظر خود را انتخاب کنید
   - **آدرس پایه API**: مقدار پیش‌فرض را نگه دارید یا تغییر دهید
   - **کلید API GapGPT**: کلید دریافتی را وارد کنید
4. روی «ذخیره تنظیمات» کلیک کنید

## ویژگی‌های فنی

### معماری انعطاف‌پذیر
- کلاس `HT_Gemini_Client` به طور خودکار بین دو سرویس‌دهنده تشخیص می‌دهد
- سازگاری کامل با کد موجود (Backward Compatible)
- تبدیل خودکار فرمت درخواست/پاسخ بین Gemini و OpenAI

### امنیت
- احراز هویت با استفاده از `Authorization: Bearer {TOKEN}` برای GapGPT
- هدرهای CSP برای مجوز دسترسی به دامنه API
- اعتبارسنجی کامل ورودی‌ها و URL‌ها
- عدم استفاده از `eval()` یا `new Function()` در کد JavaScript

### قابلیت اطمینان پایگاه داده
- متد `ensure_tables_exist()` برای اطمینان از وجود جداول
- بازیابی خودکار جداول از دست رفته
- فراخوانی خودکار هنگام بارگذاری تنظیمات

## عیب‌یابی

### خطای "API key not configured"
- مطمئن شوید که کلید API مناسب برای سرویس‌دهنده انتخابی وارد شده است
- برای GapGPT: کلید API GapGPT
- برای Gemini Direct: کلید API گوگل Gemini

### خطای اتصال به API
1. آدرس پایه API را بررسی کنید
2. اطمینان حاصل کنید که کلید API معتبر است
3. وضعیت اتصال اینترنت سرور را بررسی کنید
4. لاگ‌های خطا را در `wp-content/debug.log` مشاهده کنید

### مشکل در انتخاب مدل
- مطمئن شوید که مدل انتخابی با سرویس‌دهنده فعلی سازگار است
- برخی مدل‌ها فقط از طریق GapGPT در دسترس هستند

## مثال کد

### دریافت پیکربندی فعلی
```php
$provider = get_option('ht_ai_provider', 'gemini_direct');
$model = get_option('ht_ai_model', 'gemini-2.0-flash');
$base_url = get_option('ht_gapgpt_base_url', 'https://api.gapgpt.app/v1');
$api_key = get_option('ht_gapgpt_api_key', '');
```

### استفاده از کلاینت AI
```php
// کلاینت به طور خودکار پیکربندی را بارگذاری می‌کند
$client = new \HomayeTabesh\HT_Gemini_Client();

// ارسال درخواست (بدون نیاز به تشخیص سرویس‌دهنده)
$response = $client->generate_content(
    'سلام! چطور می‌تونم کمکت کنم؟',
    ['context' => 'example']
);
```

## API Reference

### فرمت درخواست GapGPT
```json
{
  "model": "gemini-2.0-flash",
  "messages": [
    {
      "role": "system",
      "content": "دستورالعمل سیستم"
    },
    {
      "role": "user",
      "content": "پیام کاربر"
    }
  ],
  "temperature": 0.7
}
```

### فرمت پاسخ
پاسخ از هر دو سرویس‌دهنده به فرمت یکسان داخلی تبدیل می‌شود:
```php
[
    'success' => true,
    'response' => 'متن پاسخ',
    'candidates' => [...],
    'usageMetadata' => [
        'promptTokenCount' => 10,
        'candidatesTokenCount' => 20,
        'totalTokenCount' => 30
    ]
]
```

## لینک‌های مفید
- [مستندات GapGPT](https://gapgpt.app)
- [پنل توسعه‌دهندگان GapGPT](https://gapgpt.app)
- [Google AI Studio](https://makersuite.google.com/app/apikey)

## تاریخچه نسخه‌ها
- **v1.0.0** - افزودن پشتیبانی از GapGPT Gateway
- **v1.0.0** - بهبود امنیت و قابلیت اطمینان پایگاه داده
