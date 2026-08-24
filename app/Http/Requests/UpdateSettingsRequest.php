<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SettingsInput',
    description: 'Partial update - send only the sections and fields that change; anything omitted keeps its stored value. Sending an explicit null clears a field.',
    properties: [
        new OA\Property(
            property: 'brand',
            properties: [
                new OA\Property(property: 'site_url', type: 'string', format: 'uri', nullable: true, example: 'https://durjewels.com'),
                new OA\Property(property: 'whatsapp_number', type: 'string', example: '963999000000', description: 'Digits only - no plus sign, no spaces. Required whenever the brand section is sent.'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'contact',
            properties: [
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+963 999 000 000', description: 'Display text, formatted for humans'),
                new OA\Property(property: 'phone_href', type: 'string', nullable: true, example: 'tel:+963999000000', description: 'Goes straight into href - kept separate because the display format is not a valid tel: value'),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'address_ar', type: 'string', nullable: true, example: 'دمشق، سوريا'),
                new OA\Property(property: 'address_en', type: 'string', nullable: true, example: 'Damascus, Syria'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'social',
            description: 'No WhatsApp field here on purpose - that link is built from brand.whatsapp_number.',
            properties: [
                new OA\Property(property: 'instagram_url', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'facebook_url', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'home_hero',
            properties: [
                new OA\Property(property: 'video_url', type: 'string', format: 'uri', nullable: true, description: 'Set it directly, or upload a file as home_hero[video] instead'),
                new OA\Property(property: 'poster_url', type: 'string', format: 'uri', nullable: true, description: 'Set it directly, or upload a file as home_hero[poster] instead'),
                new OA\Property(property: 'video', type: 'string', format: 'binary', description: 'Video file to upload; the resulting Cloudinary URL is stored in video_url'),
                new OA\Property(property: 'poster', type: 'string', format: 'binary', description: 'Image file to upload; the resulting Cloudinary URL is stored in poster_url'),
                new OA\Property(property: 'title_line1_ar', type: 'string', nullable: true),
                new OA\Property(property: 'title_line1_en', type: 'string', nullable: true),
                new OA\Property(property: 'title_line2_ar', type: 'string', nullable: true),
                new OA\Property(property: 'title_line2_en', type: 'string', nullable: true),
                new OA\Property(property: 'description_ar', type: 'string', nullable: true),
                new OA\Property(property: 'description_en', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_label_ar', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_label_en', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_url', type: 'string', nullable: true, example: '/products', description: 'A relative path or a full URL - not validated as a URL'),
                new OA\Property(property: 'secondary_button_label_ar', type: 'string', nullable: true),
                new OA\Property(property: 'secondary_button_label_en', type: 'string', nullable: true),
                new OA\Property(property: 'secondary_button_url', type: 'string', nullable: true, example: '/#about'),
            ],
            type: 'object'
        ),
    ]
)]
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الحماية مسؤولية الـ middleware بالـ route
    }

    public function rules(): array
    {
        return [
            'brand' => ['sometimes', 'array'],
            'brand.site_url' => ['nullable', 'url'],
            // required_with مش required: التحديث جزئي، فمنفرضه بس لما ينبعت
            // قسم brand - هيك ما بيصير الرقم فاضي أبداً وبنفس الوقت تعديل
            // قسم تاني ما بيضطر يعيد إرساله.
            'brand.whatsapp_number' => ['required_with:brand', 'regex:/^[0-9]+$/'],

            'contact' => ['sometimes', 'array'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.phone_href' => ['nullable', 'string', 'max:60'],
            'contact.email' => ['nullable', 'email'],
            'contact.address_ar' => ['nullable', 'string', 'max:255'],
            'contact.address_en' => ['nullable', 'string', 'max:255'],

            'social' => ['sometimes', 'array'],
            'social.instagram_url' => ['nullable', 'url'],
            'social.facebook_url' => ['nullable', 'url'],

            'home_hero' => ['sometimes', 'array'],
            'home_hero.video_url' => ['nullable', 'url'],
            'home_hero.poster_url' => ['nullable', 'url'],
            'home_hero.video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.config('settings.media.max_video_kb')],
            'home_hero.poster' => ['nullable', 'image', 'max:'.config('settings.media.max_poster_kb')],
            'home_hero.title_line1_ar' => ['nullable', 'string', 'max:255'],
            'home_hero.title_line1_en' => ['nullable', 'string', 'max:255'],
            'home_hero.title_line2_ar' => ['nullable', 'string', 'max:255'],
            'home_hero.title_line2_en' => ['nullable', 'string', 'max:255'],
            'home_hero.description_ar' => ['nullable', 'string', 'max:1000'],
            'home_hero.description_en' => ['nullable', 'string', 'max:1000'],
            'home_hero.primary_button_label_ar' => ['nullable', 'string', 'max:100'],
            'home_hero.primary_button_label_en' => ['nullable', 'string', 'max:100'],
            // مش url: بتقبل مسار داخلي زي /products متل ما بتقبل رابط كامل.
            'home_hero.primary_button_url' => ['nullable', 'string', 'max:255'],
            'home_hero.secondary_button_label_ar' => ['nullable', 'string', 'max:100'],
            'home_hero.secondary_button_label_en' => ['nullable', 'string', 'max:100'],
            'home_hero.secondary_button_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand.whatsapp_number.regex' => 'The WhatsApp number must be digits only, with no plus sign or spaces.',
            'brand.whatsapp_number.required_with' => 'The WhatsApp number is required whenever the brand section is updated.',
        ];
    }

    /**
     * ملفات الوسائط المرفوعة، مفصولة عن حقول النص.
     *
     * @return array<string, \Illuminate\Http\UploadedFile>
     */
    public function heroMedia(): array
    {
        return array_filter([
            'video' => $this->file('home_hero.video'),
            'poster' => $this->file('home_hero.poster'),
        ]);
    }

    /**
     * الحقول يلي بتنخزن فعلاً - بدون الملفات.
     *
     * validated() بترجّع كائنات UploadedFile مع الباقي، ولو مرقت للدمج
     * بتنخزن حرفياً جوّا عمود JSON. مكانها heroMedia() وبتتحوّل لروابط.
     */
    public function settingsData(): array
    {
        $data = $this->validated();

        unset($data['home_hero']['video'], $data['home_hero']['poster']);

        return $data;
    }
}
