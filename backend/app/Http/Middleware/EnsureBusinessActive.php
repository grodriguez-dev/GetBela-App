<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessActive
{
    /**
     * Bloquea el acceso a los usuarios de un negocio suspendido.
     *
     * El rol `admin` (super admin de la plataforma) nunca se bloquea porque
     * no pertenece a un negocio.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['owner', 'employee'], true)) {
            $business = $user->business;

            if ($business && !$business->is_active) {
                return response()->json([
                    'message' => 'El acceso de este negocio está suspendido. Contacta al administrador de GetBela.',
                ], 403);
            }
        }

        return $next($request);
    }
}
