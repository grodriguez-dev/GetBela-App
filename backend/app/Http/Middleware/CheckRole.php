<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Asegurarnos de que el usuario está autenticado
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado.'
            ], 401);
        }

        // 2. Verificar si el rol del usuario está dentro de los permitidos
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'Acceso denegado. No tienes los permisos necesarios para esta acción.'
            ], 403);
        }

        return $next($request);
    }
}
