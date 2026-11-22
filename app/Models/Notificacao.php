<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lida' => 'boolean'];
    }
}
