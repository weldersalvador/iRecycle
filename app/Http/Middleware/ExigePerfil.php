<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigePerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        if (! in_array($request->user()->perfil, $perfis, true)) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        return $next($request);
    }
}
