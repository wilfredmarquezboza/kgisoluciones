<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeguimientoPagos extends Migration
{
    public function up()
    {
        // actividad_id (hito) se valida en la aplicación para que la migración funcione igual en MySQL y SQLite.
        $this->forge->addColumn('cuotas', [
            'actividad_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'fecha_estimada'    => ['type' => 'DATE', 'null' => true],
            'fecha_emision'     => ['type' => 'DATE', 'null' => true],
            'fecha_vencimiento' => ['type' => 'DATE', 'null' => true],
            'detr_fecha'        => ['type' => 'DATE', 'null' => true],
            'detr_ref'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
        ]);

        // Cobros (abonos) de cada pago, pueden ser parciales
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cuota_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'fecha'      => ['type' => 'DATE'],
            'monto'      => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'referencia' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('cuota_id');
        $this->forge->addForeignKey('cuota_id', 'cuotas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('abonos');
    }

    public function down()
    {
        $this->forge->dropTable('abonos', true);
        $this->forge->dropColumn('cuotas', ['actividad_id', 'fecha_estimada', 'fecha_emision', 'fecha_vencimiento', 'detr_fecha', 'detr_ref']);
    }
}
