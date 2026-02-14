<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    //
      protected $fillable = [
        'nombre',
        'fecha',
    ];

    public function personas()
    {
        return $this->belongsToMany(Persona::class);
    }
}
