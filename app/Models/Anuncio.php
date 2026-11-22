<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Anuncio extends Model
{
    protected $table = 'anuncios';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_encerramento' => 'datetime',
            'quantidade' => 'float',
            'lance_minimo' => 'float',
            'maior_lance' => 'float',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMaterial::class, 'categoria_id');
    }

    public function lances(): HasMany
    {
        return $this->hasMany(Lance::class);
    }

    public function transacao(): HasOne
    {
        return $this->hasOne(Transacao::class);
    }
}
