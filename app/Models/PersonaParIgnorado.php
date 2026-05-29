<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaParIgnorado extends Model
{
    protected $table = 'persona_pares_ignorados';

    protected $fillable = ['persona_a_id', 'persona_b_id'];

    public function personaA(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_a_id');
    }

    public function personaB(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_b_id');
    }
}
