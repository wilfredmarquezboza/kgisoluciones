<?php

namespace App\Models;

class AdjuntoModel extends BaseModel
{
    public const TIPOS = ['factura_pdf' => 'Factura (PDF)', 'factura_xml' => 'Factura (XML)', 'constancia' => 'Constancia de detracción', 'otro' => 'Otro'];

    protected $table         = 'adjuntos';
    protected $allowedFields = ['cuota_id', 'tipo', 'nombre', 'archivo', 'mime', 'tamano', 'usuario_id'];

    public static function dir(): string
    {
        return WRITEPATH . 'uploads/adjuntos/';
    }

    /** Borra del disco los archivos de estas cuotas (las filas caen por la clave foránea). */
    public function purgarDeCuotas(array $cuotaIds): void
    {
        if (! $cuotaIds) {
            return;
        }
        foreach ($this->whereIn('cuota_id', $cuotaIds)->findAll() as $a) {
            @unlink(self::dir() . $a['archivo']);
        }
        $this->whereIn('cuota_id', $cuotaIds)->delete();
    }
}
