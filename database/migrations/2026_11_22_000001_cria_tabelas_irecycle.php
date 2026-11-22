<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('perfil', 20)->default('vendedor');   // vendedor | empresa | operador | admin
            $t->string('telefone', 20)->nullable();
            $t->string('status', 20)->default('ativo');      // ativo | pendente | bloqueado
            $t->unsignedBigInteger('ponto_descarte_id')->nullable(); // usado pelo operador
            $t->string('api_token', 64)->nullable()->unique();
            $t->timestamp('token_ultimo_uso')->nullable();
        });

        Schema::create('empresas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('razao_social');
            $t->string('cnpj', 18)->unique();
            $t->string('status_aprovacao', 20)->default('pendente'); // pendente | aprovada | recusada
            $t->timestamps();
        });

        Schema::create('categorias_material', function (Blueprint $t) {
            $t->id();
            $t->string('nome')->unique();
            $t->boolean('ativa')->default(true);
            $t->timestamps();
        });

        Schema::create('pontos_descarte', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('endereco');
            $t->string('horario_funcionamento');
            $t->string('materiais_aceitos');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        Schema::create('anuncios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // vendedor
            $t->foreignId('categoria_id')->constrained('categorias_material');
            $t->string('material', 120);
            $t->text('descricao')->nullable();
            $t->decimal('quantidade', 10, 2);
            $t->string('unidade', 10)->default('kg');
            $t->string('foto_url')->nullable();
            $t->decimal('lance_minimo', 10, 2);
            $t->decimal('maior_lance', 10, 2)->nullable();
            $t->dateTime('data_encerramento');
            $t->string('status', 20)->default('aberto')->index(); // aberto | vendido | sem_lances | cancelado
            $t->timestamps();
        });

        Schema::create('lances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('anuncio_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // empresa
            $t->decimal('valor', 10, 2);
            $t->timestamps();
        });

        Schema::create('transacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('anuncio_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('vendedor_id')->constrained('users');
            $t->foreignId('empresa_id')->constrained('users');
            $t->decimal('valor_final', 10, 2);
            $t->string('codigo_descarte', 20)->unique();
            $t->string('status', 30)->default('aguardando_entrega'); // aguardando_entrega | concluida
            $t->unsignedBigInteger('ponto_descarte_id')->nullable();
            $t->unsignedBigInteger('operador_id')->nullable();
            $t->dateTime('entregue_em')->nullable();
            $t->timestamps();
        });

        Schema::create('pagamentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transacao_id')->unique()->constrained('transacoes')->cascadeOnDelete();
            $t->decimal('valor', 10, 2);
            $t->string('status', 20)->default('pendente'); // pendente | pago
            $t->dateTime('pago_em')->nullable();
            $t->timestamps();
        });

        Schema::create('notificacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('tipo', 30);
            $t->string('mensagem');
            $t->boolean('lida')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('transacoes');
        Schema::dropIfExists('lances');
        Schema::dropIfExists('anuncios');
        Schema::dropIfExists('pontos_descarte');
        Schema::dropIfExists('categorias_material');
        Schema::dropIfExists('empresas');

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['perfil', 'telefone', 'status', 'ponto_descarte_id', 'api_token', 'token_ultimo_uso']);
        });
    }
};
