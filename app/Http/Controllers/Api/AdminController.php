<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anuncio;
use App\Models\CategoriaMaterial;
use App\Models\Empresa;
use App\Models\PontoDescarte;
use App\Models\User;
use App\Services\LeilaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private LeilaoService $leilao) {}

    // ---------- RF13: aprovar empresas ----------
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

    // ---------- RF14: categorias de materiais ----------
    public function categorias(): JsonResponse
    {
        return response()->json(CategoriaMaterial::orderBy('nome')->get(['id', 'nome', 'ativa']));
    }

    public function salvarCategoria(Request $request): JsonResponse
    {
        $dados = $request->validate(['nome' => 'required|string|max:60|unique:categorias_material,nome']);

        return response()->json(CategoriaMaterial::create($dados), 201);
    }

    public function atualizarCategoria(Request $request, CategoriaMaterial $categoria): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('categorias_material', 'nome')->ignore($categoria->id)],
            'ativa' => 'sometimes|boolean',
        ]);

        $categoria->update($dados);

        return response()->json($categoria);
    }

    // ---------- RF17: pontos de descarte ----------
    public function pontos(): JsonResponse
    {
        return response()->json(PontoDescarte::orderBy('nome')->get());
    }

    public function salvarPonto(Request $request): JsonResponse
    {
        return response()->json(PontoDescarte::create($request->validate($this->regrasPonto(false))), 201);
    }

    public function atualizarPonto(Request $request, PontoDescarte $ponto): JsonResponse
    {
        $dados = $request->validate($this->regrasPonto(true) + ['ativo' => 'sometimes|boolean']);
        $ponto->update($dados);

        return response()->json($ponto);
    }

    private function regrasPonto(bool $parcial): array
    {
        $prefixo = $parcial ? 'sometimes|' : '';

        return [
            'nome' => $prefixo.'required|string|max:120',
            'endereco' => $prefixo.'required|string|max:200',
            'horario_funcionamento' => $prefixo.'required|string|max:100',
            'materiais_aceitos' => $prefixo.'required|string|max:200',
        ];
    }

    // ---------- RF15: operadores ----------
    public function operadores(): JsonResponse
    {
        $pontos = PontoDescarte::pluck('nome', 'id');

        return response()->json(
            User::where('perfil', 'operador')->orderBy('name')->get()->map(fn ($u) => [
                'id' => $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'ponto' => $pontos[$u->ponto_descarte_id] ?? '—',
                'status' => $u->status,
            ])
        );
    }

    public function salvarOperador(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:120',
            'email' => 'required|email|max:150|unique:users,email',
            'senha' => 'required|string|min:6|max:100',
            'telefone' => 'nullable|string|max:20',
            'ponto_descarte_id' => 'required|exists:pontos_descarte,id',
        ]);

        $operador = User::create([
            'name' => $dados['nome'],
            'email' => $dados['email'],
            'password' => $dados['senha'],
            'telefone' => $dados['telefone'] ?? null,
            'perfil' => 'operador',
            'status' => 'ativo',
            'ponto_descarte_id' => $dados['ponto_descarte_id'],
        ]);
        Log::info('OPERADOR_CADASTRADO', ['operador' => $operador->id]);

        return response()->json(['message' => 'Operador cadastrado.'], 201);
    }

    public function atualizarOperador(Request $request, User $operador): JsonResponse
    {
        abort_if($operador->perfil !== 'operador', 404);

        $dados = $request->validate([
            'ponto_descarte_id' => 'sometimes|required|exists:pontos_descarte,id',
            'status' => 'sometimes|in:ativo,bloqueado',
        ]);

        if (($dados['status'] ?? null) === 'bloqueado') {
            $dados['api_token'] = null;
        }

        $operador->forceFill($dados)->save();

        return response()->json(['message' => 'Operador atualizado.']);
    }

    // ---------- RF16: moderação ----------
    public function anuncios(): JsonResponse
    {
        return response()->json(
            Anuncio::with('vendedor:id,name')->latest()->limit(100)->get()->map(fn ($a) => [
                'id' => $a->id,
                'material' => $a->material,
                'vendedor' => $a->vendedor->name,
                'status' => $a->status,
                'maior_lance' => $a->maior_lance,
                'data_encerramento' => $a->data_encerramento,
            ])
        );
    }

    public function removerAnuncio(Anuncio $anuncio): JsonResponse
    {
        if ($anuncio->status !== 'aberto') {
            return response()->json(['message' => 'Só é possível remover anúncios abertos.'], 422);
        }

        $anuncio->update(['status' => 'cancelado']);

        $this->leilao->notificar($anuncio->user_id, 'moderacao', "Seu anúncio \"{$anuncio->material}\" foi removido pela moderação.");
        foreach ($anuncio->lances()->distinct()->pluck('user_id') as $empresaId) {
            $this->leilao->notificar($empresaId, 'moderacao', "O leilão de \"{$anuncio->material}\" foi cancelado pela moderação.");
        }
        Log::info('ANUNCIO_REMOVIDO', ['anuncio' => $anuncio->id]);

        return response()->json(['message' => 'Anúncio removido.']);
    }

    public function usuarios(): JsonResponse
    {
        return response()->json(
            User::where('perfil', '!=', 'admin')->orderBy('name')->get(['id', 'name', 'email', 'perfil', 'status'])
                ->map(fn ($u) => ['id' => $u->id, 'nome' => $u->name, 'email' => $u->email, 'perfil' => $u->perfil, 'status' => $u->status])
        );
    }

    public function bloquear(User $usuario): JsonResponse
    {
        abort_if($usuario->perfil === 'admin', 403, 'Não é possível bloquear um administrador.');

        $usuario->forceFill(['status' => 'bloqueado', 'api_token' => null])->save();
        Log::info('USUARIO_BLOQUEADO', ['usuario' => $usuario->id]);

        return response()->json(['message' => 'Usuário bloqueado.']);
    }

    public function desbloquear(User $usuario): JsonResponse
    {
        if ($usuario->perfil === 'empresa' && $usuario->empresa?->status_aprovacao !== 'aprovada') {
            return response()->json(['message' => 'Esta empresa ainda não foi aprovada. Aprove-a na aba de empresas.'], 422);
        }

        $usuario->update(['status' => 'ativo']);
        Log::info('USUARIO_DESBLOQUEADO', ['usuario' => $usuario->id]);

        return response()->json(['message' => 'Usuário desbloqueado.']);
    }
}
