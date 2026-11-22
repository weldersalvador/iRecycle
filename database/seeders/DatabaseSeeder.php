<?php

namespace Database\Seeders;

use App\Models\Anuncio;
use App\Models\CategoriaMaterial;
use App\Models\Empresa;
use App\Models\PontoDescarte;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Plástico', 'Metal', 'Papel', 'Vidro', 'Eletrônicos'] as $nome) {
            CategoriaMaterial::firstOrCreate(['nome' => $nome]);
        }

        $ponto1 = PontoDescarte::firstOrCreate(['nome' => 'EcoPonto Centro'], [
            'endereco' => 'Rua Halfeld, 100', 'horario_funcionamento' => '08h - 18h',
            'materiais_aceitos' => 'Plástico, Metal, Papel',
        ]);
        PontoDescarte::firstOrCreate(['nome' => 'Recicla JF - Zona Sul'], [
            'endereco' => 'Av. Rio Branco, 500', 'horario_funcionamento' => '08h - 17h',
            'materiais_aceitos' => 'Vidro, Eletrônicos',
        ]);

        // Contas de teste (senha: 123456; admin: admin123)
        User::firstOrCreate(['email' => 'admin@irecycle.com'], [
            'name' => 'Administrador', 'password' => 'admin123', 'perfil' => 'admin',
        ]);
        User::firstOrCreate(['email' => 'operador@irecycle.com'], [
            'name' => 'Operador EcoPonto', 'password' => '123456', 'perfil' => 'operador',
            'ponto_descarte_id' => $ponto1->id,
        ]);
        $vendedor = User::firstOrCreate(['email' => 'vendedor@irecycle.com'], [
            'name' => 'Vendedor Teste', 'password' => '123456', 'perfil' => 'vendedor',
        ]);

        $empresa = User::firstOrCreate(['email' => 'empresa@irecycle.com'], [
            'name' => 'Reciclagem Silva LTDA', 'password' => '123456', 'perfil' => 'empresa',
        ]);
        Empresa::firstOrCreate(['user_id' => $empresa->id], [
            'razao_social' => 'Reciclagem Silva LTDA', 'cnpj' => '12.345.678/0001-90', 'status_aprovacao' => 'aprovada',
        ]);

        // Empresa pendente, para testar a aprovação pelo admin
        $pendente = User::firstOrCreate(['email' => 'pendente@irecycle.com'], [
            'name' => 'EcoMetal S.A.', 'password' => '123456', 'perfil' => 'empresa', 'status' => 'pendente',
        ]);
        Empresa::firstOrCreate(['user_id' => $pendente->id], [
            'razao_social' => 'EcoMetal S.A.', 'cnpj' => '98.765.432/0001-10',
        ]);

        if (Anuncio::count() === 0) {
            $cat = fn ($n) => CategoriaMaterial::where('nome', $n)->value('id');

            Anuncio::create([
                'user_id' => $vendedor->id, 'categoria_id' => $cat('Plástico'), 'material' => 'Garrafas PET',
                'descricao' => 'Garrafas limpas, sem tampa.', 'quantidade' => 30, 'lance_minimo' => 40,
                'data_encerramento' => now()->addDays(2),
            ]);
            Anuncio::create([
                'user_id' => $vendedor->id, 'categoria_id' => $cat('Papel'), 'material' => 'Papelão',
                'descricao' => 'Caixas desmontadas.', 'quantidade' => 50, 'lance_minimo' => 60,
                'data_encerramento' => now()->addDay(),
            ]);
            // Fecha em 3 minutos: serve para demonstrar o encerramento e o código de descarte
            Anuncio::create([
                'user_id' => $vendedor->id, 'categoria_id' => $cat('Metal'), 'material' => 'Latinhas de Alumínio (DEMO)',
                'descricao' => 'Leilão de demonstração.', 'quantidade' => 10, 'lance_minimo' => 20,
                'data_encerramento' => now()->addMinutes(3),
            ]);
        }
    }
}
