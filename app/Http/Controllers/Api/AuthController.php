<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** RF1 */
    public function cadastro(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:120',
            'email' => 'required|email|max:150|unique:users,email',
            'senha' => 'required|string|min:6|max:100',
            'telefone' => 'nullable|string|max:20',
            'perfil' => 'required|in:vendedor,empresa',
            'razao_social' => 'required_if:perfil,empresa|nullable|string|max:150',
            'cnpj' => 'required_if:perfil,empresa|nullable|string|max:18|unique:empresas,cnpj',
        ]);

        $ehEmpresa = $dados['perfil'] === 'empresa';

        DB::transaction(function () use ($dados, $ehEmpresa) {
            $user = User::create([
                'name' => $dados['nome'],
                'email' => $dados['email'],
                'password' => $dados['senha'],
                'telefone' => $dados['telefone'] ?? null,
                'perfil' => $dados['perfil'],
                'status' => $ehEmpresa ? 'pendente' : 'ativo',
            ]);

            if ($ehEmpresa) {
                Empresa::create([
                    'user_id' => $user->id,
                    'razao_social' => $dados['razao_social'],
                    'cnpj' => $dados['cnpj'],
                ]);
            }
        });

        return response()->json([
            'message' => $ehEmpresa
                ? 'Cadastro enviado! Aguarde a aprovação do administrador para entrar.'
                : 'Cadastro realizado! Você já pode entrar.',
        ], 201);
    }

    /** RF3 */
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => 'required|email',
            'senha' => 'required|string',
        ]);

        $user = User::where('email', $dados['email'])->first();

        if (! $user || ! Hash::check($dados['senha'], $user->password)) {
            return response()->json(['message' => 'E-mail ou senha incorretos.'], 422);
        }

        if ($user->status === 'pendente') {
            return response()->json(['message' => 'Seu cadastro ainda aguarda aprovação do administrador.'], 403);
        }

        if ($user->status !== 'ativo') {
            return response()->json(['message' => 'Este acesso está bloqueado.'], 403);
        }

        $token = Str::random(60);
        $user->forceFill([
            'api_token' => hash('sha256', $token),
            'token_ultimo_uso' => now(),
        ])->save();

        Log::info('LOGIN', ['user' => $user->id]);

        return response()->json(['token' => $token, 'usuario' => $this->resumo($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->resumo($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->forceFill(['api_token' => null, 'token_ultimo_uso' => null])->save();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    private function resumo(User $u): array
    {
        return ['id' => $u->id, 'nome' => $u->name, 'email' => $u->email, 'perfil' => $u->perfil];
    }
}
