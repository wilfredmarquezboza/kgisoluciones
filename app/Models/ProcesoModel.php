<?php

namespace App\Models;

class ProcesoModel extends BaseModel
{
    protected $table         = 'procesos';
    protected $allowedFields = ['descripcion', 'fecha'];
}
