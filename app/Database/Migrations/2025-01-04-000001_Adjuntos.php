<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Adjuntos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cuota_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'tipo'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'otro'],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 150],   // nombre original, solo para mostrar
            'archivo'    => ['type' => 'VARCHAR', 'constraint' => 80],    // nombre aleatorio en writable/uploads/adjuntos
            'mime'       => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'tamano'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'usuario_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('cuota_id');
        $this->forge->addForeignKey('cuota_id', 'cuotas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('adjuntos');

        $now = date('Y-m-d H:i:s');
        foreach (['AVISOS_EMAILS' => '', 'AVISOS_DIAS_ANTES' => '3'] as $k => $v) {
            if (! $this->db->table('configuraciones')->where('clave', $k)->countAllResults()) {
                $this->db->table('configuraciones')->insert(['clave' => $k, 'valor' => $v, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('adjuntos', true);
        $this->db->table('configuraciones')->whereIn('clave', ['AVISOS_EMAILS', 'AVISOS_DIAS_ANTES', 'AVISOS_ULTIMO'])->delete();
    }
}
