<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SettingController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    #[OA\Get(
        path: '/settings',
        summary: 'Store settings',
        description: 'Public, like the product and category read endpoints. Returns one object holding the editable content of the site - brand, contact, social links and the home page hero.',
        tags: ['Settings'],
        responses: [
            new OA\Response(response: 200, description: 'The current settings', content: new OA\JsonContent(ref: '#/components/schemas/Settings')),
        ]
    )]
    public function show(): JsonResponse
    {
        return response()->json(new SettingResource($this->settings->current()));
    }

    #[OA\Put(
        path: '/settings',
        summary: 'Update store settings',
        description: <<<'TXT'
            Admin only. The update is partial: send only the sections and fields
            that change and everything else keeps its stored value. Sending an
            explicit null clears a field.

            To upload the hero video or poster, send multipart/form-data with
            home_hero[video] and/or home_hero[poster]. The files go to Cloudinary
            and the resulting full URLs are stored in video_url and poster_url.
            Browsers cannot send multipart with PUT, so use POST with a
            _method=PUT field for that case.
            TXT,
        security: [['bearerAuth' => []]],
        tags: ['Settings'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SettingsInput')),
        responses: [
            new OA\Response(response: 200, description: 'The updated settings', content: new OA\JsonContent(ref: '#/components/schemas/Settings')),
            new OA\Response(response: 401, description: 'Not authenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $setting = $this->settings->update(
            $request->settingsData(),
            $request->heroMedia()
        );

        return response()->json(new SettingResource($setting));
    }
}
