<?php

namespace App\Console\Commands;

use App\Services\LeilaoService;
use Illuminate\Console\Command;

class EncerrarLeiloes extends Command
{
    protected $signature = 'leiloes:encerrar';

    protected $description = 'Encerra os leilões com prazo vencido, gera códigos de descarte e notifica os usuários';

    public function handle(LeilaoService $leilao): int
    {
        $this->info($leilao->encerrarVencidos().' leilão(ões) processado(s).');

        return self::SUCCESS;
    }
}
