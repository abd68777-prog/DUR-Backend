# مفاتيح اختبار

أزواج RSA ثابتة تستخدمها `tests/Feature/ClerkMultiIssuerTest.php` لتوقيع توكنات
JWT حقيقية والتحقق منها.

**هي مفاتيح اختبار فقط، ما بتحمي ولا شي حقيقي.** ما إلها أي علاقة بمفاتيح Clerk
ولا بأي بيئة تشغيل، وموجودة بالريبو عن قصد.

ليش fixtures ثابتة بدل `openssl_pkey_new()` بكل تست:

- توليد مفتاح 2048-bit مرّتين لكل تست بطيء بلا فايدة
- `openssl_pkey_new()` بيفشل على أي جهاز ما عنده `openssl.cnf` مضبوط (شائع على
  Windows)، فالتستات كانت تنكسر لسبب ما إله علاقة بالكود المفحوص

للتوليد من جديد لو لزم:

```bash
cd tests/Fixtures/keys
for n in prod dev; do
  openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "${n}-private.pem"
  openssl rsa -in "${n}-private.pem" -pubout -out "${n}-public.pem"
done
```
