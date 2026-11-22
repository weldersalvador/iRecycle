<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transacao extends Model
{
    protected $table = 'transacoes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valor_final' => 'float', 'entregue_em' => 'datetime'];
    }

    public function anuncio(): BelongsTo
    {
        return $this->belongsTo(Anuncio::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class);
    }
}
