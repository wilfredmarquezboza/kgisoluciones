<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Diagnostico extends BaseCommand
{
    protected $group       = 'KGI';
    protected $name        = 'kgi:diagnostico';
    protected $description = 'Verifica el entorno (PHP, .env, base de datos, migraciones, permisos de carpetas, archivos estáticos).';

    public function run(array $params)
    {
        $fallos = 0;
        $chk    = function (string $titulo, bool $ok, string $ayuda = '') use (&$fallos) {
            $ok ? CLI::write('  [OK]    ' . $titulo, 'green') : CLI::write('  [FALLA] ' . $titulo . ($ayuda ? "\n          → $ayuda" : ''), 'red');
            $fallos += $ok ? 0 : 1;

            return $ok;
        };

        CLI::write('Entorno', 'yellow');
        $chk('PHP ' . PHP_VERSION . ' (se requiere 8.1 o superior)', version_compare(PHP_VERSION, '8.1.0', '>='), 'Actualiza PHP.');
        foreach (['intl', 'mbstring', 'json'] as $ext) {
            $chk("Extensión $ext", extension_loaded($ext), "Instala/activa php-$ext.");
        }
        $chk('Extensión de base de datos (mysqli o sqlite3)', extension_loaded('mysqli') || extension_loaded('sqlite3'), 'Instala php-mysql.');
        $chk('Archivo .env presente', is_file(ROOTPATH . '.env'), 'Copia el archivo "env" a ".env" y ajústalo.');
        CLI::write('  Modo: ' . ENVIRONMENT . ' | baseURL: ' . config('App')->baseURL);
        foreach (['', 'cache/', 'logs/', 'session/', 'uploads/'] as $d) {
            $ruta = WRITEPATH . $d;
            if ($d === 'uploads/' && ! is_dir($ruta)) {
                @mkdir($ruta, 0750, true);
            }
            $chk('Carpeta escribible: writable/' . $d, is_dir($ruta) && is_writable($ruta), "chown -R www-data:www-data writable && chmod -R u+rwX writable");
        }

        CLI::newLine();
        CLI::write('Archivos estáticos', 'yellow');
        foreach (['css/app.css', 'js/app.js', 'lib/jquery.min.js', 'lib/bulma.min.css', 'lib/fa/css/all.min.css', 'lib/fa/webfonts/fa-solid-900.woff2'] as $f) {
            $chk("public/assets/$f", is_file(FCPATH . "assets/$f"), 'Falta el archivo: haz git pull / vuelve a subir la carpeta public/.');
        }

        CLI::newLine();
        CLI::write('Base de datos', 'yellow');
        try {
            $db = db_connect();
            $db->initialize();
            $chk('Conexión (' . $db->getDatabase() . ')', true);
        } catch (\Throwable $e) {
            $chk('Conexión', false, 'Revisa database.default.* en .env: ' . $e->getMessage());
            CLI::newLine();
            CLI::error("Se encontraron $fallos problema(s).");

            return 1;
        }

        try {
            $runner = service('migrations');
            $hechas = array_column($runner->getHistory(), 'version');
            $faltan = array_filter($runner->findMigrations(), static fn ($m) => ! in_array($m->version, $hechas, true));
            $chk('Migraciones al día', ! $faltan, count($faltan) . ' pendiente(s): ejecuta "php spark migrate".');
        } catch (\Throwable $e) {
            $chk('Migraciones', false, 'La base no está instalada: ejecuta "php spark migrate". (' . $e->getMessage() . ')');
        }

        foreach (['usuarios', 'perfiles', 'permisos', 'auditoria', 'proyectos', 'clientes', 'cuotas', 'abonos', 'adjuntos', 'actividades', 'configuraciones'] as $t) {
            $chk("Tabla $t", $db->tableExists($t), 'Ejecuta "php spark migrate".');
        }
        if ($db->tableExists('perfiles') && $db->fieldExists('es_admin', 'perfiles')) {
            $n = $db->table('perfiles')->where('es_admin', 1)->countAllResults();
            $chk('Existe un perfil administrador (es_admin)', $n > 0, 'Marca tu perfil de administración: UPDATE perfiles SET es_admin = 1 WHERE nombre = \'Administrador\';');
            $u = $db->table('usuarios')->join('perfiles', 'perfiles.id = usuarios.perfil_id')->where('perfiles.es_admin', 1)->where('usuarios.activo', 1)->countAllResults();
            $chk('Hay al menos un usuario administrador activo', $u > 0, 'Asigna el perfil administrador a un usuario activo.');
        } elseif ($db->tableExists('perfiles')) {
            $chk('Columna perfiles.es_admin', false, 'Ejecuta "php spark migrate".');
        }

        CLI::newLine();
        CLI::write('Correo (opcional)', 'yellow');
        $cfg = $db->tableExists('configuraciones') ? array_column($db->table('configuraciones')->get()->getResultArray(), 'valor', 'clave') : [];
        $chk('SMTP_HOST y SMTP_USER configurados (necesarios para recuperar contraseña y avisos)', ! empty($cfg['SMTP_HOST']) && ! empty($cfg['SMTP_USER']), 'Complétalos en Seguridad → Configuración.');

        CLI::newLine();
        if ($fallos) {
            CLI::error("Se encontraron $fallos problema(s). Corrígelos y vuelve a ejecutar: php spark kgi:diagnostico");

            return 1;
        }
        CLI::write('Todo en orden.', 'green');

        return 0;
    }
}
