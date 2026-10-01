<?php

namespace App\Models;

class CuotaModel extends BaseModel
{
    public const ESTADOS = ['pendiente', 'facturado', 'pagado'];

    protected $table         = 'cuotas';
    protected $allowedFields = ['proyecto_id', 'orden', 'etiqueta', 'porcentaje', 'estado', 'factura', 'fecha_pago',
        'actividad_id', 'fecha_estimada', 'fecha_emision', 'fecha_vencimiento', 'detr_fecha', 'detr_ref'];
}
