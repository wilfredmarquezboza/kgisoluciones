<?php

namespace App\Models;

class PerfilModel extends BaseModel
{
    protected $table         = 'perfiles';
    protected $allowedFields = ['nombre', 'descripcion'];
}
