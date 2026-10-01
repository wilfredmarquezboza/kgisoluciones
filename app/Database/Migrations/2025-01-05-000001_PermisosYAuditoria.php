<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PermisosYAuditoria extends Migration
{
    public function up()
    {
        // Perfil con acceso total (no se le configuran permisos y no se puede eliminar).
        $this->forge->addColumn('perfiles', [
            'es_admin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->db->table('perfiles')->where('nombre', 'Administrador')->update(['es_admin' => 1]);
        if (! $this->db->table('perfiles')->where('es_admin', 1)->countAllResults()) {
            $min = $this->db->table('perfiles')->selectMin('id', 'id')->get()->getRowArray();
            if ($min && $min['id']) {
                $this->db->table('perfiles')->where('id', $min['id'])->update(['es_admin' => 1]);
            }
        }

        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'perfil_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'permiso'   => ['type' => 'VARCHAR', 'constraint' => 40],   // "modulo.accion", p. ej. facturas.cobrar
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['perfil_id', 'permiso']);
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('permisos');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'usuario_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'usuario_nombre' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],   // copia, por si el usuario se elimina
            'accion'         => ['type' => 'VARCHAR', 'constraint' => 20],
            'entidad'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'registro_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'resumen'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'cambios'        => ['type' => 'TEXT', 'null' => true],
            'ip'             => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entidad', 'registro_id']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('auditoria');
    }

    public function down()
    {
        $this->forge->dropTable('auditoria', true);
        $this->forge->dropTable('permisos', true);
        $this->forge->dropColumn('perfiles', 'es_admin');
    }
}
