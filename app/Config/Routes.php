<?php

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

// Privado
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', static fn () => redirect()->to('/proyectos'));
    $routes->post('logout', 'Auth::logout');

    $routes->get('facturas', 'Facturas::index');
    $routes->get('facturas/datos', 'Facturas::datos');
    $routes->get('facturas/exportar', 'Facturas::exportar');
    $routes->post('facturas/plan', 'Facturas::plan');
    $routes->post('facturas/quitar/(:num)', 'Facturas::quitar/$1');
    $routes->post('facturas/cuota/(:num)', 'Facturas::cuota/$1');
    $routes->post('facturas/abono/(:num)', 'Facturas::abono/$1');
    $routes->post('facturas/abono-eliminar/(:num)', 'Facturas::abonoEliminar/$1');
    $routes->post('facturas/actividad/(:num)', 'Facturas::actividad/$1');
    $routes->post('facturas/actividad-eliminar/(:num)', 'Facturas::actividadEliminar/$1');

    foreach (['proyectos' => 'Proyectos', 'clientes' => 'Clientes', 'procesos' => 'Procesos',
        'configuraciones' => 'Configuraciones', 'perfiles' => 'Perfiles', 'usuarios' => 'Usuarios'] as $uri => $ctrl) {
        $routes->get($uri, "$ctrl::index");
        $routes->get("$uri/listar", "$ctrl::listar");
        $routes->post("$uri/guardar", "$ctrl::guardar");
        $routes->post("$uri/eliminar/(:num)", "$ctrl::eliminar/$1");
    }
});
