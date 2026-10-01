<?php

namespace App\Models;

class ActividadModel extends BaseModel
{
    public const ESTADOS = ['En proceso', 'Presentado', 'Concluido'];

    protected $table         = 'actividades';
    protected $allowedFields = ['proyecto_id', 'nombre', 'fecha', 'estado', 'porcentaje'];
}
