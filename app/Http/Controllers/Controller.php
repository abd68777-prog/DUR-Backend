<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'DUR API',
    description: 'API لمتجر مجوهرات — إدارة المنتجات والتصنيفات، مع مصادقة عبر Clerk.'
)]
#[OA\Server(url: '/api', description: 'API base path')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'توكن الجلسة (session token) من Clerk، يُرسل كـ: Authorization: Bearer <token>'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'العنصر المطلوب غير موجود'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The name field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['name' => ['The name field is required.']],
        ),
    ]
)]
#[OA\Schema(
    schema: 'MessageResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'تم حذف العنصر بنجاح'),
    ]
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'last', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
    ]
)]
abstract class Controller
{
    //
}
