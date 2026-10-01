<?php

namespace App\Controllers;

use App\Models\ProcesoModel;

class Procesos extends CrudController
{
    protected string $slug = 'procesos';
    protected string $title = 'Gestión de Procesos';
    protected string $singular = 'proceso';
    protected string $modelClass = ProcesoModel::class;
    protected array $searchColumns = ['procesos.descripcion'];
    protected array $sortMap = ['descripcion' => 'procesos.descripcion', 'fecha' => 'procesos.fecha'];

    protected function columns(): array
    {
        return [
            ['key' => 'descripcion', 'label' => 'Descripción', 'sortable' => true],
            ['key' => 'fecha', 'label' => 'Fecha', 'sortable' => true, 'type' => 'date'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'descripcion', 'label' => 'Descripción', 'type' => 'textarea', 'required' => true, 'rules' => 'required|max_length[255]'],
            ['name' => 'fecha', 'label' => 'Fecha', 'type' => 'date', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
        ];
    }
}
