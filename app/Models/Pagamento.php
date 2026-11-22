<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valor' => 'float', 'pago_em' => 'datetime'];
    }

    public function transacao(): BelongsTo
    {
        return $this->belongsTo(Transacao::class);
    }
}
