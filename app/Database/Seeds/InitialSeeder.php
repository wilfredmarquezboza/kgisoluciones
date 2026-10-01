<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('perfiles')->insert([
            'nombre' => 'Administrador', 'descripcion' => 'Administrador', 'es_admin' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $perfilId = $this->db->insertID();

        // Contraseña inicial: variable de entorno ADMIN_PASSWORD o "Admin123!" (cambiar tras el primer ingreso).
        $pass = env('ADMIN_PASSWORD', 'Admin123!');
        $this->db->table('usuarios')->insert([
            'perfil_id' => $perfilId,
            'nombres'   => 'Administrador',
            'email'     => env('ADMIN_EMAIL', 'admin@kgisoluciones.com'),
            'password'  => password_hash($pass, PASSWORD_DEFAULT),
            'activo'    => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $config = [
            'SMTP_HOST' => 'ssl://smtp.gmail.com', 'SMTP_PORT' => '465', 'SMTP_USER' => '',
            'SMTP_PASS' => '', 'SMTP_FROM' => 'KGI Soluciones', 'ANIO' => date('Y'),
            'RUTA_ARCHIVO' => '/var/www/kgisoluciones.com/public_html/sistema/',
            'SERVIDOR' => 'https://kgisoluciones.com/sistema/', 'AUDITORIA' => 'true',
        ];
        foreach ($config as $k => $v) {
            $this->db->table('configuraciones')->insert(['clave' => $k, 'valor' => $v, 'created_at' => $now, 'updated_at' => $now]);
        }

        $clientes = ['Edifica', 'Grupo LAR', 'GRV', 'Resemin', 'TM Gestión Inmobiliaria', 'UPLA - Universidad Peruana de los Andes'];
        foreach ($clientes as $c) {
            $this->db->table('clientes')->insert(['nombre' => $c, 'created_at' => $now, 'updated_at' => $now]);
        }

        $proyectos = [
            ['Ancash', 'Proyecto inmobiliario Condominio Club Playa Castillo de Arena'],
            ['Ancash', 'Habilitación urbana Condominio Baiona'],
            ['Ancash', 'Planeamiento integral La Gramita'],
            ['Arequipa', 'Habilitación urbana Condominio Tolosa'],
            ['Arequipa', 'Proyecto inmobiliario Home'],
            ['Cusco', null],
            ['Ica', null],
            ['Junín', 'Diagnóstico de derechos Campus Universitario'],
            ['Junín', 'Habilitación urbana Vista Hermosa'],
            ['Junín', 'Diagnóstico de derechos Trancapampa'],
        ];
        foreach ($proyectos as [$dep, $nom]) {
            $this->db->table('proyectos')->insert([
                'departamento' => $dep, 'nombre' => $nom, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
