<?php

namespace App\Models;

class CuotaModel extends BaseModel
{
    public const ESTADOS = ['pendiente', 'facturado', 'pagado'];

    protected $table         = 'cuotas';
    protected $allowedFields = ['proyecto_id', 'orden', 'etiqueta', 'porcentaje', 'estado', 'factura', 'fecha_pago'];
}
