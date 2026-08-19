# دليل النشر (Deployment)

هذا الملف موجّه للشخص اللي رح يجهّز السيرفر وينشر هالمشروع (Backend فقط — الـ API). المشروع مربوط بـ GitHub Actions (`.github/workflows/ci-cd.yml`) بحيث كل push على branch `main`:

1. يشغّل الاختبارات (`php artisan test`) وفحص التنسيق (`vendor/bin/pint --test`).
2. لو نجحوا، يتصل بالسيرفر عبر SSH وينفّذ خطوات النشر تلقائياً.

## 1. متطلبات السيرفر

- Ubuntu 22.04+ (أو أي توزيعة Linux مشابهة)
- PHP 8.3+ مع الإضافات: `mbstring, pdo_mysql, gd, zip, bcmath, curl, fileinfo` (لاحظ: 8.3 مطلوب فعلياً بسبب `ronasit/laravel-clerk`، مش 8.2)
- Composer
- MySQL 8+ (أو أي قاعدة بيانات متوافقة)
- Nginx
- Supervisor (لتشغيل queue worker باستمرار)
- Git

> ملاحظة: هذا backend API فقط (بدون Blade views حقيقية)، فما في داعي لـ Node/npm build بالسيرفر.

## 2. الإعداد الأولي (مرة وحدة، يدوي)

```bash
# استنساخ المشروع
cd /var/www
git clone https://github.com/abd68777-prog/DUR-Backend.git dur-backend
cd dur-backend

# البيئة
cp .env.example .env
nano .env   # عبّي القيم الحقيقية (راجع القسم 3 تحت)

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

# صلاحيات
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```

## 3. قيم `.env` اللي لازم تتعبّى بالإنتاج

| المتغيّر | القيمة |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` **(مهم جداً — أمان)** |
| `APP_URL` | دومين الـ API الحقيقي (https) |
| `DB_*` | بيانات قاعدة بيانات الإنتاج (مش root، يوزر بصلاحيات محدودة) |
| `LOG_LEVEL` | `error` |
| `CLERK_SECRET_KEY` | مفتاح `sk_live_...` (مش test) من لوحة Clerk |
| `CLERK_ALLOWED_ISSUER` | من لوحة Clerk |
| `CLERK_ALLOWED_ORIGINS` | دومين الفرونت-إند الحقيقي |
| `CORS_ALLOWED_ORIGINS` | نفس دومين الفرونت-إند (لازم يطابق `CLERK_ALLOWED_ORIGINS`) |
| `CLOUDINARY_URL` | من لوحة Cloudinary |

`clerk.pem` (ملف JWT public key) لازم يترفع يدوياً لمجلد المشروع بالسيرفر — مش موجود بالـ git (متجاهل عن قصد لأنه حساس).

## 4. Nginx (مثال)

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/dur-backend/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

أضف SSL عبر `certbot --nginx` بعد ما الدومين يشاور عالسيرفر.

## 5. Queue Worker (Supervisor)

```ini
; /etc/supervisor/conf.d/dur-backend-worker.conf
[program:dur-backend-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dur-backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dur-backend/storage/logs/worker.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start dur-backend-worker:*
```

## 6. ربط الـ CI/CD (GitHub Actions)

### مستخدم SSH مخصّص للنشر

```bash
adduser deployer
usermod -aG www-data deployer
# اعطي deployer صلاحية كتابة على /var/www/dur-backend
```

### مفتاح SSH

على جهازك (مش السيرفر):
```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f dur_deploy_key
```

- انسخ محتوى `dur_deploy_key.pub` لملف `~/.ssh/authorized_keys` تبع اليوزر `deployer` بالسيرفر.
- خد محتوى `dur_deploy_key` (المفتاح الخاص) — رح تحطه بـ GitHub Secrets.

### GitHub Secrets المطلوبة

بمستودع GitHub: **Settings → Secrets and variables → Actions → New repository secret**

| الاسم | القيمة |
|---|---|
| `SSH_HOST` | IP أو دومين السيرفر |
| `SSH_USERNAME` | `deployer` |
| `SSH_PRIVATE_KEY` | محتوى المفتاح الخاص (`dur_deploy_key`) كامل |
| `SSH_PORT` | `22` (أو المنفذ المخصّص، اختياري) |
| `DEPLOY_PATH` | `/var/www/dur-backend` |

بعد إضافة هالأسرار، أي `git push` على `main` رح يشغّل الاختبارات تلقائياً، ولو نجحوا رح يعمل SSH للسيرفر وينفّذ:

```bash
git fetch origin main
git reset --hard origin/main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan l5-swagger:generate
php artisan queue:restart
```

> ⚠️ **مهم**: `git reset --hard` بيمسح أي تعديل يدوي غير محفوظ (uncommitted) عالسيرفر بدون تحذير. مجلد المشروع بالسيرفر لازم يضل مطابق تماماً للريبو دايماً — أي تعديل مطلوب (حتى لو مستعجل) لازم يصير عبر commit وpush، مش تعديل مباشر بالملفات عالسيرفر. لو ما استخدمنا `reset --hard`، أي drift بسيط (حتى تعديل تجريبي واحد بالغلط) بيوقف كل عمليات النشر التلقائي القادمة بخطأ "local changes would be overwritten by merge".

## 7. التراجع عند وجود مشكلة (Rollback)

```bash
ssh deployer@server
cd /var/www/dur-backend
git log --oneline -5      # حدد الكوميت السليم
git checkout <commit-hash>
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # فقط لو في migration لازم ترجع، وبحذر
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan l5-swagger:generate
php artisan queue:restart
```