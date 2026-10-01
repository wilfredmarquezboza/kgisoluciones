<?php

namespace App\Models;

class ClienteModel extends BaseModel
{
    protected $table         = 'clientes';
    protected $allowedFields = ['nombre'];
}
