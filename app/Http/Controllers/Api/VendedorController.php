<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anuncio;
use App\Models\Lance;
use App\Models\Transacao;
use App\Services\LeilaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendedorController extends Controller
{
    public function __construct(private LeilaoService $leilao) {}

    /** RF6 (histórico de anúncios) + estatísticas da tela inicial */
    public function resumo(Request $request): JsonResponse
    {
        $this->leilao->encerrarVencidos();
        $id = $request->user()->id;

        $anuncios = Anuncio::with('categoria:id,nome')->where('user_id', $id)->latest()->get();
        $transacoes = Transacao::with(['anuncio:id,material', 'empresa:id,name', 'pagamento'])
            ->where('vendedor_id', $id)->latest()->get();

        return response()->json([
            'estatisticas' => [
                'anuncios_ativos' => $anuncios->where('status', 'aberto')->count(),
                'lances_recebidos' => Lance::whereIn('anuncio_id', $anuncios->pluck('id'))->count(),
            ],
            'anuncios' => $anuncios,
            'transacoes' => $transacoes->map(fn ($t) => [
                'id' => $t->id,
                'material' => $t->anuncio->material,
                'comprador' => $t->empresa->name,
                'valor_final' => $t->valor_final,
                'codigo_descarte' => $t->codigo_descarte,
                'status' => $t->status,
                'pagamento' => $t->pagamento?->status,
            ]),
        ]);
    }

    /** RF4 */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'material' => 'required|string|max:120',
            'categoria_id' => 'required|exists:categorias_material,id',
            'descricao' => 'nullable|string|max:1000',
            'quantidade' => 'required|numeric|min:0.01|max:99999999',
            'unidade' => 'nullable|in:kg,un',
            'lance_minimo' => 'required|numeric|min:0.01|max:99999999',
            'data_encerramento' => 'required|date|after:now',
            'foto' => 'nullable|image|max:2048',
        ]);

        $foto = $request->hasFile('foto')
            ? Storage::url($request->file('foto')->store('anuncios', 'public'))
            : null;

        $anuncio = Anuncio::create([
            'user_id' => $request->user()->id,
            'categoria_id' => $dados['categoria_id'],
            'material' => $dados['material'],
            'descricao' => $dados['descricao'] ?? null,
            'quantidade' => $dados['quantidade'],
            'unidade' => $dados['unidade'] ?? 'kg',
            'lance_minimo' => $dados['lance_minimo'],
            'data_encerramento' => $dados['data_encerramento'],
            'foto_url' => $foto,
        ]);

        return response()->json($anuncio, 201);
    }

    /** RF5 */
    public function cancelar(Request $request, Anuncio $anuncio): JsonResponse
    {
        abort_if($anuncio->user_id !== $request->user()->id, 403, 'Este anúncio não é seu.');

        if ($anuncio->status !== 'aberto') {
            return response()->json(['message' => 'Só é possível cancelar anúncios abertos.'], 422);
        }

        if ($anuncio->lances()->exists()) {
            return response()->json(['message' => 'Este anúncio já recebeu lances e não pode ser cancelado.'], 422);
        }

        $anuncio->update(['status' => 'cancelado']);

        return response()->json(['message' => 'Anúncio cancelado.']);
    }
}
