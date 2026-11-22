<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutenticaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token', hash('sha256', $token))->first() : null;

        // RNF11: sessão expira após 30 minutos de inatividade
        $expirada = ! $user
            || ! $user->token_ultimo_uso
            || $user->token_ultimo_uso->lt(now()->subMinutes(30));

        if ($expirada || $user->status !== 'ativo') {
            return response()->json(['message' => 'Sessão expirada. Entre novamente.'], 401);
        }

        $user->forceFill(['token_ultimo_uso' => now()])->save();
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
