<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lance extends Model
{
    protected $table = 'lances';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valor' => 'float'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
