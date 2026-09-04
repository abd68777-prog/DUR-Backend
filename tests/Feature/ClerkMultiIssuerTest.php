<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Tests\TestCase;

/**
 * التحقق الفعلي من توقيع التوكن.
 *
 * AuthTest بيستخدم actingAs يلي بيتخطّى الـ guard كلياً، فهدول أول اختبارات
 * بتمرق على المصادقة الحقيقية: منولّد أزواج مفاتيح RSA حقيقية، منوقّع JWT
 * فعلي، ومنبعته للـ API متل ما بيعمل الفرونت.
 */
class ClerkMultiIssuerTest extends TestCase
{
    use RefreshDatabase;

    private const PROD_ISSUER = 'https://clerk.durjewels.com';

    private const DEV_ISSUER = 'https://enabled-mammal-42.clerk.accounts.dev';

    protected function setUp(): void
    {
        parent::setUp();

        // الإنتاج بالمتغيّرات الأصلية، والتطوير بالمؤقتة - نفس شكل السيرفر.
        Config::set('clerk.allowed_issuer', self::PROD_ISSUER);
        Config::set('clerk.signer_key', $this->key('prod', 'public'));
        Config::set('clerk.secret_key', 'sk_test_dummy');
        Config::set('clerk.dev_issuer', self::DEV_ISSUER);
        Config::set('clerk.dev_signer_key', $this->key('dev', 'public'));
        Config::set('clerk.allowed_origins', ['https://durjewels.com', 'http://localhost:3000']);
    }

    // ------------------------------------------------------------ القبول

    public function test_a_production_token_is_accepted(): void
    {
        $token = $this->signToken('prod', self::PROD_ISSUER, 'user_prod');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('clerk_id', 'user_prod');
    }

    public function test_a_development_token_is_accepted_at_the_same_time(): void
    {
        // بيت القصيد: الاتنين شغّالين سوا بدون إعادة نشر بينهن.
        $this->signToken('prod', self::PROD_ISSUER, 'user_prod');

        $token = $this->signToken('dev', self::DEV_ISSUER, 'user_dev');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('clerk_id', 'user_dev');
    }

    public function test_an_accepted_token_creates_the_user_on_first_request(): void
    {
        // مهم لبيئة التطوير: webhook التطوير غالباً مش موجّه عالسيرفر، فأول
        // طلب هو يلي بينشئ المستخدم.
        $this->assertDatabaseCount('users', 0);

        $this->withToken($this->signToken('dev', self::DEV_ISSUER, 'user_new'))
            ->getJson('/api/user')
            ->assertOk();

        $this->assertDatabaseHas('users', ['clerk_id' => 'user_new', 'role' => 'customer']);
    }

    // ------------------------------------------------------------ الرفض

    public function test_an_unknown_issuer_is_rejected(): void
    {
        $token = $this->signToken('prod', 'https://attacker.example.com', 'user_x');

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_an_allowed_issuer_signed_with_the_other_instance_key_is_rejected(): void
    {
        // الأهم بالملف: كل مصدر لازم يتحقق بمفتاحه هو. لو المفاتيح انتبادلت،
        // توكن من instance بيمرق على instance تاني.
        $token = $this->signToken('dev', self::PROD_ISSUER, 'user_x');

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();

        $token = $this->signToken('prod', self::DEV_ISSUER, 'user_x');

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $token = $this->signToken('prod', self::PROD_ISSUER, 'user_x', expired: true);

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_token_from_an_unlisted_origin_is_rejected(): void
    {
        // فحص الـ azp بيضل شغّال متل ما هو - دومين غريب ما بيمرق حتى لو
        // التوكن موقّع صح.
        $token = $this->signToken('prod', self::PROD_ISSUER, 'user_x', claims: [
            'azp' => 'https://not-our-site.example.com',
        ]);

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_a_listed_origin_passes_the_azp_check(): void
    {
        $token = $this->signToken('prod', self::PROD_ISSUER, 'user_x', claims: [
            'azp' => 'http://localhost:3000',
        ]);

        $this->withToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_a_garbage_token_is_rejected(): void
    {
        $this->withToken('not-a-jwt')->getJson('/api/user')->assertUnauthorized();
    }

    // ------------------------------------------------------- إلغاء المؤقت

    public function test_clearing_the_dev_issuer_leaves_production_working(): void
    {
        // إلغاء دعم التطوير لاحقاً = حذف متغيّرين من .env، بلا تعديل كود.
        Config::set('clerk.dev_issuer', null);
        Config::set('clerk.dev_signer_key', '');

        $this->withToken($this->signToken('prod', self::PROD_ISSUER, 'user_prod'))
            ->getJson('/api/user')
            ->assertOk();
    }

    public function test_clearing_the_dev_issuer_rejects_development_tokens(): void
    {
        Config::set('clerk.dev_issuer', null);
        Config::set('clerk.dev_signer_key', '');

        $this->withToken($this->signToken('dev', self::DEV_ISSUER, 'user_dev'))
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_a_dev_issuer_without_its_key_rejects_instead_of_erroring(): void
    {
        // إعداد ناقص لازم يرجّع 401 نظيف مش 500.
        Config::set('clerk.dev_signer_key', '');

        $this->withToken($this->signToken('dev', self::DEV_ISSUER, 'user_dev'))
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    // ------------------------------------------------------------ أدوات

    /**
     * @param  array<string, mixed>  $claims
     */
    private function signToken(
        string $keyName,
        string $issuer,
        string $subject,
        bool $expired = false,
        array $claims = [],
    ): string {
        $config = Configuration::forAsymmetricSigner(
            signer: new Sha256,
            signingKey: InMemory::plainText($this->key($keyName, 'private')),
            verificationKey: InMemory::plainText($this->key($keyName, 'public')),
        );

        $now = CarbonImmutable::now()->toDateTimeImmutable();
        $issuedAt = $expired ? $now->modify('-2 hours') : $now->modify('-1 minute');
        $expiresAt = $expired ? $now->modify('-1 hour') : $now->modify('+1 hour');

        $builder = $config->builder()
            ->issuedBy($issuer)
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->relatedTo($subject);

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * مفاتيح ثابتة من tests/Fixtures/keys بدل توليدها بكل تست: التوليد بطيء،
     * وopenssl_pkey_new() بيفشل على أي جهاز ما عنده openssl.cnf مضبوط.
     *
     * @param  'prod'|'dev'  $instance
     * @param  'public'|'private'  $kind
     */
    private function key(string $instance, string $kind): string
    {
        return file_get_contents(base_path("tests/Fixtures/keys/{$instance}-{$kind}.pem"));
    }
}
