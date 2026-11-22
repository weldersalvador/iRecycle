<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transacao;
use App\Services\LeilaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OperadorController extends Controller
{
    public function __construct(private LeilaoService $leilao) {}

    /** RF12 */
    public function confirmarEntrega(Request $request): JsonResponse
    {
        $dados = $request->validate(['codigo' => 'required|string|max:20']);

        $transacao = Transacao::with('anuncio:id,material')
            ->where('codigo_descarte', strtoupper(trim($dados['codigo'])))->first();

        if (! $transacao) {
            return response()->json(['message' => 'Código inválido. Verifique o código gerado pelo vendedor.'], 404);
        }

        if ($transacao->status === 'concluida') {
            return response()->json(['message' => 'Este código já foi utilizado.'], 422);
        }

        $operador = $request->user();

        $transacao->update([
            'status' => 'concluida',
            'entregue_em' => now(),
            'operador_id' => $operador->id,
            'ponto_descarte_id' => $operador->ponto_descarte_id,
        ]);

        $msg = "A entrega de \"{$transacao->anuncio->material}\" foi confirmada. Transação concluída.";
        $this->leilao->notificar($transacao->vendedor_id, 'entrega', $msg);
        $this->leilao->notificar($transacao->empresa_id, 'entrega', $msg);

        Log::info('ENTREGA_CONFIRMADA', ['transacao' => $transacao->id, 'operador' => $operador->id]);

        return response()->json(['message' => 'Entrega confirmada! Transação concluída.']);
    }
}
