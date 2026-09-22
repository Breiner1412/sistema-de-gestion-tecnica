<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tecnico extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'nombre',
        'telefono',
        'estado',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ordenesTrabajo()
    {
        return $this->hasMany(OrdenTrabajo::class);
    }
}
