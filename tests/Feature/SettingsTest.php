<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------- المعايير

    public function test_settings_are_public(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonStructure(['brand', 'contact', 'social', 'home_hero']);
    }

    public function test_guests_cannot_update_settings(): void
    {
        $this->putJson('/api/settings', ['contact' => ['phone' => 'hacked']])
            ->assertStatus(401);

        $this->assertNotSame('hacked', Setting::current()->contact['phone']);
    }

    public function test_managers_cannot_update_settings(): void
    {
        // المواصفة: admin فقط.
        $this->actingAs(User::factory()->manager()->create(), 'clerk')
            ->putJson('/api/settings', ['contact' => ['phone' => '123']])
            ->assertStatus(403);
    }

    public function test_an_admin_update_is_visible_on_the_next_read(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', [
                'home_hero' => ['video_url' => 'https://res.cloudinary.com/demo/video/upload/v1/hero.mp4'],
            ])
            ->assertOk();

        // طلب مستقل تماماً - ما في كاش قديم بينحط بالنص.
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('home_hero.video_url', 'https://res.cloudinary.com/demo/video/upload/v1/hero.mp4');
    }

    // ------------------------------------------------------- الشكل والبذرة

    public function test_the_response_is_one_object_not_a_list(): void
    {
        $body = $this->getJson('/api/settings')->assertOk()->json();

        $this->assertArrayHasKey('brand', $body);
        $this->assertArrayNotHasKey(0, $body, 'the payload must not be a list');
    }

    public function test_it_ships_seeded_with_usable_values(): void
    {
        // GET لازم يشتغل من أول لحظة بدون ما الإدارة تعبّي شي.
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('brand.whatsapp_number', '963999000000')
            ->assertJsonPath('home_hero.primary_button_url', '/products')
            ->assertJsonPath('home_hero.title_line1_ar', 'أجواء فاخرة');
    }

    public function test_social_carries_no_whatsapp_field(): void
    {
        // رابط الواتساب بينبنى من brand.whatsapp_number - حقل مكرر بينتج تضارب.
        $social = $this->getJson('/api/settings')->assertOk()->json('social');

        $this->assertArrayNotHasKey('whatsapp_url', $social);
        $this->assertArrayNotHasKey('whatsapp', $social);
    }

    public function test_a_newly_added_field_still_appears_after_an_old_row_was_seeded(): void
    {
        // صف مخزّن ناقص مفاتيح - الرد لازم يكمّلها من الافتراضيات.
        $setting = Setting::current();
        $setting->contact = ['phone' => '+963 111'];
        $setting->save();

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('contact.phone', '+963 111')
            ->assertJsonPath('contact.address_en', 'Damascus, Syria');
    }

    // --------------------------------------------------------- التحديث الجزئي

    public function test_updating_one_section_leaves_the_others_untouched(): void
    {
        $before = $this->getJson('/api/settings')->json('contact');

        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['social' => ['facebook_url' => 'https://facebook.com/new']])
            ->assertOk();

        $this->getJson('/api/settings')
            ->assertJsonPath('contact', $before)
            ->assertJsonPath('social.facebook_url', 'https://facebook.com/new');
    }

    public function test_updating_one_field_leaves_its_siblings_untouched(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['home_hero' => ['title_line1_en' => 'New Title']])
            ->assertOk();

        $this->getJson('/api/settings')
            ->assertJsonPath('home_hero.title_line1_en', 'New Title')
            ->assertJsonPath('home_hero.primary_button_url', '/products');
    }

    public function test_an_explicit_null_clears_a_field(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['contact' => ['email' => null]])
            ->assertOk()
            ->assertJsonPath('contact.email', null);
    }

    // ------------------------------------------------------------- التحقق

    public function test_the_whatsapp_number_must_be_digits_only(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['+963999000000', '963 999 000', '0963-999'] as $bad) {
            $this->actingAs($admin, 'clerk')
                ->putJson('/api/settings', ['brand' => ['whatsapp_number' => $bad]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['brand.whatsapp_number']);
        }
    }

    public function test_the_whatsapp_number_is_required_when_the_brand_section_is_sent(): void
    {
        // ما منسمح للرقم يفضى، بس تعديل قسم تاني ما بيضطر يعيد إرساله.
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['brand' => ['site_url' => 'https://durjewels.com']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand.whatsapp_number']);
    }

    public function test_other_sections_can_be_updated_without_the_brand_section(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['contact' => ['phone' => '+963 111 222']])
            ->assertOk()
            ->assertJsonPath('contact.phone', '+963 111 222');
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['contact' => ['email' => 'not-an-email']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact.email']);
    }

    public function test_social_links_must_be_full_urls(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', ['social' => ['instagram_url' => 'instagram.com/dur']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['social.instagram_url']);
    }

    public function test_button_urls_accept_relative_paths(): void
    {
        // /products و/#about مسارات صالحة هون، فقاعدة url الصارمة ما بتنطبق.
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', [
                'home_hero' => [
                    'primary_button_url' => '/products',
                    'secondary_button_url' => '/#about',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('home_hero.primary_button_url', '/products');
    }

    public function test_button_urls_also_accept_absolute_ones(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->putJson('/api/settings', [
                'home_hero' => ['primary_button_url' => 'https://durjewels.com/sale'],
            ])
            ->assertOk()
            ->assertJsonPath('home_hero.primary_button_url', 'https://durjewels.com/sale');
    }

    // ------------------------------------------------------------ الوسائط

    public function test_uploading_a_hero_poster_returns_a_full_url(): void
    {
        Storage::fake('cloudinary');

        $response = $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->post('/api/settings', [
                '_method' => 'PUT',
                'home_hero' => ['poster' => UploadedFile::fake()->image('poster.jpg')],
            ])
            ->assertOk();

        $url = $response->json('home_hero.poster_url');

        $this->assertNotNull($url);
        $this->assertStringStartsWith('http', $url, 'must be a ready-to-use URL, not a relative path');
    }

    public function test_uploading_a_hero_video_returns_a_video_url(): void
    {
        Storage::fake('cloudinary');

        $response = $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->post('/api/settings', [
                '_method' => 'PUT',
                'home_hero' => ['video' => UploadedFile::fake()->create('hero.mp4', 500, 'video/mp4')],
            ])
            ->assertOk();

        $url = $response->json('home_hero.video_url');

        $this->assertNotNull($url);
        // لازم يكون مسار فيديو عند Cloudinary مش صورة.
        $this->assertStringContainsString('/video/', $url);
    }

    public function test_the_uploaded_file_itself_is_never_stored_in_the_json(): void
    {
        Storage::fake('cloudinary');

        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->post('/api/settings', [
                '_method' => 'PUT',
                'home_hero' => ['poster' => UploadedFile::fake()->image('poster.jpg')],
            ])
            ->assertOk();

        $hero = Setting::current()->home_hero;

        $this->assertArrayNotHasKey('poster', $hero);
        $this->assertArrayNotHasKey('video', $hero);
    }

    public function test_uploading_media_keeps_the_other_hero_fields(): void
    {
        Storage::fake('cloudinary');

        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->post('/api/settings', [
                '_method' => 'PUT',
                'home_hero' => ['poster' => UploadedFile::fake()->image('poster.jpg')],
            ])
            ->assertOk()
            ->assertJsonPath('home_hero.title_line1_ar', 'أجواء فاخرة');
    }

    public function test_a_non_video_file_is_rejected_for_the_video_field(): void
    {
        Storage::fake('cloudinary');

        $this->actingAs(User::factory()->admin()->create(), 'clerk')
            ->post('/api/settings', [
                '_method' => 'PUT',
                'home_hero' => ['video' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['home_hero.video']);
    }
}
