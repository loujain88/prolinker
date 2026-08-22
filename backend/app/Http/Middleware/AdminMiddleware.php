<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminMiddleware
 *
 * Register alias 'admin' in:
 *   Laravel 11 → bootstrap/app.php  $middleware->alias(['admin' => AdminMiddleware::class])
 *   Laravel 10 → Kernel.php         $middlewareAliases['admin'] = AdminMiddleware::class
 *
 * Route stack: auth:sanctum → admin
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'غير مصرح — يرجى تسجيل الدخول.'], 401);
        }
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'وصول مرفوض — هذه المنطقة للمسؤولين فقط.'], 403);
        }
        if (! $user->is_active) {
            return response()->json(['message' => 'تم تعليق حساب المسؤول.'], 403);
        }

        return $next($request);
    }
}
