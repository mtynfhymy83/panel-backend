# برنامه تست APIهای پنل پردیس

## خلاصه
هدف تست این است که بفهمیم هر خطا مربوط به فرانت است، بک‌اند است، یا داده/دسترسی تستی مشکل دارد. تست را همیشه با این ترتیب انجام بده: اول Swagger، بعد فرانت، بعد Network مرورگر. اگر یک درخواست در Swagger هم خراب بود، احتمالاً گزارش به بک‌اند لازم دارد. اگر در Swagger درست بود ولی فرانت خراب بود، اول درخواست فرانت را با Swagger مقایسه می‌کنیم.

## آماده‌سازی تست
- سرور فرانت را اجرا کن: `npm run dev`
- آدرس تست فرانت: `http://127.0.0.1:5173/`
- Swagger بک‌اند: `https://panel.dev.pardis-book.ir/docs`
- در مرورگر `DevTools > Network` را باز کن و تیک `Preserve log` را بزن.
- قبل از شروع هر سناریوی مهم، `localStorage` را پاک کن یا از incognito استفاده کن.
- برای هر نقش حداقل یک کاربر تست داشته باش:
  - `student`
  - `admin`
  - `teacher`
  - `examiner`
  - اگر ممکن است یک کاربر چندنقشی برای تست role switch

## روش تشخیص مقصر خطا
- اگر درخواست در Swagger با همان body/token خطا می‌دهد: گزارش به بک‌اند.
- اگر Swagger موفق است ولی فرانت خطا می‌دهد: Network را نگاه کن؛ اگر مسیر، method، body یا token فرق دارد، مشکل فرانت است.
- اگر مسیر و body یکی است ولی فرانت خطای CORS، proxy یا cookie/token دارد: مشکل config/frontend یا تنظیمات دسترسی بک‌اند است.
- اگر پاسخ `403` آمد: نقش کاربر، active role token و دسترسی آن endpoint را چک کن.
- اگر پاسخ `422` آمد: body اشتباه یا validation بک‌اند است.
- اگر پاسخ `404` آمد: مسیر اشتباه است یا endpoint در بک‌اند وجود ندارد.
- اگر پاسخ `500` آمد: معمولاً بک‌اند باید بررسی کند، مخصوصاً اگر Swagger هم همان خطا را بدهد.

## سناریوهای تست مرحله‌به‌مرحله

### 1. Auth
- ثبت‌نام:
  - در فرانت ثبت‌نام کن.
  - باید `POST /api/auth/register/otp` با body شامل `firstName`, `lastName`, `phone`, `password` ارسال شود.
  - بعد کد را وارد کن.
  - باید `POST /api/auth/register/verify` با `phone`, `code` ارسال شود.
  - انتظار: `201`، دریافت `data.token` و ورود به پنل دانش‌آموز.
- ورود با رمز:
  - از صفحه ورود با شماره و رمز همان کاربر وارد شو.
  - انتظار: `POST /api/auth/login`، status `200`، token ذخیره شود.
- ورود با کد پیامکی:
  - فراموشی رمز/ورود با کد را تست کن.
  - انتظار: verify با body `phone`, `code` باشد، نه `otp`.
- خروج:
  - روی خروج بزن.
  - انتظار: `POST /api/auth/logout` فقط یک بار اجرا شود و کاربر به login برگردد.
- پروفایل:
  - صفحه profile را باز کن.
  - انتظار: `GET /api/me` موفق باشد.
  - ویرایش نام باید `PATCH /api/me` با `firstName`, `lastName` بفرستد.
  - تغییر رمز باید `PATCH /api/me/password` با `currentPassword`, `newPassword` بفرستد.

### 2. Role Switch
- با کاربر چندنقشی وارد شو.
- یکی از نقش‌ها را انتخاب کن.
- انتظار:
  - اول login موفق شود.
  - بعد `POST /api/me/active-role` با `{ role }` اجرا شود.
  - token جدید ذخیره شود.
  - dashboard همان نقش بدون `403` باز شود.
- اگر dashboard با `403` باز شد، token active role را با بک‌اند چک کن.

### 3. Admin
- Dashboard:
  - ورود با admin.
  - انتظار: `GET /api/admin/dashboard`.
- Users:
  - صفحه کاربران/اساتید را باز کن.
  - انتظار: `GET /api/admin/users`.
  - تغییر نقش: `PATCH /api/admin/users/{user}/roles`.
  - حذف کاربر: `DELETE /api/admin/users/{user}`.
- Classes:
  - لیست کلاس‌ها: `GET /api/admin/classes`.
  - ساخت کلاس: `POST /api/admin/classes` با `name`, `level`.
  - ویرایش: `PATCH /api/admin/classes/{class}`.
  - حذف: `DELETE /api/admin/classes/{class}`.
  - افزودن عضو: `POST /api/admin/classes/{class}/members` با `userId`, `role`.
  - حذف عضو: `DELETE /api/admin/classes/{class}/members/{user}?role=...`.
- Messages:
  - لیست پیام‌ها: `GET /api/admin/messages`.
  - بررسی پیام: `PATCH /api/admin/messages/{message}/review`.
  - پاسخ پیام: `PATCH /api/admin/messages/{message}/reply` با `adminReply`.
- Terms/Exams/Progress:
  - `GET /api/admin/terms`
  - `GET /api/admin/exams`
  - `GET /api/admin/progress?class_id=...`
- Teacher feedback:
  - لیست: `GET /api/admin/teacher-feedbacks`
  - ثبت: `POST /api/admin/teacher-feedbacks` با `teacherId`, `classId`, `strengths`, `improvements`, `gems`.

### 4. Teacher
- Dashboard:
  - انتظار: `GET /api/teacher/dashboard`.
- Classes:
  - انتظار: `GET /api/teacher/classes`.
  - اگر کلاس‌ها خالی بود ولی در admin استاد به کلاس اضافه شده، این مورد را به بک‌اند گزارش کن.
- Students:
  - در صفحه ثبت نمره، برای کلاس انتخابی باید `GET /api/teacher/classes/{class}/students` اجرا شود.
- Terms:
  - شروع ترم: `POST /api/teacher/classes/{class}/terms` با `name`, `startDate`.
  - پایان ترم: `PATCH /api/teacher/terms/{term}/end` با `endDate`.
- Grades:
  - ثبت نمره/فیدبک: `POST /api/teacher/grades` با `termId`, `studentId`, `criteriaScores`, `score`, `feedback`.
- Feedbacks:
  - لیست بازخوردهای مدیر: `GET /api/teacher/feedbacks`.
  - mark seen: `PATCH /api/teacher/feedbacks/mark-seen`.

### 5. Examiner
- Dashboard:
  - انتظار: `GET /api/examiner/dashboard`.
- Exam:
  - برای کلاس انتخابی: `GET /api/examiner/classes/{class}/exam`.
  - ثبت آزمون: `POST /api/examiner/classes/{class}/exam` با `examDate`, `studentScores`, `classStrengths`, `classImprovements`, `classSuggestions`.
  - ثبت دوباره برای همان ترم باید طبق Swagger احتمالاً `409` بدهد.
- اگر فرانت دانش‌آموزهای کلاس را نشان نداد:
  - response `GET /api/examiner/classes/{class}/exam` باید فیلد `students` داشته باشد.
  - اگر خالی بود، membership کلاس را در admin چک کن.

### 6. Student
- Dashboard:
  - انتظار: `GET /api/student/dashboard`.
  - باید نمره‌ها/ترم‌های تمام‌شده را برگرداند.
- Messages:
  - لیست پیام‌ها: `GET /api/student/messages`.
  - ساخت پیام: `POST /api/student/messages` با `type`, `title`, `body`, و در صورت نیاز `classId`.
  - اگر پیام ساخته شد ولی در admin دیده نشد، هر دو endpoint student/admin را با یک message id بررسی کن.

## وضعیت پیاده‌سازی بک‌اند (برای تست)

| سناریو | Endpoint | فیلدهای مهم response |
|--------|----------|----------------------|
| Teacher classes | `GET /api/teacher/classes` | `activeTerm`, `exams`, `examCount`, `students` |
| Teacher class detail | `GET /api/teacher/classes/{class}` | همان فیلدهای بالا داخل `class` |
| Teacher students | `GET /api/teacher/classes/{class}/students` | `studentIds`, `students[{id, fullName}]` |
| Teacher exams | `GET /api/teacher/classes/{class}/exams` | `examDate`, `studentScores`, `examiner` |
| Teacher terms | `POST .../terms`, `PATCH .../terms/{term}/end` | `startDate`/`start_date`, `endDate`/`end_date` |
| Examiner dashboard | `GET /api/examiner/dashboard` | `classes`, `exams`, `classesCount` |
| Examiner exam | `GET /api/examiner/classes/{class}/exam` | `activeTerm`, `exam`, `students` |
| Student dashboard | `GET /api/student/dashboard` | `grades`, `endedTerms`, `classes[{teachers}]` |
| Student messages | `GET/POST /api/student/messages` | `classId`, `class{id,name,level}`, type شامل `request` |
| Admin progress | `GET /api/admin/progress?class_id=` | `students`, `exams`, `grades`, `criteria` |
| Admin messages | `GET /api/admin/messages` | `student{id,fullName,phone}`, `class{id,name,level}` |

## قالب گزارش خطا برای بک‌اند
برای هر خطا این قالب را پر کن:

```txt
Title:
Role:
Endpoint:
Method:
Request body:
Status code:
Actual response:
Expected response according to Swagger:
Token active role:
Steps to reproduce:
Swagger result with same data:
Frontend Network screenshot:
Verdict: Backend / Frontend / Test data unclear
```

نمونه کوتاه:

```txt
Title: Teacher classes returns empty after assigning teacher to class
Role: teacher
Endpoint: /api/teacher/classes
Method: GET
Status code: 200
Actual response: data.classes = []
Expected: class assigned to this teacher should be returned
Swagger result: same empty result
Verdict: Backend or test data relation issue
```

## معیار قبول نهایی
- ثبت‌نام، ورود، خروج و پروفایل بدون خطای Network کار کنند.
- هر نقش dashboard خودش را بدون `403/404` باز کند.
- admin بتواند کاربر، کلاس، عضو کلاس، پیام و feedback استاد را مدیریت کند.
- teacher بتواند کلاس‌های خودش را ببیند، ترم بسازد/تمام کند و grade ثبت کند.
- examiner بتواند آزمون کلاس را بگیرد و ثبت کند.
- student بتواند dashboard و پیام‌های خودش را ببیند و پیام جدید بسازد.
- هر خطای باقی‌مانده با قالب بالا مستند شود و مشخص باشد سمت فرانت است یا بک‌اند.
