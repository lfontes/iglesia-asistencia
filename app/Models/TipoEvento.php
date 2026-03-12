<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEvento extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    public function eventos()
    {
        return $this->hasMany(Evento::class);
    }
}
