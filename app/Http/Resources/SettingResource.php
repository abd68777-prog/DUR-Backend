<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Settings',
    description: 'A single object, never an array. Every key is always present, so the frontend can read them without existence checks.',
    properties: [
        new OA\Property(
            property: 'brand',
            properties: [
                new OA\Property(property: 'site_url', type: 'string', format: 'uri', nullable: true, example: 'https://durjewels.com'),
                new OA\Property(property: 'whatsapp_number', type: 'string', example: '963999000000', description: 'Digits only - build the link as wa.me/{whatsapp_number}'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'contact',
            properties: [
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+963 999 000 000', description: 'Display text'),
                new OA\Property(property: 'phone_href', type: 'string', nullable: true, example: 'tel:+963999000000', description: 'Put straight into href'),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'address_ar', type: 'string', nullable: true),
                new OA\Property(property: 'address_en', type: 'string', nullable: true),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'social',
            description: 'WhatsApp is deliberately absent - build its link from brand.whatsapp_number.',
            properties: [
                new OA\Property(property: 'instagram_url', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'facebook_url', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'home_hero',
            properties: [
                new OA\Property(property: 'video_url', type: 'string', format: 'uri', nullable: true, description: 'Full Cloudinary URL, ready to use as a src'),
                new OA\Property(property: 'poster_url', type: 'string', format: 'uri', nullable: true),
                new OA\Property(property: 'title_line1_ar', type: 'string', nullable: true),
                new OA\Property(property: 'title_line1_en', type: 'string', nullable: true),
                new OA\Property(property: 'title_line2_ar', type: 'string', nullable: true),
                new OA\Property(property: 'title_line2_en', type: 'string', nullable: true),
                new OA\Property(property: 'description_ar', type: 'string', nullable: true),
                new OA\Property(property: 'description_en', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_label_ar', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_label_en', type: 'string', nullable: true),
                new OA\Property(property: 'primary_button_url', type: 'string', nullable: true, example: '/products'),
                new OA\Property(property: 'secondary_button_label_ar', type: 'string', nullable: true),
                new OA\Property(property: 'secondary_button_label_en', type: 'string', nullable: true),
                new OA\Property(property: 'secondary_button_url', type: 'string', nullable: true, example: '/#about'),
            ],
            type: 'object'
        ),
    ]
)]
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $defaults = config('settings.defaults');

        // منملأ الناقص من الافتراضيات حتى كل مفتاح يضل موجود بالرد، حتى لو
        // انضاف حقل جديد بعد ما انزرع الصف. الفرونت ما بيحتاج فحص وجود.
        return [
            'brand' => array_replace($defaults['brand'], (array) $this->brand),
            'contact' => array_replace($defaults['contact'], (array) $this->contact),
            'social' => array_replace($defaults['social'], (array) $this->social),
            'home_hero' => array_replace($defaults['home_hero'], (array) $this->home_hero),
        ];
    }
}
