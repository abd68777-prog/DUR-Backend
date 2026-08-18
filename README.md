# DUR — Backend API

Backend لمتجر مجوهرات (ذهب ومجوهرات) مبني على Laravel 12، بيوفّر API لإدارة المنتجات والتصنيفات، مع مصادقة عبر Clerk وتخزين صور على Cloudinary CDN.

## المكدّس التقني

| الطبقة | التقنية |
|---|---|
| Framework | Laravel 12 (PHP 8.3+) |
| المصادقة | [Clerk](https://clerk.com) عبر `ronasit/laravel-clerk` |
| قاعدة البيانات | MySQL |
| تخزين الصور | Cloudinary (عبر `cloudinary-labs/cloudinary-laravel`) |
| API tokens | Laravel Sanctum |
| Webhooks | Svix (للتحقق من توقيع Clerk webhooks) |

## المصادقة والصلاحيات

المصادقة بالكامل عبر **Clerk** (مش نظام auth تقليدي بـ Laravel). الـ middleware `auth:clerk` بيتحقق من الـ JWT الجاي من الفرونت-إند، والـ middleware `role:{roles}` (`EnsureUserHasRole`) بيتحقق من دور المستخدم (`role` column بجدول `users`).

الأدوار المستخدمة: `admin`, `manager`, `customer`.

مستخدمين جدد بينضافوا تلقائياً لقاعدة البيانات عبر **Clerk webhook** (`POST /api/webhooks/clerk`، حدث `user.created`) — يتم التحقق من توقيع الـ webhook عبر Svix قبل المعالجة.

## هيكلية الصلاحيات على الـ API

| المجموعة | من يقدر يوصل |
|---|---|
| `GET /api/user` | أي مستخدم مسجّل دخول |
| `GET /api/products`, `GET /api/products/{id}` | أي مستخدم مسجّل دخول |
| `GET /api/categories`, `GET /api/categories/{id}` | أي مستخدم مسجّل دخول |
| `POST/PUT /api/products`, `POST/PUT /api/categories`, toggle-active, destroy-image | admin + manager |
| `DELETE /api/products/{id}`, `DELETE /api/categories/{id}` | admin فقط |
| `GET /api/dashboard` | admin + manager |
| `GET /api/dashboard/stats` | admin فقط |
| `POST /api/webhooks/clerk` | عام (محمي بتوقيع Svix) |

## الموديلات والعلاقات

```
Category  1 ── * Product  1 ── * ProductImage
```

- **Category**: `name`, `slug` (unique), `is_active`
- **Product**: `category_id`, `name`, `description`, `gold_weight`, `karat` (18/21/24), `gemstone_type`, `gemstone_carat`, `price`, `stock`, `is_active`
- **ProductImage**: `product_id`, `path` (Cloudinary public_id), `sort_order`, `is_primary` — عندها accessor `url` بيولّد رابط Cloudinary الكامل تلقائياً

حذف Category بيحذف منتجاتها تلقائياً (`cascadeOnDelete`). حذف Product بيحذف صوره من Cloudinary قبل حذف السجل.

## الـ Endpoints بالتفصيل

### Products
| Method | Path | الوصف |
|---|---|---|
| GET | `/api/products` | قائمة مع pagination، فلاتر: `category_id`, `is_active`, `search`, `per_page` |
| GET | `/api/products/{id}` | تفاصيل منتج مع الصور والتصنيف |
| POST | `/api/products` | إنشاء منتج (يدعم رفع صور متعددة `images[]`) |
| PUT | `/api/products/{id}` | تعديل منتج |
| PATCH | `/api/products/{id}/toggle-active` | تفعيل/تعطيل المنتج |
| DELETE | `/api/products/{id}` | حذف المنتج وصوره |
| DELETE | `/api/products/{id}/images/{image}` | حذف صورة واحدة |

### Categories
| Method | Path | الوصف |
|---|---|---|
| GET | `/api/categories` | قائمة كل التصنيفات |
| GET | `/api/categories/{id}` | تفاصيل تصنيف |
| POST | `/api/categories` | إنشاء تصنيف |
| PUT | `/api/categories/{id}` | تعديل تصنيف |
| DELETE | `/api/categories/{id}` | حذف تصنيف (يحذف منتجاته معه) |

### Dashboard
| Method | Path | الوصف |
|---|---|---|
| GET | `/api/dashboard/stats` | عدد المنتجات (كل/نشط/غير نشط)، توزيع حسب التصنيف، منتجات بمخزون أقل من 5، آخر 10 منتجات |

## معالجة الأخطاء

كل أخطاء `/api/*` ترجع بصيغة JSON موحّدة `{"message": "...", "errors"?: {...}}` بغض النظر عن الـ `Accept` header — معرّفة بـ `bootstrap/app.php`.

## CORS

مفعّل عبر `config/cors.php` على مسارات `api/*` فقط، والـ origins المسموحة بتتحدد من `CORS_ALLOWED_ORIGINS` بملف `.env` (قائمة مفصولة بفواصل).

## التخزين السحابي (Cloudinary)

صور المنتجات بتترفع مباشرة على Cloudinary عبر Laravel Filesystem disk مخصّص (`cloudinary`)، معرّف بـ `config/filesystems.php`. القيمة بتتحدد من `CLOUDINARY_URL` بملف `.env`:

```
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
```

الرفع لازم يصير عبر ملف حقيقي (`UploadedFile`/stream) — مش raw binary string مباشرة — لأن الـ SDK بيفسّر الـ string كمسار ملف أو base64 أو URL.

## الإعداد المحلي

```bash
composer install
cp .env.example .env
php artisan key:generate
# عدّل .env: DB_*, CLERK_*, CORS_ALLOWED_ORIGINS, CLOUDINARY_URL
php artisan migrate
php artisan serve
```

## الاختبارات والجودة

```bash
php artisan test        # 45 اختبار: Auth, Products, Categories, Dashboard, Webhook, CORS, Error responses
vendor/bin/pint --test  # فحص التنسيق
```

## النشر (Deployment)

المشروع مربوط بـ CI/CD عبر GitHub Actions (`.github/workflows/ci-cd.yml`) — كل push على `main` يشغّل الاختبارات وفحص التنسيق، ولو نجحت ينشر تلقائياً على السيرفر عبر SSH. تفاصيل إعداد السيرفر وربط الأسرار (Secrets) موجودة بـ [DEPLOYMENT.md](DEPLOYMENT.md).

## شو ناقص قبل التسليم الكامل

- [ ] قيم `.env` الإنتاجية الحقيقية (دومين، بيانات DB، مفاتيح Clerk live) — راجع [DEPLOYMENT.md](DEPLOYMENT.md)
- [ ] Seeders ببيانات تجريبية (اختياري، للديمو فقط)
- [ ] تجهيز السيرفر الفعلي وربط GitHub Secrets (خطوات مفصّلة بـ DEPLOYMENT.md)