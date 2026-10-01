<?php

namespace App\Models;

class ConfiguracionModel extends BaseModel
{
    protected $table         = 'configuraciones';
    protected $allowedFields = ['clave', 'valor'];
}
