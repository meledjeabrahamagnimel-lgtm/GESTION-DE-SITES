<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Place l'utilisateur connecté dans son équipe de permissions (son entreprise).
 * Un Super Admin (sans entreprise) est rattaché à l'équipe plateforme (0).
 */
class DefinirEquipePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($utilisateur = $request->user()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($utilisateur->entreprise_id ?? 0);
        }

        return $next($request);
    }
}
