<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'clerk_id', type: 'string', example: 'user_2abc123'),
        new OA\Property(property: 'name', type: 'string', example: 'محمد أحمد'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'manager', 'customer']),
    ]
)]
class AuthController extends Controller
{
    #[OA\Get(
        path: '/user',
        summary: 'بيانات المستخدم الحالي',
        description: 'بترجع بيانات المستخدم المسجّل دخوله حالياً (من التوكن).',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'بيانات المستخدم', content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, description: 'مش مسجّل دخول', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
