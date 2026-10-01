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
            'facturas/flujo' => ['Flujo de caja', 'fa-chart-column'],
        ]],
        'Seguridad' => ['fa-shield-halved', [
            'configuraciones' => ['Configuración', 'fa-gear'],
            'perfiles'        => ['Perfiles y permisos', 'fa-id-badge'],
            'usuarios'        => ['Usuarios', 'fa-users'],
            'auditoria'       => ['Historial de cambios', 'fa-clock-rotate-left'],
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

function puede(string $permiso): bool
{
    return \App\Libraries\Permisos::puede($permiso);
}

/** Menú con solo las opciones a las que el usuario puede entrar; sin grupos vacíos. */
function menu_permitido(): array
{
    $out = [];
    foreach (menu_items() as $grupo => [$icono, $items]) {
        $ok = array_filter($items, static fn ($_, $ruta) => puede(\App\Libraries\Permisos::modulo($ruta) . '.ver'), ARRAY_FILTER_USE_BOTH);
        if ($ok) {
            $out[$grupo] = [$icono, $ok];
        }
    }

    return $out;
}
