<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Laravel بيحط افتراضياً `fn () => route('login')` هون، وبيستدعيها جوّا
        // Authenticate middleware لأي طلب ما بيطلب JSON صراحةً (يعني fetch بدون
        // هيدر Accept: application/json). ما في route اسمه login بهالمشروع -
        // Clerk بيتولى المصادقة - فكانت ترمي RouteNotFoundException وترجع 500
        // بدل 401، قبل ما يشتغل معالج الأخطاء أصلاً.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        // بدون شرط api/* عن قصد: ما في تسجيل دخول ويب بهالمشروع (Clerk بيتولى
        // المصادقة كلها)، فأي طلب غير مصادَق برّا api/* كان بينتهي عند Laravel
        // بـ redirect لـ route('login') المفقود => RouteNotFoundException و500
        // بدل 401.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'You must be logged in to access this resource.'], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'The requested resource was not found.'], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'An error occurred while processing the request.',
            ], $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            return response()->json(['message' => 'A server error occurred.'], 500);
        });
    })->create();
