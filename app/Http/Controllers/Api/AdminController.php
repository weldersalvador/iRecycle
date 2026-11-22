<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /** RF13 */
    public function empresas(): JsonResponse
    {
        return response()->json(
            Empresa::where('status_aprovacao', 'pendente')->latest()->get(['id', 'razao_social', 'cnpj', 'status_aprovacao'])
        );
    }

    public function aprovar(Empresa $empresa): JsonResponse
    {
        $empresa->update(['status_aprovacao' => 'aprovada']);
        $empresa->user->update(['status' => 'ativo']);
        Log::info('EMPRESA_APROVADA', ['empresa' => $empresa->id]);

        return response()->json(['message' => 'Empresa aprovada.']);
    }

    public function recusar(Empresa $empresa): JsonResponse
    {
        $empresa->update(['status_aprovacao' => 'recusada']);
        $empresa->user->update(['status' => 'bloqueado']);
        Log::info('EMPRESA_RECUSADA', ['empresa' => $empresa->id]);

        return response()->json(['message' => 'Cadastro recusado.']);
    }
}
