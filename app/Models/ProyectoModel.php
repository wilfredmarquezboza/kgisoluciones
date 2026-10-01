<?php

namespace App\Models;

class ProyectoModel extends BaseModel
{
    protected $table         = 'proyectos';
    protected $allowedFields = ['cliente_id', 'departamento', 'nombre', 'moneda', 'monto'];
}
