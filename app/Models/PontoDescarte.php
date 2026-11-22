<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class PontoDescarte extends Model
{
    protected $table = 'pontos_descarte';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
