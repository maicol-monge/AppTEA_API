<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialista extends Model
{
    use HasFactory;

    protected $table = 'especialista';
    protected $primaryKey = 'id_especialista';
    public $timestamps = true;

    protected $fillable = [
        'id_usuario',
        'especialidad',
        'terminos_privacida',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
