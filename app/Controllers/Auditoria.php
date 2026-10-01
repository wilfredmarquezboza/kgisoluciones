<?php

namespace App\Controllers;

use App\Models\AuditoriaModel;
use CodeIgniter\Database\BaseBuilder;

/** Historial de cambios (solo lectura). */
class Auditoria extends CrudController
{
    protected string $slug = 'auditoria';
    protected string $title = 'Historial de cambios';
    protected string $singular = 'evento';
    protected string $modelClass = AuditoriaModel::class;
    protected bool $readOnly = true;
    protected bool $detail = true;
    protected string $defaultDir = 'DESC';
    protected array $searchColumns = ['auditoria.usuario_nombre', 'auditoria.resumen', 'auditoria.entidad', 'auditoria.ip'];
    protected array $sortMap = ['created_at' => 'auditoria.created_at', 'usuario_nombre' => 'auditoria.usuario_nombre', 'accion' => 'auditoria.accion', 'entidad' => 'auditoria.entidad'];

    public function __construct()
    {
        $entidades = array_column(db_connect()->table('auditoria')->distinct()->select('entidad')->orderBy('entidad')->get()->getResultArray(), 'entidad');
        $this->filters = [
            ['name' => 'accion', 'label' => 'Acción', 'type' => 'select', 'options' => ['crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'cobro' => 'Cobro', 'acceso' => 'Acceso', 'alerta' => 'Aviso']],
            ['name' => 'entidad', 'label' => 'Módulo', 'type' => 'select', 'options' => array_combine($entidades, $entidades)],
            ['name' => 'desde', 'label' => 'Desde', 'type' => 'date'],
            ['name' => 'hasta', 'label' => 'Hasta', 'type' => 'date'],
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Fecha', 'sortable' => true, 'type' => 'datetime'],
            ['key' => 'usuario_nombre', 'label' => 'Usuario', 'sortable' => true],
            ['key' => 'accion', 'label' => 'Acción', 'sortable' => true, 'type' => 'accion'],
            ['key' => 'entidad', 'label' => 'Módulo', 'sortable' => true],
            ['key' => 'resumen', 'label' => 'Detalle'],
        ];
    }

    protected function fields(): array
    {
        return [];
    }

    protected function applyFilters(BaseBuilder $b): BaseBuilder
    {
        $g = $this->request->getGet();
        foreach (['accion', 'entidad'] as $k) {
            if (! empty($g[$k]) && is_string($g[$k])) {
                $b->where("auditoria.$k", $g[$k]);
            }
        }
        foreach (['desde' => ['>=', ' 00:00:00'], 'hasta' => ['<=', ' 23:59:59']] as $k => [$op, $hora]) {
            $d = \DateTime::createFromFormat('Y-m-d', (string) ($g[$k] ?? ''));
            if ($d && $d->format('Y-m-d') === $g[$k]) {
                $b->where("auditoria.created_at $op", $g[$k] . $hora);
            }
        }

        return $b;
    }

    // Solo lectura: nadie puede guardar ni borrar desde aquí aunque llame a la ruta directamente.
    public function guardar()
    {
        return $this->fail('El historial no se puede modificar.', 405);
    }

    public function eliminar(int $id)
    {
        return $this->fail('El historial no se puede modificar.', 405);
    }
}
