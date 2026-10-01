<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKgiTables extends Migration
{
    public function up()
    {
        $ts = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        // Perfiles
        $this->forge->addField(array_merge([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->createTable('perfiles');

        // Usuarios
        $this->forge->addField(array_merge([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'perfil_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombres'   => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'password'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'activo'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'ultimo_acceso' => ['type' => 'DATETIME', 'null' => true],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('usuarios');

        // Tokens de recuperación de contraseña (se guarda el hash, nunca el token)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'usuario_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'expira_en'  => ['type' => 'DATETIME'],
            'usado_en'   => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('token_hash');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('password_resets');

        // Configuraciones
        $this->forge->addField(array_merge([
            'id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'clave' => ['type' => 'VARCHAR', 'constraint' => 80],
            'valor' => ['type' => 'TEXT', 'null' => true],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('configuraciones');

        // Clientes
        $this->forge->addField(array_merge([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 150],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->createTable('clientes');

        // Proyectos
        $this->forge->addField(array_merge([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cliente_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'departamento' => ['type' => 'VARCHAR', 'constraint' => 60],
            'nombre'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('cliente_id', 'clientes', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('proyectos');

        // Procesos
        $this->forge->addField(array_merge([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255],
            'fecha'       => ['type' => 'DATE', 'null' => true],
        ], $ts));
        $this->forge->addKey('id', true);
        $this->forge->createTable('procesos');
    }

    public function down()
    {
        foreach (['procesos', 'proyectos', 'clientes', 'configuraciones', 'password_resets', 'usuarios', 'perfiles'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
