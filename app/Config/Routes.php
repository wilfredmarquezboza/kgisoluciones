<?php

use App\Libraries\Permisos;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Público (solo visitantes)
$routes->group('', ['filter' => 'guest'], static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attempt');
    $routes->get('recuperar', 'Auth::forgot');
    $routes->post('recuperar', 'Auth::sendReset');
    $routes->get('restablecer/(:segment)', 'Auth::reset/$1');
    $routes->post('restablecer/(:segment)', 'Auth::updatePassword/$1');
});

// Privado: cada ruta declara el permiso que exige (perm:modulo.accion)
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', static fn () => redirect()->to(Permisos::primeraRuta() ?? 'sin-acceso'));
    $routes->get('sin-acceso', static fn () => view('errors/sin_permiso', ['title' => 'Sin acceso', 'mensaje' => 'Tu perfil aún no tiene módulos asignados.']));
    $routes->post('logout', 'Auth::logout');

    // Control de facturas
    $f = static fn (string $perm) => ['filter' => "perm:$perm"];
    $routes->get('facturas', 'Facturas::index', $f('facturas.ver'));
    $routes->get('facturas/datos', 'Facturas::datos', $f('facturas.ver'));
    $routes->get('facturas/exportar', 'Facturas::exportar', $f('facturas.ver'));
    $routes->get('facturas/historial/(:num)', 'Facturas::historial/$1', $f('facturas.ver'));
    $routes->get('facturas/adjunto/(:num)', 'Facturas::descargar/$1', $f('facturas.ver'));
    $routes->post('facturas/plan', 'Facturas::plan', $f('facturas.editar'));
    $routes->post('facturas/quitar/(:num)', 'Facturas::quitar/$1', $f('facturas.eliminar'));
    $routes->post('facturas/cuota/(:num)', 'Facturas::cuota/$1', ['filter' => 'perm:facturas.editar,facturas.cobrar']);
    $routes->post('facturas/abono/(:num)', 'Facturas::abono/$1', $f('facturas.cobrar'));
    $routes->post('facturas/abono-eliminar/(:num)', 'Facturas::abonoEliminar/$1', $f('facturas.cobrar'));
    $routes->post('facturas/adjunto/(:num)', 'Facturas::adjuntar/$1', $f('facturas.editar'));
    $routes->post('facturas/adjunto-eliminar/(:num)', 'Facturas::adjuntoEliminar/$1', $f('facturas.editar'));
    $routes->post('facturas/avisos', 'Facturas::avisos', $f('facturas.editar'));
    $routes->post('facturas/actividad/(:num)', 'Facturas::actividad/$1', $f('facturas.editar'));
    $routes->post('facturas/actividad-eliminar/(:num)', 'Facturas::actividadEliminar/$1', $f('facturas.eliminar'));
    $routes->get('facturas/flujo', 'Facturas::flujo', $f('flujo.ver'));
    $routes->get('facturas/flujo-datos', 'Facturas::flujoDatos', $f('flujo.ver'));
    $routes->get('facturas/flujo-exportar', 'Facturas::flujoExportar', $f('flujo.ver'));

    // Mantenedores
    foreach (['proyectos' => 'Proyectos', 'clientes' => 'Clientes', 'procesos' => 'Procesos',
        'configuraciones' => 'Configuraciones', 'perfiles' => 'Perfiles', 'usuarios' => 'Usuarios'] as $uri => $ctrl) {
        $routes->get($uri, "$ctrl::index", $f("$uri.ver"));
        $routes->get("$uri/listar", "$ctrl::listar", $f("$uri.ver"));
        $routes->post("$uri/guardar", "$ctrl::guardar", $f("$uri.editar"));
        $routes->post("$uri/eliminar/(:num)", "$ctrl::eliminar/$1", $f("$uri.eliminar"));
    }

    // Permisos de un perfil y historial de cambios
    $routes->get('perfiles/permisos/(:num)', 'Perfiles::permisos/$1', $f('perfiles.editar'));
    $routes->post('perfiles/permisos/(:num)', 'Perfiles::guardarPermisos/$1', $f('perfiles.editar'));
    $routes->get('auditoria', 'Auditoria::index', $f('auditoria.ver'));
    $routes->get('auditoria/listar', 'Auditoria::listar', $f('auditoria.ver'));
});
