<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anuncio;
use App\Models\Transacao;
use App\Services\LeilaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(private LeilaoService $leilao) {}

    /** RF7 */
    public function leiloes(): JsonResponse
    {
        $this->leilao->encerrarVencidos();

        return response()->json(
            Anuncio::with('categoria:id,nome')
                ->where('status', 'aberto')
                ->orderBy('data_encerramento')
                ->get()
        );
    }

    /** RF8 */
    public function lance(Request $request, Anuncio $anuncio): JsonResponse
    {
        $dados = $request->validate(['valor' => 'required|numeric|min:0.01|max:99999999']);

        $lance = $this->leilao->darLance($request->user(), $anuncio, (float) $dados['valor']);

        return response()->json(['message' => 'Lance registrado com sucesso!', 'lance' => $lance], 201);
    }

    /** RF6 (histórico de compras e status dos pagamentos) */
    public function compras(Request $request): JsonResponse
    {
        $this->leilao->encerrarVencidos();

        $transacoes = Transacao::with(['anuncio:id,material', 'pagamento'])
            ->where('empresa_id', $request->user()->id)->latest()->get();

        return response()->json($transacoes->map(fn ($t) => [
            'id' => $t->id,
            'material' => $t->anuncio->material,
            'valor_final' => $t->valor_final,
            'status' => $t->status,
            'pagamento' => $t->pagamento?->status,
        ]));
    }

    /** RF9 (pagamento simulado) */
    public function pagar(Request $request, Transacao $transacao): JsonResponse
    {
        abort_if($transacao->empresa_id !== $request->user()->id, 403, 'Esta compra não é sua.');

        $pagamento = $transacao->pagamento;

        if ($pagamento->status === 'pago') {
            return response()->json(['message' => 'Este pagamento já foi registrado.'], 422);
        }

        $pagamento->update(['status' => 'pago', 'pago_em' => now()]);

        $this->leilao->notificar($transacao->vendedor_id, 'pagamento', 'O pagamento de uma venda sua foi registrado.');

        return response()->json(['message' => 'Pagamento registrado (simulado).']);
    }
}
