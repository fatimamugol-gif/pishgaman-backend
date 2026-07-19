# سیستم یکپارچه مدیریت منابع انسانی - راهنمای استفاده

## overview
این سیستم جامع برای مدیریت حضور و غیاب، مرخصی، و جبران تاخیر کارکنان طراحی شده است.

### سیستم جبران تاخیر
طبق قانون تعریف شده:

- **نیم ساعت اول تاخیر (۰ تا ۳۰ دقیقه):** بدون جبران خدمت
- **نیم ساعت دوم تاخیر (۳۰ تا ۶۰ دقیقه):** ۱۰ دقیقه جبران خدمت
- **نیم ساعت سوم تاخیر (۶۰ تا ۹۰ دقیقه):** ۲۰ دقیقه جبران خدمت
- **نیم ساعت چهارم تاخیر (۹۰ تا ۱۲۰ دقیقه):** ۳۰ دقیقه جبران خدمت + مرخصی ۴ ساعته خودکار

### سیستم مرخصی ماهانه (8.5% قانون)
- **سقف مرخصی ماهانه:** 8.5% از کل ساعات کاری ماه (حدود 15.9 ساعت)
- **مرخصی روزانه:** هر روز = 8.5 ساعت از سهمیه
- **مرخصی ساعتی:** بر اساس ساعت واقعی محاسبه می‌شود
- **قانون ذخیره:** مرخصی استفاده نشده قابل ذخیره نیست و به عنوان اضافه کار در همان ماه محاسبه می‌شود

### سیستم محدودیت MAC Address
- هر کاربر می‌تواند حداکثر 2 MAC Address ثبت کند
- ثبت ورود/خروج فقط از دستگاه‌های مجاز امکان‌پذیر است
- برای ثبت MAC Address، ادمین باید در پروفایل کاربر آدرس‌ها را وارد کند

## شرایط اعمال قانون تاخیر
قانون جبران تاخیر تنها در شرایط زیر اعمال می‌شود:
1. روز تعطیل رسمی نباشد
2. کاربر مرخصی روزانه یا ساعتی ثبت نکرده باشد
3. کاربر دارای شیفت کاری فعال باشد

## ساختار دیتابیس

### جدول next_delay_compensation_rules
ذخیره قوانین جبران تاخیر:
- `rule_name`: نام قانون
- `delay_start_minutes`: شروع بازه تاخیر (دقیقه)
- `delay_end_minutes`: پایان بازه تاخیر (دقیقه)
- `compensation_minutes`: دقیقه جبران خدمت مورد نیاز
- `auto_leave_hours`: آیا مرخصی خودکار ثبت شود؟
- `auto_leave_duration_hours`: مدت مرخصی خودکار (ساعت)
- `is_active`: وضعیت فعال بودن قانون

### جدول next_delay_compensations
ذخیره رکوردهای جبران تاخیر کارکنان:
- `user_id`: شناسه کاربر
- `attendance_clock_id`: شناسه رکورد تردد
- `date`: تاریخ
- `delay_minutes`: مقدار تاخیر واقعی
- `compensation_minutes_required`: دقیقه جبران خدمت مورد نیاز
- `compensation_minutes_completed`: دقیقه جبران خدمت انجام شده
- `auto_leave_recorded`: آیا مرخصی خودکار ثبت شد؟
- `auto_leave_request_id`: آی‌دی مرخصی ثبت شده

## API Endpoints

### مدیریت قوانین (فقط ادمین)

#### دریافت لیست قوانین
```
GET /api/next/delay-compensation/rules
```

#### ایجاد قانون جدید
```
POST /api/next/delay-compensation/rules
Body: {
  "rule_name": "نام قانون",
  "delay_start_minutes": 0,
  "delay_end_minutes": 30,
  "compensation_minutes": 0,
  "auto_leave_hours": false,
  "auto_leave_duration_hours": 0
}
```

#### به‌روزرسانی قانون
```
POST /api/next/delay-compensation/rules/{id}
Body: {
  "rule_name": "نام قانون",
  "delay_start_minutes": 0,
  "delay_end_minutes": 30,
  "compensation_minutes": 0,
  "auto_leave_hours": false,
  "auto_leave_duration_hours": 0,
  "is_active": true
}
```

#### حذف قانون
```
DELETE /api/next/delay-compensation/rules/{id}
```

### پردازش جبران تاخیر

#### پردازش تاخیر برای یک رکورد تردد
```
POST /api/next/delay-compensation/process
Body: {
  "attendance_id": 1
}
```

#### ثبت جبران خدمت انجام شده
```
POST /api/next/delay-compensation/record
Body: {
  "compensation_id": 1,
  "minutes_completed": 10
}
```

#### دریافت گزارش جبران تاخیر کاربر
```
GET /api/next/delay-compensation/user?user_id=1&start_date=2024-01-01&end_date=2024-12-31
```

#### دریافت لیست تمام جبران تاخیرها (ادمین/سوپروایزر)
```
GET /api/next/delay-compensation/all
```

## پنل مدیریت Next.js

### دسترسی به صفحه مدیریت
در داشبورد Next.js، به مسیر `/dashboard/delay-compensation` دسترسی دارید.

### امکانات:
- مشاهده لیست تمام قوانین جبران تاخیر
- ایجاد قانون جدید
- ویرایش قوانین موجود
- حذف قوانین
- مشاهده گزارش جبران تاخیرها
- ثبت جبران خدمت انجام شده

## پیاده‌سازی Next.js

### کامپوننت‌های فرانت‌آند

#### 1. سرویس API
`services/delayCompensationService.ts` - تمام توابع مورد نیاز برای ارتباط با API

#### 2. کامپوننت مدیریت قوانین
`components/staff/DelayCompensationRulesTable.tsx` - جدول مدیریت قوانین جبران تاخیر

#### 3. کامپوننت گزارش‌گیری
`components/staff/DelayCompensationRecordsTable.tsx` - جدول نمایش و مدیریت رکوردهای جبران تاخیر

#### 4. صفحه مدیریت
`app/dashboard/delay-compensation/page.tsx` - صفحه اصلی مدیریت با تب‌بندی

### استفاده در کامپوننت‌ها

```tsx
import { getDelayCompensationRules, createDelayCompensationRule } from '@/services/delayCompensationService';

// دریافت قوانین
const { data } = await getDelayCompensationRules();

// ایجاد قانون جدید
const result = await createDelayCompensationRule({
  rule_name: 'نام قانون',
  delay_start_minutes: 0,
  delay_end_minutes: 30,
  compensation_minutes: 0,
  auto_leave_hours: false,
  auto_leave_duration_hours: 0,
  is_active: true,
});
```

## نحوه استفاده یکپارچه

### ۱. تنظیم اولیه کاربران
برای هر کاربر، باید MAC Address دستگاه‌های مجاز را ثبت کنید:
```sql
UPDATE users SET mac_address_1 = '00:1A:2B:3C:4D:5E', mac_address_2 = '00:1A:2B:3C:4D:5F' WHERE id = 1;
```

### ۲. تنظیم ساعت کاری
ابتدا باید شیفت کاری را از طریق پنل ادمین یا API تنظیم کنید:
```
POST /api/next/hr/admin/store-shift
Body: {
  "name": "شیفت ثابت صبح",
  "shift_start": "08:00",
  "shift_end": "16:00",
  "allowed_delay_minutes": 15
}
```

### ۳. ثبت تعطیلات (اختیاری)
برای اعمال قانون در روزهای تعطیل، تعطیلات را ثبت کنید:
```
POST /api/next/hr/admin/store-holiday
Body: {
  "holiday_date_shamsi": "1405/01/01",
  "title": "عید نوروز"
}
```

### ۴. ثبت ورود/خروج خودکار (یکپارچه)
هنگام ثبت ورود/خروج کارکنان، سیستم به صورت خودکار:
- MAC Address دستگاه را اعتبارسنجی می‌کند
- تاخیر را محاسبه می‌کند
- قانون مناسب را اعمال می‌کند
- جبران تاخیر را ثبت می‌کند
- در صورت نیاز مرخصی خودکار ایجاد می‌کند

```
POST /api/next/hr/attendance/toggle
Headers: {
  "Authorization": "Bearer {token}",
  "X-Client-MAC": "00:1A:2B:3C:4D:5E"
}
Body: {
  "date_shamsi": "1405/04/14"
}
```

### ۵. ثبت مرخصی با محدودیت 8.5%
سیستم به صورت خودکار محدودیت ماهانه را اعتبارسنجی می‌کند:
```
POST /api/next/hr/leaves/store
Body: {
  "leave_type": "daily_vacation",
  "start_timestamp": 1234567890,
  "end_timestamp": 1234567890 + 86400,
  "reason": "علت مرخصی"
}
```

اگر سقف ماهانه پر شده باشد، سیستم خطا برمی‌گرداند.

## سرویس DelayCompensationService

متدهای اصلی سرویس:

### processAttendanceDelay(AttendanceClock $attendance)
پردازش و محاسبه جبران تاخیر برای یک رکورد تردد

### recordCompensationCompleted(int $compensationId, int $minutesCompleted)
ثبت جبران خدمت انجام شده

### getUserCompensationReport(int $userId, string $startDate = null, string $endDate = null)
دریافت گزارش جبران تاخیر کاربر

## مثال عملی

فرض کنید کارمندی ساعت ۸:۳۰ وارد می‌شود (۳۰ دقیقه تاخیر):

1. سیستم تاخیر را محاسبه می‌کند: ۳۰ دقیقه
2. قانون مناسب را پیدا می‌کند: نیم ساعت اول (۰-۳۰ دقیقه)
3. جبران خدمت مورد نیاز: ۰ دقیقه
4. رکورد در جدول next_delay_compensations ثبت می‌شود

اگر همان کارمند ساعت ۹:۱۵ وارد شود (۷۵ دقیقه تاخیر):

1. سیستم تاخیر را محاسبه می‌کند: ۷۵ دقیقه
2. قانون مناسب را پیدا می‌کند: نیم ساعت سوم (۶۰-۹۰ دقیقه)
3. جبران خدمت مورد نیاز: ۲۰ دقیقه
4. رکورد در جدول next_delay_compensations ثبت می‌شود

اگر کارمند ساعت ۱۰:۰۰ وارد شود (۱۲۰ دقیقه تاخیر):

1. سیستم تاخیر را محاسبه می‌کند: ۱۲۰ دقیقه
2. قانون مناسب را پیدا می‌کند: نیم ساعت چهارم (۹۰-۱۲۰ دقیقه)
3. جبران خدمت مورد نیاز: ۳۰ دقیقه
4. مرخصی ۴ ساعته خودکار ثبت می‌شود
5. رکورد در جدول next_delay_compensations ثبت می‌شود

## نکات مهم

1. **قوانین قابل تنظیم هستند:** ادمین می‌تواند قوانین را مطابق با سیاست شرکت تغییر دهد
2. **مرخصی خودکار:** در صورت تاخیر بیش از ۹۰ دقیقه، مرخصی ۴ ساعته به صورت خودکار ثبت می‌شود
3. **گزارش‌گیری:** می‌توان گزارش کاملی از جبران تاخیرها را دریافت کرد
4. **ادغام با سیستم حضور و غیاب:** سیستم با existing attendance system یکپارچه است
