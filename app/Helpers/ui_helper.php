<?php

/** Menú lateral: grupo => [icono, items[ruta => [etiqueta, icono]]] */
function menu_items(): array
{
    return [
        'Kgi' => ['fa-table-cells', [
            'proyectos' => ['Proyectos', 'fa-diagram-project'],
            'clientes'  => ['Clientes', 'fa-building'],
            'procesos'  => ['Procesos', 'fa-list-check'],
            'facturas'  => ['Control de facturas', 'fa-file-invoice-dollar'],
        ]],
        'Seguridad' => ['fa-shield-halved', [
            'configuraciones' => ['Configuración', 'fa-gear'],
            'perfiles'        => ['Perfiles', 'fa-id-badge'],
            'usuarios'        => ['Usuarios', 'fa-users'],
        ]],
    ];
}

function usuario_actual(): array
{
    return session()->get('usuario') ?? [];
}

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }

    return $ini ?: '?';
}
