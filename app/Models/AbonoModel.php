<?php

namespace App\Models;

class AbonoModel extends BaseModel
{
    protected $table         = 'abonos';
    protected $allowedFields = ['cuota_id', 'fecha', 'monto', 'referencia'];
}
