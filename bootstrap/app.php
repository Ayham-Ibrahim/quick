<?php

use Illuminate\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        /*
        |--------------------------------------------------------------------------
        | 401 - Unauthenticated (Sanctum / Auth)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'غير مصرح، الرجاء تسجيل الدخول',
                ], 401);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 403 - Unauthorized
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
                ], 403);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 422 - Validation Errors
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 404 - Model Not Found (findOrFail / route model binding)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'العنصر المطلوب غير موجود',
                ], 404);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 500 - Database Errors
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'حدث خطأ في قاعدة البيانات',
                ], 500);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | 500 - Fallback (Any Unhandled Exception)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'حدث خطأ غير متوقع',
                ], 500);
            }
        });
    })
    ->create();
