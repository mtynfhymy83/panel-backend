# Pardis Panel Backend Plan - Updated After Latest Frontend Changes

## Summary
- بک‌اند Laravel در `backend/` ساخته شود و فرانت Vite فعلی بدون تغییر URL صفحات به API وصل شود.
- احراز هویت با Laravel Sanctum SPA session، cookie httpOnly و CSRF انجام شود؛ هیچ token یا داده حساس در localStorage ذخیره نشود.
- منطق اصلی سیستم ترم‌محور است: کلاس، اعضا، ترم، نمره استاد، آزمون آزمون‌گیرنده، گزارش زبان‌آموز و نمودارها همگی با `term_id` کنترل می‌شوند.
- ادمین کلاس و کاربران را مدیریت می‌کند؛ استاد ترم را شروع/پایان می‌دهد؛ زبان‌آموز نتیجه ترم را فقط بعد از پایان ترم می‌بیند.

## Key Backend Rules
- کاربران چندنقشی هستند: `admin`, `teacher`, `student`, `examiner`.
- حذف کاربر توسط ادمین باید `soft delete` باشد، نه حذف فیزیکی؛ کاربر از عضویت کلاس‌های فعال detach شود، اما سوابق نمره، آزمون، پیام و فیدبک حفظ شوند.
- حذف حساب فعلی ادمین و حذف کاربران دارای نقش `admin` از UI/API معمولی ممنوع باشد.
- شماره موبایل ایرانی باید در بک‌اند validate شود: `^09\d{9}$`.
- `username` و `phone` یکتا باشند و soft-deleted users هم برای حفظ امنیت و تاریخچه قابل reuse نباشند.
- OTP برای سه جریان لازم است:
  - ثبت‌نام
  - ورود با کد فراموشی رمز
  - تغییر شماره تماس در پروفایل
- OTP شش رقمی، دارای expiry پنج دقیقه، hash شده در cache/Redis، با rate limit و حداکثر تلاش اشتباه باشد.
- در production پاسخ‌های عمومی نباید با متن متفاوت باعث user enumeration شوند.
- تاریخ‌ها در دیتابیس Gregorian/ISO ذخیره شوند؛ نمایش شمسی فقط وظیفه فرانت است.

## Database Schema
- `users`
  - `id`, `full_name`, `username` unique, `phone` unique nullable, `password`, `deleted_at`, timestamps

- `user_roles`
  - `id`, `user_id`, `role`, unique `user_id + role`

- `course_classes`
  - `id`, `name`, `level`, timestamps

- `class_memberships`
  - `id`, `course_class_id`, `user_id`, `role`, timestamps
  - unique `course_class_id + user_id + role`
  - role فقط یکی از `teacher`, `student`, `examiner`

- `terms`
  - `id`, `course_class_id`, `name`, `start_date`, `end_date` nullable
  - `is_active`, `created_by_teacher_id`, `ended_by_teacher_id`, `ended_at`
  - `closed_student_ids` JSON برای snapshot زبان‌آموزهای زمان پایان ترم
  - timestamps
  - فقط یک ترم فعال برای هر کلاس مجاز است.

- `teacher_grades`
  - `id`, `course_class_id`, `term_id`, `student_id`
  - `teacher_id`, `created_by_teacher_id`, `updated_by_teacher_id`
  - `criteria_scores` JSON, `score`, `total_score`, `max_total_score`
  - `feedback` JSON شامل strengths/improvements/smartFeedback
  - timestamps
  - unique `term_id + student_id`

- `exams`
  - `id`, `course_class_id`, `term_id`, `examiner_id`, `exam_date`
  - `student_scores` JSON یا جدول جدا طبق پیاده‌سازی Laravel
  - `class_strengths`, `class_improvements`, `class_suggestions`
  - timestamps
  - unique `term_id + course_class_id + examiner_id`

- `student_messages`
  - `id`, `student_id`, `course_class_id` nullable, `type`, `title`, `body`
  - `status`, `admin_reply`, `admin_reply_by`, `reviewed_at`, `replied_at`, `student_seen_at`
  - timestamps

- `student_message_teachers`
  - `student_message_id`, `teacher_id`

- `teacher_feedbacks`
  - `id`, `teacher_id`, `course_class_id`, `admin_id`
  - `strengths`, `improvements`, `gems`, `teacher_seen_at`
  - timestamps

## API Contract
Auth:
- `GET /sanctum/csrf-cookie`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/me`
- `PATCH /api/me`
- `PATCH /api/me/password`
- `POST /api/me/active-role`
- `POST /api/auth/register/otp`
- `POST /api/auth/register/verify`
- `POST /api/auth/password-login/otp`
- `POST /api/auth/password-login/verify`
- `POST /api/me/phone/otp`
- `PATCH /api/me/phone/verify`

Admin:
- `GET /api/admin/dashboard`
- `GET /api/admin/users?role=&search=&page=`
- `PATCH /api/admin/users/{user}/roles`
- `DELETE /api/admin/users/{user}`
- `GET /api/admin/classes?search=&page=`
- `POST /api/admin/classes`
- `PATCH /api/admin/classes/{class}`
- `DELETE /api/admin/classes/{class}`
- `POST /api/admin/classes/{class}/members`
- `DELETE /api/admin/classes/{class}/members/{user}?role=`
- `GET /api/admin/terms?class_id=&page=` فقط read-only
- `GET /api/admin/exams?class_id=&level=&page=`
- `GET /api/admin/progress?class_id=`
- `GET /api/admin/messages?status=&type=&search=&page=`
- `PATCH /api/admin/messages/{message}/review`
- `PATCH /api/admin/messages/{message}/reply`
- `GET /api/admin/teacher-feedbacks?teacher_id=&class_id=&page=`
- `POST /api/admin/teacher-feedbacks`

Teacher:
- `GET /api/teacher/dashboard`
- `GET /api/teacher/classes`
- `POST /api/teacher/classes/{class}/terms`
- `PATCH /api/teacher/terms/{term}/end`
- `GET /api/teacher/classes/{class}`
- `GET /api/teacher/classes/{class}/students`
- `POST /api/teacher/grades`
- `GET /api/teacher/feedbacks?page=`
- `PATCH /api/teacher/feedbacks/mark-seen`

Examiner:
- `GET /api/examiner/dashboard`
- `GET /api/examiner/classes/{class}/exam`
- `POST /api/examiner/classes/{class}/exam`

Student:
- `GET /api/student/dashboard`
- `GET /api/student/messages?page=`
- `POST /api/student/messages`
- `PATCH /api/student/messages/mark-seen`

## Critical Business Logic
- استاد فقط برای کلاس‌هایی که عضو آن‌ها با نقش `teacher` است می‌تواند ترم بسازد یا پایان دهد.
- ساخت ترم جدید فقط وقتی مجاز است که آن کلاس ترم فعال نداشته باشد.
- تاریخ شروع ترم جدید نباید قبل از تاریخ پایان آخرین ترم پایان‌یافته همان کلاس باشد.
- هنگام شروع ترم فقط `start_date` گرفته شود؛ `end_date` هنگام پایان ترم ثبت شود.
- پایان ترم فقط وقتی مجاز است که همه زبان‌آموزهای فعلی کلاس برای همان `term_id` نمره داشته باشند.
- بعد از پایان ترم، `closed_student_ids` با فهرست زبان‌آموزهای همان لحظه ذخیره شود.
- API داشبورد زبان‌آموز فقط gradeهای ترم‌های پایان‌یافته را برگرداند؛ ترم فعال حتی اگر نمره داشته باشد برای زبان‌آموز publish نشود.
- آزمون‌گیرنده فقط از ترم فعال کلاس آزمون می‌گیرد.
- ثبت آزمون تکراری برای `term_id + class_id + examiner_id` باید `409 Conflict` بدهد.
- اگر استاد یا آزمون‌گیرنده وسط ترم عوض شود، عضو فعلی کلاس اجازه ادامه کار همان ترم فعال را دارد.
- گزارش‌های admin می‌توانند سوابق historical کاربران soft-deleted را با برچسبی مثل «کاربر حذف‌شده» نشان دهند.

## Security And Production Requirements
- تمام create/update/deleteها با Form Request validation و Policy/Gate محافظت شوند.
- تمام تغییرات حساس داخل transaction انجام شوند: ثبت‌نام با OTP، تغییر شماره، تغییر نقش، عضویت کلاس، ساخت/پایان ترم، ثبت نمره، ثبت آزمون، حذف کاربر.
- برای APIهای authenticated هدر `Cache-Control: no-store, private` ارسال شود.
- `password`, `remember_token`, raw OTP و hash OTP هرگز در response یا log نیاید.
- برای OTP، login و مسیرهای حساس rate limit جداگانه تعریف شود.
- همه authorizationها در بک‌اند enforce شوند؛ role check فرانت فقط UX است.

## Response Shape
- API Resources فیلدها را camelCase برگردانند تا با normalizerهای فعلی فرانت سازگار باشد.
- User:
```json
{
  "id": 1,
  "fullName": "Abbas Fahimi",
  "username": "abbas",
  "phone": "09120000000",
  "role": "teacher",
  "roles": ["teacher", "examiner"],
  "createdAt": "2026-06-15T10:00:00Z"
}
```
- Class:
```json
{
  "id": 1,
  "name": "TopNoch FunA",
  "level": "4",
  "teacherIds": [2],
  "studentIds": [5, 6],
  "examinerIds": [3],
  "createdAt": "2026-06-15T10:00:00Z"
}
```
- Term:
```json
{
  "id": 10,
  "classId": 1,
  "name": "ترم اردیبهشت ۱۴۰۵",
  "startDate": "2026-04-21",
  "endDate": "2026-05-21",
  "isActive": false,
  "createdByTeacherId": 2,
  "endedByTeacherId": 3,
  "endedAt": "2026-05-21T10:00:00Z"
}
```

## Implementation Steps
1. Scaffold Laravel in `backend/`, configure MySQL, Sanctum, CORS and stateful domains for `localhost:3000` and production.
2. Add migrations/models/resources/seeders for users, roles, classes, memberships, terms, grades, exams, messages and teacher feedback.
3. Seed default admin from env, never from hardcoded production password.
4. Add enums/constants for roles, levels, message types, statuses, OTP purposes and evaluation criteria keys.
5. Implement `SmsService` with fake local driver and provider-ready production interface.
6. Implement Auth + OTP endpoints first, then profile phone change OTP.
7. Implement admin user/class/member APIs, including safe soft-delete user.
8. Implement teacher term and grade APIs with term-completion validation.
9. Implement examiner exam APIs with active-term and duplicate-exam validation.
10. Implement student dashboard API using only published/ended terms.
11. Implement messages, replies, unread counts, teacher feedback and gems.
12. Add feature tests before frontend integration.
13. Integrate frontend gradually through existing `src/js/api/*` modules; do not create a new API layer.

## Test Plan
- Registration OTP rejects invalid Iranian phone and duplicate phone.
- Registration verify creates only a student user after correct OTP.
- Username and phone uniqueness are enforced by backend.
- Forgot-password OTP logs user in after correct code without changing password.
- Profile phone change requires OTP and rejects duplicate phone.
- Multi-role users can choose/switch active role.
- Admin can assign/remove roles and class memberships.
- Admin can soft-delete non-admin users; deleted users cannot log in; historical reports remain readable.
- Teacher can create a term only for assigned classes.
- Teacher cannot start a term before previous term end date.
- Teacher cannot end term before grading all current students.
- Student dashboard hides grades from active/unended terms.
- Student dashboard shows latest ended term and previous ended terms.
- Examiner cannot start/submit exam when class has no active term.
- Examiner can submit once per active term and again in later terms.
- Admin messages can be reviewed/replied once reviewed without reverting to unreviewed.
- Teacher feedback unread badge appears until seen.
- Unauthorized users get `403`, unauthenticated users get `401`, duplicates/conflicts get `409`, validation gets `422`.

## Assumptions
- Backend target is Laravel + MySQL + Sanctum SPA auth.
- Redis/cache is available in production for OTP; file/database cache فقط برای local مجاز است.
- SMS provider انتخاب نشده، اما باید پشت interface باشد.
- `examiner_assignments` جدول جدا لازم نیست؛ عضویت آزمون‌گیرنده در کلاس با `class_memberships.role = examiner` کافی است.
- نتایج تاریخی با soft-deleted users حفظ می‌شوند و reuse شماره/نام کاربری بعد از حذف در v1 مجاز نیست.
