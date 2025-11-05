<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = true;

    protected $fillable = [
        'nombres',
        'apellidos',
        'direccion',
        'telefono',
        'correo',
        'contrasena',
        'requiere_cambio_contrasena',
        'privilegio',
        'imagen',
        'estado',
    ];

    protected $hidden = ['contrasena'];
}
