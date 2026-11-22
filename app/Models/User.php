<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'perfil', 'telefone', 'status', 'ponto_descarte_id'])]
#[Hidden(['password', 'remember_token', 'api_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_ultimo_uso' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function empresa(): HasOne
    {
        return $this->hasOne(Empresa::class);
    }
}
