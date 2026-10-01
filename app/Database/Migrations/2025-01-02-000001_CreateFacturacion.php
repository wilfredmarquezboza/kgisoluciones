<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFacturacion extends Migration
{
    public function up()
    {
        // Un proyecto con monto definido participa en el Control de facturas.
        $this->forge->addColumn('proyectos', [
            'moneda' => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => true],
            'monto'  => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
        ]);

        // Pagos (cuotas) de cada proyecto
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'proyecto_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'orden'       => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'etiqueta'    => ['type' => 'VARCHAR', 'constraint' => 60],
            'porcentaje'  => ['type' => 'DECIMAL', 'constraint' => '7,4'],
            'estado'      => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pendiente'],
            'factura'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'fecha_pago'  => ['type' => 'DATE', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('proyecto_id');
        $this->forge->addForeignKey('proyecto_id', 'proyectos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cuotas');

        // Actividades que miden el avance de cada proyecto
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'proyecto_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'fecha'       => ['type' => 'DATE', 'null' => true],
            'estado'      => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'En proceso'],
            'porcentaje'  => ['type' => 'INT', 'constraint' => 3],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('proyecto_id');
        $this->forge->addForeignKey('proyecto_id', 'proyectos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('actividades');

        // Tasas editables desde Seguridad → Configuración (en %)
        $now = date('Y-m-d H:i:s');
        foreach (['IGV' => '18', 'DETRACCION' => '12'] as $k => $v) {
            if (! $this->db->table('configuraciones')->where('clave', $k)->countAllResults()) {
                $this->db->table('configuraciones')->insert(['clave' => $k, 'valor' => $v, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('actividades', true);
        $this->forge->dropTable('cuotas', true);
        $this->forge->dropColumn('proyectos', ['moneda', 'monto']);
        $this->db->table('configuraciones')->whereIn('clave', ['IGV', 'DETRACCION'])->delete();
    }
}
