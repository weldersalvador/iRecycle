<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaMaterial;
use App\Models\Notificacao;
use App\Models\PontoDescarte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicoController extends Controller
{
    /** RF2 */
    public function pontos(): JsonResponse
    {
        return response()->json(PontoDescarte::where('ativo', true)->orderBy('nome')->get());
    }

    public function categorias(): JsonResponse
    {
        return response()->json(CategoriaMaterial::where('ativa', true)->orderBy('nome')->get(['id', 'nome']));
    }

    /** RF11 (notificações internas) */
    public function notificacoes(Request $request): JsonResponse
    {
        return response()->json(
            Notificacao::where('user_id', $request->user()->id)->latest()->limit(50)->get()
        );
    }

    public function marcarLidas(Request $request): JsonResponse
    {
        Notificacao::where('user_id', $request->user()->id)->update(['lida' => true]);

        return response()->json(['message' => 'ok']);
    }
}
