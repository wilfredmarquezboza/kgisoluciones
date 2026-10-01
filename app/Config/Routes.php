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

    foreach (['proyectos' => 'Proyectos', 'clientes' => 'Clientes', 'procesos' => 'Procesos',
        'configuraciones' => 'Configuraciones', 'perfiles' => 'Perfiles', 'usuarios' => 'Usuarios'] as $uri => $ctrl) {
        $routes->get($uri, "$ctrl::index");
        $routes->get("$uri/listar", "$ctrl::listar");
        $routes->post("$uri/guardar", "$ctrl::guardar");
        $routes->post("$uri/eliminar/(:num)", "$ctrl::eliminar/$1");
    }
});
