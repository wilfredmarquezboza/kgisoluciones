<?php

namespace App\Libraries;

/** Permisos por perfil: "modulo.accion". El perfil con es_admin tiene acceso total. */
class Permisos
{
    /** modulo => [etiqueta, acciones] */
    public const CATALOGO = [
        'proyectos'       => ['Proyectos', ['ver', 'editar', 'eliminar']],
        'clientes'        => ['Clientes', ['ver', 'editar', 'eliminar']],
        'procesos'        => ['Procesos', ['ver', 'editar', 'eliminar']],
        'facturas'        => ['Control de facturas', ['ver', 'editar', 'cobrar', 'eliminar']],
        'flujo'           => ['Flujo de caja', ['ver']],
        'configuraciones' => ['Configuración', ['ver', 'editar', 'eliminar']],
        'perfiles'        => ['Perfiles y permisos', ['ver', 'editar', 'eliminar']],
        'usuarios'        => ['Usuarios', ['ver', 'editar', 'eliminar']],
        'auditoria'       => ['Historial de cambios', ['ver']],
    ];

    public const ACCIONES = [
        'ver'      => 'Ver',
        'editar'   => 'Crear y editar',
        'cobrar'   => 'Registrar cobros',
        'eliminar' => 'Eliminar',
    ];

    /** @var array<int,array{admin:bool,lista:array<string,true>}> caché por petición */
    private static array $cache = [];

    public static function validos(): array
    {
        $out = [];
        foreach (self::CATALOGO as $mod => [, $acciones]) {
            foreach ($acciones as $a) {
                $out[] = "$mod.$a";
            }
        }

        return $out;
    }

    public static function cargar(int $perfilId): array
    {
        if (! isset(self::$cache[$perfilId])) {
            $db     = db_connect();
            $perfil = $db->table('perfiles')->select('es_admin')->where('id', $perfilId)->get()->getRowArray();
            $lista  = [];
            foreach ($db->table('permisos')->select('permiso')->where('perfil_id', $perfilId)->get()->getResultArray() as $r) {
                $lista[$r['permiso']] = true;
            }
            self::$cache[$perfilId] = ['admin' => (bool) ($perfil['es_admin'] ?? false), 'lista' => $lista];
        }

        return self::$cache[$perfilId];
    }

    public static function olvidar(): void
    {
        self::$cache = [];
    }

    public static function puede(string $permiso, ?int $perfilId = null): bool
    {
        $perfilId ??= (int) (session()->get('usuario')['perfil_id'] ?? 0);
        if (! $perfilId) {
            return false;
        }
        $p = self::cargar($perfilId);

        return $p['admin'] || isset($p['lista'][$permiso]);
    }

    public static function esAdmin(?int $perfilId = null): bool
    {
        $perfilId ??= (int) (session()->get('usuario')['perfil_id'] ?? 0);

        return $perfilId > 0 && self::cargar($perfilId)['admin'];
    }

    /** Primera ruta del menú a la que el usuario puede entrar. */
    public static function primeraRuta(): ?string
    {
        helper('ui');
        foreach (menu_items() as [, $items]) {
            foreach ($items as $ruta => $_) {
                if (self::puede(self::modulo($ruta) . '.ver')) {
                    return $ruta;
                }
            }
        }

        return null;
    }

    /** Ruta del menú -> módulo del catálogo. */
    public static function modulo(string $ruta): string
    {
        return $ruta === 'facturas/flujo' ? 'flujo' : $ruta;
    }
}
