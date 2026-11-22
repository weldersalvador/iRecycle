<?php

namespace App\Services;

use App\Models\Anuncio;
use App\Models\Lance;
use App\Models\Notificacao;
use App\Models\Pagamento;
use App\Models\Transacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeilaoService
{
    /** RF8 + RNF12: lance dentro de transação, com lock no anúncio. */
    public function darLance(User $empresa, Anuncio $anuncio, float $valor): Lance
    {
        return DB::transaction(function () use ($empresa, $anuncio, $valor) {
            $a = Anuncio::lockForUpdate()->findOrFail($anuncio->id);

            if ($a->status !== 'aberto' || $a->data_encerramento->isPast()) {
                throw ValidationException::withMessages(['valor' => 'Este leilão já foi encerrado.']);
            }

            if ($a->maior_lance !== null && $valor <= $a->maior_lance) {
                throw ValidationException::withMessages([
                    'valor' => 'O lance deve superar o maior lance atual (R$ '.number_format($a->maior_lance, 2, ',', '.').').',
                ]);
            }

            if ($valor < $a->lance_minimo) {
                throw ValidationException::withMessages([
                    'valor' => 'O lance deve ser de pelo menos R$ '.number_format($a->lance_minimo, 2, ',', '.').'.',
                ]);
            }

            $lance = Lance::create(['anuncio_id' => $a->id, 'user_id' => $empresa->id, 'valor' => $valor]);
            $a->update(['maior_lance' => $valor]);

            Log::info('LANCE', ['anuncio' => $a->id, 'empresa' => $empresa->id, 'valor' => $valor]);

            return $lance;
        });
    }

    /** Encerra todos os leilões com prazo vencido. Retorna quantos foram processados. */
    public function encerrarVencidos(): int
    {
        $ids = Anuncio::where('status', 'aberto')->where('data_encerramento', '<=', now())->pluck('id');

        foreach ($ids as $id) {
            $this->encerrar($id);
        }

        return $ids->count();
    }

    /** RF10 + RF11: fecha o leilão, gera código de descarte, registra pagamento pendente e notifica. */
    public function encerrar(int $anuncioId): void
    {
        DB::transaction(function () use ($anuncioId) {
            $a = Anuncio::lockForUpdate()->find($anuncioId);

            if (! $a || $a->status !== 'aberto' || $a->data_encerramento->isFuture()) {
                return;
            }

            $melhor = $a->lances()->orderByDesc('valor')->orderBy('id')->first();

            if (! $melhor) {
                $a->update(['status' => 'sem_lances']);
                $this->notificar($a->user_id, 'leilao', "Seu anúncio \"{$a->material}\" terminou sem lances.");

                return;
            }

            $transacao = Transacao::create([
                'anuncio_id' => $a->id,
                'vendedor_id' => $a->user_id,
                'empresa_id' => $melhor->user_id,
                'valor_final' => $melhor->valor,
                'codigo_descarte' => $this->gerarCodigo(),
            ]);

            Pagamento::create(['transacao_id' => $transacao->id, 'valor' => $melhor->valor]);
            $a->update(['status' => 'vendido']);

            $valor = number_format($melhor->valor, 2, ',', '.');
            $this->notificar($a->user_id, 'leilao', "\"{$a->material}\" foi vendido por R$ {$valor}. Código de descarte: {$transacao->codigo_descarte}.");
            $this->notificar($melhor->user_id, 'leilao', "Você venceu o leilão de \"{$a->material}\" por R$ {$valor}.");

            Log::info('CODIGO_GERADO', ['transacao' => $transacao->id, 'anuncio' => $a->id]);
        });
    }

    public function notificar(int $userId, string $tipo, string $mensagem): void
    {
        Notificacao::create(['user_id' => $userId, 'tipo' => $tipo, 'mensagem' => $mensagem]);
    }

    private function gerarCodigo(): string
    {
        do {
            $codigo = 'REC-'.strtoupper(Str::random(6));
        } while (Transacao::where('codigo_descarte', $codigo)->exists());

        return $codigo;
    }
}
